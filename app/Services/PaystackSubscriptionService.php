<?php

namespace App\Services;

use App\Models\Business;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackSubscriptionService
{
    public function secret(): ?string
    {
        $s = (string) config('paystack.secret_key', '');

        return $s !== '' ? $s : null;
    }

    /**
     * @return array{reference: string, authorization_url: ?string, offline: bool, message?: string}
     */
    public function initializeCheckout(Business $business, User $user, SubscriptionPlan $plan): array
    {
        $reference = 'CB_'.strtoupper(Str::random(16));

        $payment = SubscriptionPayment::query()->create([
            'business_id' => $business->id,
            'subscription_plan_id' => $plan->id,
            'user_id' => $user->id,
            'paystack_reference' => $reference,
            'status' => 'pending',
            'amount_kobo' => $plan->price_amount_kobo,
            'authorization_url' => null,
            'meta' => [
                'business_uuid' => $business->uuid,
            ],
        ]);

        $secret = $this->secret();
        if ($secret === null) {
            return [
                'reference' => $reference,
                'authorization_url' => null,
                'offline' => true,
                'message' => config('salesapp.offline_payment_instructions'),
                'payment_id' => $payment->id,
            ];
        }

        $callback = rtrim((string) config('app.url'), '/').'/paystack/subscription-return?reference='.$reference;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$secret,
            'Content-Type' => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email' => $user->email,
            'amount' => $plan->price_amount_kobo,
            'reference' => $reference,
            'currency' => 'NGN',
            'callback_url' => $callback,
            'metadata' => [
                'business_uuid' => $business->uuid,
                'payment_id' => $payment->id,
            ],
        ]);

        if (! $response->successful()) {
            $payment->update([
                'status' => 'failed',
                'meta' => array_merge($payment->meta ?? [], ['initialize_error' => $response->json()]),
            ]);

            return [
                'reference' => $reference,
                'authorization_url' => null,
                'offline' => true,
                'message' => 'Unable to start online payment. '.config('salesapp.offline_payment_instructions'),
                'payment_id' => $payment->id,
            ];
        }

        $data = $response->json('data') ?? [];
        $authUrl = isset($data['authorization_url']) ? (string) $data['authorization_url'] : null;
        $payment->update(['authorization_url' => $authUrl]);

        return [
            'reference' => $reference,
            'authorization_url' => $authUrl,
            'offline' => false,
            'payment_id' => $payment->id,
        ];
    }

    public function verifySignature(string $payload, ?string $signature): bool
    {
        $secret = $this->secret();
        if ($secret === null || $signature === null || $signature === '') {
            return false;
        }

        $computed = hash_hmac('sha512', $payload, $secret);

        return hash_equals($computed, $signature);
    }

    public function activateFromSuccessfulCharge(string $reference): bool
    {
        $payment = SubscriptionPayment::query()->where('paystack_reference', $reference)->first();
        if ($payment === null || $payment->status === 'success') {
            return false;
        }

        $business = $payment->business;
        $business->update([
            'subscription_plan_id' => $payment->subscription_plan_id,
            'subscription_status' => 'active',
            'subscription_trial_ends_at' => null,
            'subscription_current_period_end' => now()->addMonth(),
        ]);

        $payment->update([
            'status' => 'success',
            'paid_at' => now(),
        ]);

        return true;
    }

    public function verifyRemoteAndActivate(string $reference): bool
    {
        $secret = $this->secret();
        if ($secret === null) {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$secret,
        ])->get('https://api.paystack.co/transaction/verify/'.urlencode($reference));

        if (! $response->successful()) {
            return false;
        }

        if (($response->json('data.status') ?? '') !== 'success') {
            return false;
        }

        return $this->activateFromSuccessfulCharge($reference);
    }
}
