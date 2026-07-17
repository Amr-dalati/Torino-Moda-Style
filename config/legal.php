<?php

return [

    'company_name' => env('LEGAL_COMPANY_NAME', '[Company legal name — configure before release]'),

    'contact_email' => env('LEGAL_CONTACT_EMAIL', '[contact@example.com — configure before release]'),

    'contact_phone' => env('LEGAL_CONTACT_PHONE', '[+00000000000 — configure before release]'),

    'contact_address' => env('LEGAL_CONTACT_ADDRESS', '[Business address — configure before release]'),

    'last_updated' => env('LEGAL_LAST_UPDATED', '2026-07-13'),

    'supported_locales' => ['en', 'ar'],

    'default_locale' => 'en',

];
