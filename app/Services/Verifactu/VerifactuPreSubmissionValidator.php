<?php

namespace Crater\Services\Verifactu;

use Crater\Models\Invoice;
use Crater\Models\VerifactuInstallation;

class VerifactuPreSubmissionValidator
{
    /**
     * Validate that an invoice is ready to be fiscally issued.
     * Returns an array with 'valid' (bool) and 'errors' (string[]).
     */
    public function validate(Invoice $invoice, VerifactuInstallation $installation): array
    {
        $invoice->loadMissing(['company.address', 'customer', 'items']);

        $errors = array_merge(
            $this->validateInvoice($invoice),
            $this->validateCompany($invoice),
            $this->validateCustomer($invoice),
            $this->validateInstallation($installation)
        );

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    private function validateInvoice(Invoice $invoice): array
    {
        $errors = [];

        if (empty($invoice->invoice_number)) {
            $errors[] = 'invoice.invoice_number: required';
        }

        if (empty($invoice->invoice_date)) {
            $errors[] = 'invoice.invoice_date: required';
        }

        if (! $invoice->total || $invoice->total <= 0) {
            $errors[] = 'invoice.total: must be greater than zero';
        }

        if ($invoice->items->isEmpty()) {
            $errors[] = 'invoice.items: at least one line item is required';
        }

        return $errors;
    }

    private function validateCompany(Invoice $invoice): array
    {
        $errors = [];

        $company = $invoice->company;

        if (! $company) {
            return ['company: not found'];
        }

        if (empty($company->name)) {
            $errors[] = 'company.name: required';
        }

        if (empty($company->tax_number)) {
            $errors[] = 'company.tax_number: NIF/CIF is required for VERI*FACTU';
        }

        return $errors;
    }

    private function validateCustomer(Invoice $invoice): array
    {
        $errors = [];

        $customer = $invoice->customer;

        if (! $customer) {
            return ['customer: not found'];
        }

        if (empty($customer->name)) {
            $errors[] = 'customer.name: required';
        }

        return $errors;
    }

    private function validateInstallation(VerifactuInstallation $installation): array
    {
        $errors = [];

        if (empty($installation->software_name)) {
            $errors[] = 'installation.software_name: required';
        }

        if (empty($installation->software_version)) {
            $errors[] = 'installation.software_version: required';
        }

        return $errors;
    }
}
