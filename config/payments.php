<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active payment provider
    |--------------------------------------------------------------------------
    |
    | Supported: mock, thawani
    | Real providers are added by implementing PaymentGatewayInterface and
    | registering them in PaymentGatewayResolver.
    |
    */
    'provider' => env('PAYMENT_PROVIDER', 'mock'),

    'mock' => [
        'webhook_secret' => env('MOCK_PAYMENT_WEBHOOK_SECRET'),
        'signature_header' => 'X-Mock-Signature',
    ],

    'thawani' => [
        'secret_key' => env('THAWANI_SECRET_KEY'),
        'publishable_key' => env('THAWANI_PUBLISHABLE_KEY'),
        'webhook_secret' => env('THAWANI_WEBHOOK_SECRET'),
        'base_url' => env('THAWANI_BASE_URL', 'https://uatcheckout.thawani.om/api/v1'),
        'checkout_base_url' => env('THAWANI_CHECKOUT_BASE_URL', 'https://uatcheckout.thawani.om'),
        'success_url' => env('THAWANI_SUCCESS_URL'),
        'cancel_url' => env('THAWANI_CANCEL_URL'),
        'expiry_minutes' => (int) env('THAWANI_EXPIRY_MINUTES', 30),
        'signature_header' => 'thawani-signature',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile payment return deep links
    |--------------------------------------------------------------------------
    |
    | After Thawani redirects to the backend success/cancel routes, the app
    | forwards the customer to these custom-scheme URLs. Webhooks remain the
    | only trusted source for payment status updates.
    |
    */
    'mobile' => [
        'payment_success_url' => env('MOBILE_PAYMENT_SUCCESS_URL', 'torinomodastyle://payment/success'),
        'payment_cancel_url' => env('MOBILE_PAYMENT_CANCEL_URL', 'torinomodastyle://payment/cancel'),
    ],

];
