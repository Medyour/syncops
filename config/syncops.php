<?php

return [
    'webhooks' => [
        'max_payload_bytes' => (int) env('SYNCOPS_WEBHOOK_MAX_PAYLOAD_BYTES', 65536),
        'rate_limit_per_minute' => (int) env('SYNCOPS_WEBHOOK_RATE_LIMIT_PER_MINUTE', 60),
    ],

    'connectors' => [
        'billing-demo' => [
            'secret' => env('SYNCOPS_BILLING_DEMO_SECRET'),
        ],
        'commerce-demo' => [
            'secret' => env('SYNCOPS_COMMERCE_DEMO_SECRET'),
        ],
        'crm-demo' => [
            'secret' => env('SYNCOPS_CRM_DEMO_SECRET'),
        ],
    ],
];
