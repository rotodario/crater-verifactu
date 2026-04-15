<?php

namespace Crater\Services\Verifactu;

use Crater\Models\CompanySetting;
use Crater\Models\Invoice;
use Crater\Services\SerialNumberFormatter;

class VerifactuRectificativeNumberFormatter
{
    protected Invoice $sourceInvoice;

    protected int $companyId;

    protected ?int $customerId = null;

    public function forInvoice(Invoice $invoice): self
    {
        $this->sourceInvoice = $invoice;
        $this->companyId = (int) $invoice->company_id;
        $this->customerId = $invoice->customer_id ? (int) $invoice->customer_id : null;

        return $this;
    }

    public function getNextNumber(): string
    {
        $format = $this->getFormat();
        $serialNumber = '';
        $placeholders = SerialNumberFormatter::getPlaceholders($format);

        foreach ($placeholders as $placeholder) {
            $name = $placeholder['name'];
            $value = $placeholder['value'];

            switch ($name) {
                case 'SEQUENCE':
                    $length = $value ?: 6;
                    $serialNumber .= str_pad((string) $this->getNextSequenceNumber(), (int) $length, '0', STR_PAD_LEFT);
                    break;
                case 'CUSTOMER_SEQUENCE':
                    $length = $value ?: 6;
                    $serialNumber .= str_pad((string) $this->getNextCustomerSequenceNumber(), (int) $length, '0', STR_PAD_LEFT);
                    break;
                case 'DATE_FORMAT':
                    $serialNumber .= date($value ?: 'Y');
                    break;
                case 'CUSTOMER_SERIES':
                    $serialNumber .= optional($this->sourceInvoice->customer)->prefix ?: 'CST';
                    break;
                case 'RANDOM_SEQUENCE':
                    $length = $value ?: 6;
                    $serialNumber .= substr(bin2hex(random_bytes((int) $length)), 0, (int) $length);
                    break;
                default:
                    $serialNumber .= $value;
                    break;
            }
        }

        return $serialNumber;
    }

    public function getNextSequenceNumber(): int
    {
        $last = $this->baseQuery()
            ->where('invoice_number', 'like', $this->getSequencePrefix() . '%')
            ->orderBy('id', 'desc')
            ->first();

        if (! $last) {
            return 1;
        }

        return $this->extractNumericTail($last->invoice_number) + 1;
    }

    public function getNextCustomerSequenceNumber(): int
    {
        $last = $this->baseQuery()
            ->when($this->customerId, function ($query) {
                $query->where('customer_id', $this->customerId);
            })
            ->where('invoice_number', 'like', $this->getCustomerSequencePrefix() . '%')
            ->orderBy('id', 'desc')
            ->first();

        if (! $last) {
            return 1;
        }

        return $this->extractNumericTail($last->invoice_number) + 1;
    }

    protected function baseQuery()
    {
        return Invoice::query()
            ->where('company_id', $this->companyId)
            ->where('invoice_kind', 'RECTIFICATIVE');
    }

    protected function getFormat(): string
    {
        return CompanySetting::getSetting('verifactu_rectificative_number_format', $this->companyId)
            ?: config('verifactu.rectificative.number_format');
    }

    protected function getSequencePrefix(): string
    {
        return $this->buildPrefix(['SEQUENCE', 'RANDOM_SEQUENCE']);
    }

    protected function getCustomerSequencePrefix(): string
    {
        return $this->buildPrefix(['CUSTOMER_SEQUENCE', 'RANDOM_SEQUENCE']);
    }

    protected function buildPrefix(array $skipPlaceholders): string
    {
        $prefix = '';
        $placeholders = SerialNumberFormatter::getPlaceholders($this->getFormat());

        foreach ($placeholders as $placeholder) {
            $name = $placeholder['name'];
            $value = $placeholder['value'];

            if (in_array($name, $skipPlaceholders, true)) {
                continue;
            }

            switch ($name) {
                case 'DATE_FORMAT':
                    $prefix .= date($value ?: 'Y');
                    break;
                case 'CUSTOMER_SERIES':
                    $prefix .= optional($this->sourceInvoice->customer)->prefix ?: 'CST';
                    break;
                default:
                    $prefix .= $value;
                    break;
            }
        }

        return $prefix;
    }

    protected function extractNumericTail(?string $invoiceNumber): int
    {
        if (! $invoiceNumber) {
            return 0;
        }

        if (preg_match('/(\d+)(?!.*\d)/', $invoiceNumber, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
