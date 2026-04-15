<?php
/**
 * Debug script for Crater installation issues
 * Save this as debug.php in the root of your Crater folder (/html/crater/debug.php)
 * Then navigate to https://crater.joseosuna.com/debug.php
 */

header('Content-Type: text/plain');

// 1. PHP version and SAPI
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n\n";

// 2. Document root and script locations
echo "\$_SERVER['DOCUMENT_ROOT']: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set') . "\n";
echo "__DIR__ (this file): " . __DIR__ . "\n\n";

// 3. Base paths
$basePath       = realpath(__DIR__);
$storagePath    = $basePath . '/storage';
$storageApp     = $storagePath . '/app';
$flagFile       = $storageApp . '/database_created';
$vendorAutoload = $basePath . '/vendor/autoload.php';
$resourcesPath  = $basePath . '/resources/views';
$viewsFile      = $resourcesPath . '/app.blade.php';
$publicPath     = $basePath . '/public';
$publicIndex    = $publicPath . '/index.php';
$publicHtaccess = $publicPath . '/.htaccess';
$publicBuild    = $publicPath . '/build';

echo "Base path: $basePath\n";
echo "Vendor autoload exists: " . (file_exists($vendorAutoload) ? 'yes' : 'no') . "\n";
echo "Resources/views/app.blade.php exists: " . (file_exists($viewsFile) ? 'yes' : 'no') . "\n\n";

// 4. Storage checks
echo "Storage base: $storagePath\n";
echo "Storage/app exists: " . (is_dir($storageApp) ? 'yes' : 'no') . "\n";
echo "Flag file: $flagFile\n";
echo "Flag exists: " . (file_exists($flagFile) ? 'yes' : 'no') . "\n\n";

function listDir($label, $path) {
    echo "Contents of $label ($path):\n";
    if (! is_dir($path)) {
        echo "  [not a directory or does not exist]\n\n";
        return;
    }
    foreach (scandir($path) as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $path . DIRECTORY_SEPARATOR . $item;
        echo "  - $item" . (is_dir($full) ? '/' : '') . "\n";
    }
    echo "\n";
}

listDir('storage', $storagePath);
listDir('storage/app', $storageApp);
listDir('resources/views', $resourcesPath);

// 5. Public checks
echo "Public/index.php exists: " . (file_exists($publicIndex) ? 'yes' : 'no') . "\n";
echo "Public/.htaccess exists: " . (file_exists($publicHtaccess) ? 'yes' : 'no') . "\n\n";
listDir('public', $publicPath);
listDir('public/build', $publicBuild);

// 6. .env
$envPath = $basePath . '/.env';
echo ".env exists: " . (file_exists($envPath) ? 'yes' : 'no') . "\n";
if (file_exists($envPath)) {
    echo "\nContents of .env:\n";
    echo file_get_contents($envPath);
}

echo "\n-- Debug script complete --\n";
