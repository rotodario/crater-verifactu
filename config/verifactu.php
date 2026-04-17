<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Operating mode
    |--------------------------------------------------------------------------
    | Controls the entire VERI*FACTU pipeline behaviour.
    |
    | off             — Completely disabled. No records, no submissions.
    | shadow          — Records created and locked, no submissions queued.
    |                   Use this to observe without committing to AEAT.
    | stub            — Records + submissions processed by the stub driver.
    |                   Full simulation, nothing leaves the server. Default for dev.
    | aeat_sandbox    — Records + submissions sent to the AEAT test endpoint.
    | aeat_production — Records + submissions sent to the AEAT live endpoint.
    */
    'mode' => env('VERIFACTU_MODE', 'stub'),

    /*
    |--------------------------------------------------------------------------
    | Issue on send
    |--------------------------------------------------------------------------
    | Whether to fiscally issue the invoice automatically when it is sent
    | (email) or marked as sent. Set to false to require manual issuance.
    */
    'issue_on_send' => env('VERIFACTU_ISSUE_ON_SEND', true),

    /*
    |--------------------------------------------------------------------------
    | Software identification
    |--------------------------------------------------------------------------
    | Identifies the invoicing software in VERI*FACTU records and submissions.
    */
    'software' => [
        'name'                => env('VERIFACTU_SOFTWARE_NAME', 'Crater VERI*FACTU Integration'),
        'version'             => env('VERIFACTU_SOFTWARE_VERSION', '0.1.0'),
        'vendor_name'         => env('VERIFACTU_VENDOR_NAME', 'Local Integration'),
        'vendor_tax_id'       => env('VERIFACTU_VENDOR_TAX_ID'),
        // IdSistemaInformatico: assigned by AEAT when registering the software.
        // Use any identifier during development; required for sandbox/production.
        'id'                  => env('VERIFACTU_SOFTWARE_ID', 'CRATER-VF-01'),
        // NumeroInstalacion: unique identifier for this installation.
        'installation_number' => env('VERIFACTU_INSTALLATION_NUMBER', '1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | QR code
    |--------------------------------------------------------------------------
    | Base URL embedded in the QR payload. Leave empty to use the local
    | invoice PDF URL. In production this should be the AEAT verification URL.
    */
    'qr' => [
        'base_url' => env('VERIFACTU_QR_BASE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rectificative numbering
    |--------------------------------------------------------------------------
    */
    'rectificative' => [
        'number_format' => env(
            'VERIFACTU_RECTIFICATIVE_NUMBER_FORMAT',
            '{{SERIES:R}}{{DELIMITER:-}}{{DATE_FORMAT:y}}{{SEQUENCE:6}}'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | AEAT endpoints (used by aeat_sandbox and aeat_production drivers)
    |--------------------------------------------------------------------------
    */
    'aeat' => [
        'sandbox_url'    => env('VERIFACTU_AEAT_SANDBOX_URL'),
        'production_url' => env('VERIFACTU_AEAT_PRODUCTION_URL'),
        'certificate_path'     => env('VERIFACTU_CERT_PATH'),
        'certificate_password' => env('VERIFACTU_CERT_PASSWORD'),
        // AEAT pre-production (prewww) uses a self-signed CA not in the default bundle.
        // Set to false for sandbox only; production always verifies.
        'sandbox_verify_ssl'   => env('VERIFACTU_AEAT_SANDBOX_VERIFY_SSL', false),
    ],

];
