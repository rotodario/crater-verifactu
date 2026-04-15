<?php

namespace Crater\Services\Verifactu;

use Carbon\Carbon;
use Crater\Models\CompanySetting;
use Crater\Models\Invoice;
use Crater\Services\SerialNumberFormatter;
use Vinkla\Hashids\Facades\Hashids;

class VerifactuRectificationService
{
    public const TYPE_REPLACEMENT = 'REPLACEMENT';

    public const TYPE_DIFFERENCES = 'DIFFERENCES';

    protected VerifactuRectificativeNumberFormatter $numberFormatter;

    public function __construct(VerifactuRectificativeNumberFormatter $numberFormatter)
    {
        $this->numberFormatter = $numberFormatter;
    }

    public function createRectificativeInvoice(Invoice $invoice, array $data = [])
    {
        if ($invoice->invoice_kind === 'RECTIFICATIVE') {
            return 'rectificative_invoice_cannot_be_rectified';
        }

        if (! $invoice->isFiscalIssued()) {
            return 'invoice_must_be_fiscally_issued';
        }

        $rectificationType = strtoupper($data['rectification_type'] ?? self::TYPE_REPLACEMENT);

        if ($rectificationType === self::TYPE_DIFFERENCES) {
            return 'rectification_type_not_supported';
        }

        $existingDraftRectificative = Invoice::where('original_invoice_id', $invoice->id)
            ->where('invoice_kind', 'RECTIFICATIVE')
            ->where('status', Invoice::STATUS_DRAFT)
            ->first();

        if ($existingDraftRectificative) {
            return 'draft_rectificative_already_exists';
        }

        $invoice->loadMissing(['items.taxes', 'taxes', 'fields', 'customer']);

        $globalSerial = (new SerialNumberFormatter())
            ->setModel($invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setNextNumbers();

        $rectificativeNumber = $this->numberFormatter
            ->forInvoice($invoice)
            ->getNextNumber();

        $dueDateEnabled = CompanySetting::getSetting(
            'invoice_set_due_date_automatically',
            $invoice->company_id
        );

        $dueDate = null;

        if ($dueDateEnabled === 'YES') {
            $dueDateDays = CompanySetting::getSetting(
                'invoice_due_date_days',
                $invoice->company_id
            );
            $dueDate = Carbon::now()->addDays($dueDateDays)->format('Y-m-d');
        }

        $reason = trim((string) ($data['rectification_reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Rectificativa de la factura ' . $invoice->invoice_number;
        }

        $rectificative = Invoice::create([
            'creator_id' => $invoice->creator_id,
            'invoice_date' => Carbon::now()->format('Y-m-d'),
            'due_date' => $dueDate,
            'invoice_number' => $rectificativeNumber,
            'sequence_number' => $globalSerial->nextSequenceNumber,
            'customer_sequence_number' => $globalSerial->nextCustomerSequenceNumber,
            'reference_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
            'company_id' => $invoice->company_id,
            'template_name' => $invoice->template_name,
            'invoice_kind' => 'RECTIFICATIVE',
            'original_invoice_id' => $invoice->id,
            'rectification_type' => $rectificationType,
            'rectification_reason' => $reason,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'fiscal_status' => Invoice::FISCAL_STATUS_NOT_ISSUED,
            'sub_total' => $invoice->sub_total,
            'discount' => $invoice->discount,
            'discount_type' => $invoice->discount_type,
            'discount_val' => $invoice->discount_val,
            'total' => $invoice->total,
            'due_amount' => $invoice->total,
            'tax_per_item' => $invoice->tax_per_item,
            'discount_per_item' => $invoice->discount_per_item,
            'tax' => $invoice->tax,
            'notes' => $reason,
            'exchange_rate' => $invoice->exchange_rate,
            'base_total' => $invoice->base_total,
            'base_discount_val' => $invoice->base_discount_val,
            'base_sub_total' => $invoice->base_sub_total,
            'base_tax' => $invoice->base_tax,
            'base_due_amount' => $invoice->base_due_amount,
            'currency_id' => $invoice->currency_id,
            'sales_tax_type' => $invoice->sales_tax_type,
            'sales_tax_address_type' => $invoice->sales_tax_address_type,
        ]);

        $rectificative->unique_hash = Hashids::connection(Invoice::class)->encode($rectificative->id);
        $rectificative->save();

        foreach ($invoice->items->toArray() as $invoiceItem) {
            $invoiceItem['company_id'] = $invoice->company_id;
            $item = $rectificative->items()->create($invoiceItem);

            if (array_key_exists('taxes', $invoiceItem) && $invoiceItem['taxes']) {
                foreach ($invoiceItem['taxes'] as $tax) {
                    $tax['company_id'] = $invoice->company_id;

                    if ($tax['amount']) {
                        $item->taxes()->create($tax);
                    }
                }
            }
        }

        foreach ($invoice->taxes->toArray() as $tax) {
            $tax['company_id'] = $invoice->company_id;
            $rectificative->taxes()->create($tax);
        }

        if ($invoice->fields()->exists()) {
            $customFields = [];

            foreach ($invoice->fields as $field) {
                $customFields[] = [
                    'id' => $field->custom_field_id,
                    'value' => $field->defaultAnswer,
                ];
            }

            $rectificative->addCustomFields($customFields);
        }

        return $rectificative;
    }
}
