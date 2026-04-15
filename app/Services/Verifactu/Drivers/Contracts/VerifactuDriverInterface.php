<?php

namespace Crater\Services\Verifactu\Drivers\Contracts;

use Crater\Models\VerifactuSubmission;

interface VerifactuDriverInterface
{
    /**
     * Process a submission. Responsible for updating the submission
     * status (ACCEPTED / FAILED) and the associated record.
     */
    public function submit(VerifactuSubmission $submission): void;

    /**
     * Driver identifier stored in verifactu_submissions.driver.
     */
    public function getName(): string;
}
