<?php

namespace Crater\Services\Verifactu;

use Crater\Models\Invoice;
use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuPlatformConfig;
use Crater\Models\VerifactuRecord;
use Illuminate\Support\Carbon;

class VerifactuRecordBuilder
{
    public function build(Invoice $invoice, VerifactuInstallation $installation)
    {
        $invoice->loadMissing([
            'company.address',
            'customer.billingAddress',
            'customer.shippingAddress',
            'items.taxes.taxType',
            'taxes.taxType',
            'currency',
        ]);

        $platform = VerifactuPlatformConfig::current();

        $issuedAt = Carbon::now();
        $previousRecord = VerifactuRecord::where('company_id', $invoice->company_id)
            ->latest('id')
            ->first();

        $snapshot = [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'reference_number' => $invoice->reference_number,
                'date' => optional($invoice->invoice_date)->format('Y-m-d'),
                'due_date' => optional($invoice->due_date)->format('Y-m-d'),
                'status' => $invoice->status,
                'paid_status' => $invoice->paid_status,
                'tax_per_item' => $invoice->tax_per_item,
                'discount_per_item' => $invoice->discount_per_item,
                'currency_id' => $invoice->currency_id,
                'exchange_rate' => $invoice->exchange_rate,
                'sub_total' => $invoice->sub_total,
                'discount_total' => $invoice->discount,
                'discount_value' => $invoice->discount_val,
                'tax_total' => $invoice->tax,
                'total' => $invoice->total,
                'due_amount' => $invoice->due_amount,
                'notes' => $invoice->notes,
                'template_name' => $invoice->template_name,
            ],
            'company' => [
                'id' => optional($invoice->company)->id,
                'name' => optional($invoice->company)->name,
                'tax_number' => optional($invoice->company)->tax_number,
            ],
            'customer' => [
                'id' => optional($invoice->customer)->id,
                'name' => optional($invoice->customer)->name,
                'company_name' => optional($invoice->customer)->company_name,
                'tax_number' => optional($invoice->customer)->tax_number,
                'email' => optional($invoice->customer)->email,
            ],
            'items' => $invoice->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'discount_type' => $item->discount_type,
                    'discount' => $item->discount,
                    'discount_val' => $item->discount_val,
                    'tax' => $item->tax,
                    'total' => $item->total,
                    'taxes' => $item->taxes->map(function ($tax) {
                        return [
                            'name' => $tax->name,
                            'percent' => $tax->percent,
                            'amount' => $tax->amount,
                            'compound_tax' => $tax->compound_tax,
                        ];
                    })->values()->toArray(),
                ];
            })->values()->toArray(),
            'taxes' => $invoice->taxes->map(function ($tax) {
                return [
                    'name' => $tax->name,
                    'percent' => $tax->percent,
                    'amount' => $tax->amount,
                    'compound_tax' => $tax->compound_tax,
                ];
            })->values()->toArray(),
            'software' => [
                // Per-installation fields (company-specific)
                'name'                => $installation->software_name     ?: $platform->software_name    ?: config('verifactu.software.name'),
                'version'             => $installation->software_version   ?: $platform->software_version ?: config('verifactu.software.version'),
                'installation_number' => $installation->installation_number ?: config('verifactu.software.installation_number', '1'),
                // Global platform SIF identity (same for every company on this deployment)
                'vendor_name'         => $platform->vendor_name    ?: config('verifactu.software.vendor_name'),
                'vendor_tax_id'       => $platform->vendor_tax_id  ?: config('verifactu.software.vendor_tax_id'),
                'software_id'         => $platform->software_id    ?: config('verifactu.software.id', 'CRATER-VF-01'),
            ],
        ];

        $invoiceUid = implode('-', [
            $invoice->company_id,
            $invoice->invoice_number,
            $issuedAt->format('YmdHis'),
        ]);

        $tipoFactura = VerifactuHuellaComputer::tipoFactura([
            'invoice_kind' => $invoice->invoice_kind ?? null,
        ]);

        $huella = (new VerifactuHuellaComputer())->compute(
            issuerNif:       optional($invoice->company)->tax_number ?? '',
            invoiceNumber:   $invoice->invoice_number,
            invoiceDate:     VerifactuHuellaComputer::formatInvoiceDate(
                                 $invoice->invoice_date ?? $issuedAt
                             ),
            tipoFactura:     $tipoFactura,
            cuotaTotal:      VerifactuHuellaComputer::formatAmount((int) $invoice->tax),
            importeTotal:    VerifactuHuellaComputer::formatAmount((int) $invoice->total),
            previousHuella:  optional($previousRecord)->hash,
            fechaHoraHuso:   VerifactuHuellaComputer::formatTimestamp($issuedAt),
        );

        return [
            'record_type' => 'invoice_registration',
            'status' => 'ISSUED',
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => optional($invoice->invoice_date)->format('Y-m-d'),
            'invoice_uid' => $invoiceUid,
            'tipo_factura' => $tipoFactura,
            'hash' => $huella,
            'previous_hash' => optional($previousRecord)->hash,
            'issued_at' => $issuedAt,
            'locked_at' => $issuedAt,
            'snapshot' => $snapshot,
            'metadata' => [
                'source_status' => $invoice->status,
                'source_paid_status' => $invoice->paid_status,
                'installation_mode' => $installation->mode,
            ],
        ];
    }
}
