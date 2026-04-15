<?php
/**
 * Crater Cache Fix & Diagnostic
 * Subir a /html/crater/ y ejecutar desde el navegador:
 * https://crater.joseosuna.com/fix_crater.php
 * 
 * ¡¡BORRAR ESTE ARCHIVO DESPUÉS DE USARLO!!
 */

echo "<h1>Crater - Diagnóstico y Limpieza de Caché</h1><pre>";

// 1. Cargar el entorno Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "✓ Laravel cargado correctamente\n\n";

// 2. Limpiar todas las cachés
echo "=== LIMPIANDO CACHÉS ===\n";

try {
    Artisan::call('cache:clear');
    echo "✓ cache:clear - " . trim(Artisan::output()) . "\n";
} catch (Exception $e) {
    echo "✗ cache:clear - " . $e->getMessage() . "\n";
}

try {
    Artisan::call('config:clear');
    echo "✓ config:clear - " . trim(Artisan::output()) . "\n";
} catch (Exception $e) {
    echo "✗ config:clear - " . $e->getMessage() . "\n";
}

try {
    Artisan::call('view:clear');
    echo "✓ view:clear - " . trim(Artisan::output()) . "\n";
} catch (Exception $e) {
    echo "✗ view:clear - " . $e->getMessage() . "\n";
}

try {
    Artisan::call('route:clear');
    echo "✓ route:clear - " . trim(Artisan::output()) . "\n";
} catch (Exception $e) {
    echo "✗ route:clear - " . $e->getMessage() . "\n";
}

// Borrar caché de bootstrap
$cacheFiles = glob(__DIR__.'/bootstrap/cache/*.php');
foreach ($cacheFiles as $file) {
    if (basename($file) !== '.gitignore') {
        @unlink($file);
    }
}
echo "✓ bootstrap/cache limpiado\n\n";

// 3. Diagnóstico de datos
echo "=== DIAGNÓSTICO DE DATOS ===\n";

$db = app('db');

// Verificar empresa activa
$company = $db->table('companies')->first();
echo "Empresa: {$company->name} (ID: {$company->id})\n";

// Contar registros
$counts = [
    'customers' => $db->table('customers')->where('company_id', $company->id)->count(),
    'invoices' => $db->table('invoices')->where('company_id', $company->id)->count(),
    'invoice_items' => $db->table('invoice_items')->where('company_id', $company->id)->count(),
    'taxes' => $db->table('taxes')->where('company_id', $company->id)->count(),
    'tax_types' => $db->table('tax_types')->where('company_id', $company->id)->count(),
    'payments' => $db->table('payments')->where('company_id', $company->id)->count(),
];

foreach ($counts as $table => $count) {
    $status = $count > 0 ? '✓' : '✗';
    echo "{$status} {$table}: {$count} registros\n";
}

// Verificar una factura de ejemplo
echo "\n=== FACTURA DE EJEMPLO (primera factura) ===\n";
$invoice = $db->table('invoices')->where('company_id', $company->id)->first();
if ($invoice) {
    echo "ID: {$invoice->id}\n";
    echo "Número: {$invoice->invoice_number}\n";
    echo "Estado: {$invoice->status} / {$invoice->paid_status}\n";
    echo "SubTotal: " . ($invoice->sub_total / 100) . " EUR\n";
    echo "Tax: " . ($invoice->tax / 100) . " EUR\n";
    echo "Total: " . ($invoice->total / 100) . " EUR\n";
    echo "tax_per_item: {$invoice->tax_per_item}\n";
    echo "discount_per_item: {$invoice->discount_per_item}\n";
    echo "customer_id: {$invoice->customer_id}\n";
    echo "currency_id: {$invoice->currency_id}\n";
    echo "company_id: {$invoice->company_id}\n";
    
    $items = $db->table('invoice_items')->where('invoice_id', $invoice->id)->get();
    echo "\nItems de la factura ({$items->count()}):\n";
    foreach ($items as $item) {
        $price = $item->price / 100;
        $total = $item->total / 100;
        echo "  - {$item->name}: {$item->description} | {$price} EUR x {$item->quantity} = {$total} EUR\n";
    }
    
    $taxes = $db->table('taxes')->where('invoice_id', $invoice->id)->get();
    echo "\nImpuestos de la factura ({$taxes->count()}):\n";
    foreach ($taxes as $tax) {
        $amount = $tax->amount / 100;
        echo "  - {$tax->name}: {$tax->percent}% = {$amount} EUR (tax_type_id: {$tax->tax_type_id})\n";
    }

    // Verificar que el customer existe
    $customer = $db->table('customers')->where('id', $invoice->customer_id)->first();
    if ($customer) {
        echo "\nCliente: {$customer->name} (ID: {$customer->id}) ✓\n";
    } else {
        echo "\n✗ ¡¡CLIENTE NO ENCONTRADO!! customer_id={$invoice->customer_id}\n";
    }

    // Verificar currency
    $currency = $db->table('currencies')->where('id', $invoice->currency_id)->first();
    if ($currency) {
        echo "Moneda: {$currency->name} ({$currency->code}) ✓\n";
    } else {
        echo "✗ ¡¡MONEDA NO ENCONTRADA!! currency_id={$invoice->currency_id}\n";
    }
}

// Verificar company_settings relevantes
echo "\n=== CONFIGURACIÓN DE EMPRESA ===\n";
$settings = $db->table('company_settings')
    ->where('company_id', $company->id)
    ->whereIn('option', ['currency', 'tax_per_item', 'discount_per_item', 'invoice_number_format'])
    ->get();

foreach ($settings as $s) {
    echo "{$s->option}: {$s->value}\n";
}

echo "\n=== VERIFICACIÓN DE INTEGRIDAD ===\n";

// Items sin factura válida
$orphanItems = $db->select("
    SELECT COUNT(*) as cnt FROM invoice_items ii 
    LEFT JOIN invoices i ON ii.invoice_id = i.id 
    WHERE i.id IS NULL AND ii.company_id = ?
", [$company->id]);
echo "Items huérfanos (sin factura): {$orphanItems[0]->cnt}\n";

// Facturas sin items
$emptyInvoices = $db->select("
    SELECT COUNT(*) as cnt FROM invoices i 
    LEFT JOIN invoice_items ii ON ii.invoice_id = i.id 
    WHERE ii.id IS NULL AND i.company_id = ?
", [$company->id]);
echo "Facturas sin items: {$emptyInvoices[0]->cnt}\n";

// Taxes sin factura
$orphanTaxes = $db->select("
    SELECT COUNT(*) as cnt FROM taxes t 
    LEFT JOIN invoices i ON t.invoice_id = i.id 
    WHERE i.id IS NULL AND t.company_id = ? AND t.invoice_id IS NOT NULL
", [$company->id]);
echo "Impuestos huérfanos: {$orphanTaxes[0]->cnt}\n";

echo "\n</pre>";
echo "<hr><p style='color:red'><strong>⚠️ IMPORTANTE: Borra este archivo (fix_crater.php) del servidor después de usarlo.</strong></p>";
echo "<p>Ahora prueba a entrar en <a href='/'>Crater</a> y ver una factura.</p>";
