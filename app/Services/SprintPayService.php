<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SprintPay hosted card payment (merchant credentials from env).
 *
 * @see https://github.com/sprintpay/sprintpay-api-php-client
 */
final class SprintPayService
{
    /**
     * Request a hosted payment page URL. Returns null if disabled or on transport/API failure.
     *
     * @param  array<string, mixed>  $payload  SprintPay body (amount, currency, callback, etc.)
     */
    public function requestHostedCardUrl(array $payload): ?string
    {
        if (! config('sprintpay.enabled')) {
            return null;
        }

        $base = rtrim((string) config('sprintpay.base_url'), '/');
        if ($base === '') {
            return null;
        }

        $dt = (string) config('sprintpay.datetime_header');
        $auth = (string) config('sprintpay.authorization_header');
        if ($dt === '' || $auth === '') {
            Log::warning('SprintPay: missing datetime or authorization header in config.');

            return null;
        }

        $url = $base.'/payement/card/hosted/url';

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'datetime' => $dt,
                    'authorization' => $auth,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('SprintPay transport: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::warning('SprintPay HTTP '.$response->status().': '.$response->body());

            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        foreach (['paymentUrl', 'payment_url', 'url', 'hostedUrl', 'hosted_url'] as $key) {
            if (! empty($json[$key]) && is_string($json[$key])) {
                return $json[$key];
            }
        }

        if (! empty($json['data']['url']) && is_string($json['data']['url'])) {
            return $json['data']['url'];
        }

        Log::info('SprintPay: unexpected response shape', ['body' => $json]);

        return null;
    }
}
