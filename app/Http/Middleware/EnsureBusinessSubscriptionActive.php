<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Services\SubscriptionAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessSubscriptionActive
{
    public function __construct(
        private readonly SubscriptionAccess $subscriptionAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->route('business');
        if (! $business instanceof Business) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! $this->subscriptionAccess->allowsAppAccess($business)) {
            return response()->json([
                'message' => 'This workspace needs an active subscription or trial.',
                'code' => 'subscription_required',
                'subscription' => $this->subscriptionPayload($business),
            ], 402);
        }

        return $next($request);
    }

    private function subscriptionPayload(Business $business): array
    {
        $business->loadMissing('subscriptionPlan');

        return [
            'status' => $business->subscription_status,
            'trial_ends_at' => $business->subscription_trial_ends_at?->toIso8601String(),
            'current_period_end' => $business->subscription_current_period_end?->toIso8601String(),
            'plan' => $business->subscriptionPlan?->toApiArray(),
        ];
    }
}
