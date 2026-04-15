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
}
