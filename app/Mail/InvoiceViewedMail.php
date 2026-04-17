<?php

namespace Crater\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceViewedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $companyId = $this->data['company_id'] ?? null;
        $mailService = app(\Crater\Services\CompanyMailService::class);

        $from     = $companyId ? $mailService->getFromAddress($companyId) : config('mail.from.address');
        $fromName = $companyId ? $mailService->getFromName($companyId)    : config('mail.from.name');

        return $this->from($from, $fromName)
                    ->markdown('emails.viewed.invoice', ['data', $this->data]);
    }
}
