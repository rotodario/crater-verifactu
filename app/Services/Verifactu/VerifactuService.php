<?php

namespace Crater\Services\Verifactu;

use Crater\Models\Invoice;
use Crater\Models\VerifactuRecord;

class VerifactuService
{
    protected $resolver;
    protected $recordBuilder;
    protected $stateManager;
    protected $qrService;
    protected $eventLogger;
    protected $submissionService;
    protected $declarationService;
    protected $driverManager;
    protected $validator;

    public function __construct(
        VerifactuInstallationResolver $resolver,
        VerifactuRecordBuilder $recordBuilder,
        VerifactuStateManager $stateManager,
        VerifactuQrService $qrService,
        VerifactuEventLogger $eventLogger,
        VerifactuSubmissionService $submissionService,
        VerifactuDeclarationService $declarationService,
        VerifactuDriverManager $driverManager,
        VerifactuPreSubmissionValidator $validator
    ) {
        $this->resolver = $resolver;
        $this->recordBuilder = $recordBuilder;
        $this->stateManager = $stateManager;
        $this->qrService = $qrService;
        $this->eventLogger = $eventLogger;
        $this->submissionService = $submissionService;
        $this->declarationService = $declarationService;
        $this->driverManager = $driverManager;
        $this->validator = $validator;
    }

    public function ensureIssued(Invoice $invoice, array $context = [])
    {
        if ($this->driverManager->isOff()) {
            return null;
        }

        if ($invoice->isFiscalIssued()) {
            return $invoice->verifactuRecord;
        }

        $installation = $this->resolver->resolveForCompany($invoice->company_id);

        if ($this->driverManager->isOffForInstallation($installation)) {
            return null;
        }

        $validation = $this->validator->validate($invoice, $installation);

        if (! $validation['valid']) {
            $this->eventLogger->log(
                'validation_failed',
                $invoice,
                null,
                ['errors' => $validation['errors']],
                'Invoice failed VERI*FACTU pre-submission validation.'
            );

            throw new \RuntimeException(
                'VERI*FACTU validation failed: ' . implode(' | ', $validation['errors'])
            );
        }

        $this->declarationService->ensureDraftDeclaration($invoice->company_id);

        $recordAttributes = $this->recordBuilder->build($invoice, $installation);

        $record = VerifactuRecord::create(array_merge($recordAttributes, [
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'verifactu_installation_id' => $installation->id,
        ]));

        $record->qr_payload = $this->qrService->buildPayload($invoice, $record);
        $record->save();

        $this->stateManager->markIssued($invoice, $record->id);
        $this->submissionService->queueSubmission($record);

        $this->eventLogger->log(
            'invoice_issued',
            $invoice,
            $record,
            $context,
            'Invoice fiscally issued and locked for VERI*FACTU.'
        );

        return $record;
    }

    public function annulInvoice(Invoice $invoice, array $context = [])
    {
        if ($this->driverManager->isOff()) {
            return null;
        }

        if (! $invoice->isFiscalIssued()) {
            throw new \RuntimeException('La factura no está expedida fiscalmente y no puede anularse.');
        }

        if ($invoice->fiscal_status === Invoice::FISCAL_STATUS_ANNULLED) {
            throw new \RuntimeException('La factura ya está anulada fiscalmente.');
        }

        $installation = $this->resolver->resolveForCompany($invoice->company_id);

        if ($this->driverManager->isOffForInstallation($installation)) {
            return null;
        }

        // Build a cancellation record using the same snapshot as the original
        $originalRecord = $invoice->verifactuRecord;
        $issuedAt       = \Carbon\Carbon::now('UTC'); // must be UTC — see VerifactuRecordBuilder

        $previousRecord = \Crater\Models\VerifactuRecord::where('company_id', $invoice->company_id)
            ->latest('id')
            ->first();

        $fechaHoraHuso  = VerifactuHuellaComputer::formatTimestamp($issuedAt);
        $huellaComputer = new VerifactuHuellaComputer();
        $huella = $huellaComputer->computeBaja(
            issuerNif:      $invoice->company->tax_number ?? '',
            invoiceNumber:  $invoice->invoice_number,
            invoiceDate:    VerifactuHuellaComputer::formatInvoiceDate(
                                $invoice->invoice_date ?? $issuedAt
                            ),
            previousHuella: optional($previousRecord)->hash,
            fechaHoraHuso:  $fechaHoraHuso,
        );

        $record = \Crater\Models\VerifactuRecord::create([
            'company_id'               => $invoice->company_id,
            'invoice_id'               => $invoice->id,
            'verifactu_installation_id' => $installation->id,
            'record_type'              => 'invoice_cancellation',
            'status'                   => 'ISSUED',
            'invoice_number'           => $invoice->invoice_number,
            'invoice_date'             => optional($invoice->invoice_date)->format('Y-m-d'),
            'invoice_uid'              => implode('-', [$invoice->company_id, $invoice->invoice_number, $issuedAt->format('YmdHis'), 'BAJA']),
            'tipo_factura'             => $originalRecord->tipo_factura ?? 'F1',
            'hash'                     => $huella,
            'previous_hash'            => optional($previousRecord)->hash,
            'issued_at'                => $issuedAt,
            'locked_at'                => $issuedAt,
            'snapshot'                 => $originalRecord ? $originalRecord->snapshot : [],
            'metadata'                 => [
                'cancellation_of_record_id' => optional($originalRecord)->id,
                'fecha_hora_huso'           => $fechaHoraHuso,
            ],
        ]);

        $this->stateManager->markAnnulled($invoice);
        $this->submissionService->queueSubmission($record);

        $this->eventLogger->log(
            'invoice_annulled',
            $invoice,
            $record,
            $context,
            'Invoice fiscally annulled. RegistroBaja created and queued.'
        );

        return $record;
    }
}
