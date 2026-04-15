<?php

return [
    'enabled' => env('VERIFACTU_ENABLED', true),
    'mode' => env('VERIFACTU_MODE', 'shadow'),
    'issue_on_send' => env('VERIFACTU_ISSUE_ON_SEND', true),
    'submission_enabled' => env('VERIFACTU_SUBMISSION_ENABLED', false),
    'submission_driver' => env('VERIFACTU_SUBMISSION_DRIVER', 'stub'),
    'software' => [
        'name' => env('VERIFACTU_SOFTWARE_NAME', 'Crater VERI*FACTU Integration'),
        'version' => env('VERIFACTU_SOFTWARE_VERSION', '0.1.0'),
        'vendor_name' => env('VERIFACTU_VENDOR_NAME', 'Local Integration'),
        'vendor_tax_id' => env('VERIFACTU_VENDOR_TAX_ID'),
    ],
    'qr' => [
        'base_url' => env('VERIFACTU_QR_BASE_URL'),
    ],
    'rectificative' => [
        'number_format' => env(
            'VERIFACTU_RECTIFICATIVE_NUMBER_FORMAT',
            '{{SERIES:R}}{{DELIMITER:-}}{{DATE_FORMAT:y}}{{SEQUENCE:6}}'
        ),
    ],
];
