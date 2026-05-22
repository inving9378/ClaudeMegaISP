<?php

return [
    'api_url'          => env('WHATSAPP_API_URL', 'http://localhost/evolution'),
    'api_key'          => env('WHATSAPP_API_KEY', ''),
    'default_instance' => env('WHATSAPP_DEFAULT_INSTANCE', 'meganet'),
    'webhook_base_url' => env('WHATSAPP_WEBHOOK_BASE_URL', 'http://localhost'),
    'fake'             => env('WHATSAPP_FAKE', false),
];
