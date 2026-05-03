<?php

return [

    /*
    | Comma-separated emails in .env (SALESAPP_PLATFORM_ADMIN_EMAILS) that may manage
    | subscription plans and review loan applications from the portfolio admin area.
    */
    'platform_admin_emails' => array_values(array_filter(array_map(
        static fn (string $e): string => strtolower(trim($e)),
        explode(',', (string) env('SALESAPP_PLATFORM_ADMIN_EMAILS', ''))
    ))),

    /*
    | When true, platform admins may run `php artisan migrate --force` from the portfolio
    | dashboard (for hosts without SSH). Set SALESAPP_ALLOW_WEB_MIGRATIONS=false to disable.
    */
    'allow_web_migrations' => filter_var(env('SALESAPP_ALLOW_WEB_MIGRATIONS', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Shown in the mobile app when Paystack is not configured (offline / manual payment).
    */
    'offline_payment_instructions' => (string) env(
        'SALESAPP_OFFLINE_PAYMENT_INSTRUCTIONS',
        'Complete payment via your agreed channel. Your workspace activates after we confirm receipt (usually within 1 business day).'
    ),

];
