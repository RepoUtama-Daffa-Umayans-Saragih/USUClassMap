<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Automation Gateway Configuration (USUClassMap Fotokopi)
    |--------------------------------------------------------------------------
    */

    'default' => env('WHATSAPP_DRIVER', 'sandbox'),

    'fallback_on_failure' => filter_var(env('WHATSAPP_FALLBACK_ON_FAILURE', true), FILTER_VALIDATE_BOOLEAN),

    'retention_days' => (int) env('FOTOCOPY_FILE_RETENTION_DAYS', 14),

    'drivers' => [
        'waha' => [
            'base_url' => rtrim((string) env('WHATSAPP_WAHA_BASE_URL', 'http://localhost:3000'), '/'),
            'session' => (string) env('WHATSAPP_WAHA_SESSION', 'default'),
            'api_key' => (string) env('WHATSAPP_WAHA_API_KEY', ''),
            'timeout' => 25,
        ],

        'fonnte' => [
            'endpoint' => 'https://api.fonnte.com/send',
            'token' => (string) env('WHATSAPP_FONNTE_TOKEN', ''),
            'timeout' => 25,
            'max_direct_bytes' => 15728640, // 15 MB cloud limit before signed-link fallback
        ],

        'sandbox' => [
            'label' => 'Sandbox Simulator & Local Audit Log',
        ],
    ],
];
