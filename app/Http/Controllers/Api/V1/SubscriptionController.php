<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Services\PaystackSubscriptionService;
use App\Services\SubscriptionAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PaystackSubscriptionService $paystack,
        private readonly SubscriptionAccess $subscriptionAccess,
    ) {}

    public function show(Request $request, Business $business): JsonResponse
    {
        $business->loadMissing('subscriptionPlan');

        return response()->json([
            'data' => [
                'access' => $this->subscriptionAccess->allowsAppAccess($business),
                'status' => $business->subscription_status,
                'trial_ends_at' => $business->subscription_trial_ends_at?->toIso8601String(),
                'current_period_end' => $business->subscription_current_period_end?->toIso8601String(),
                'plan' => $business->subscriptionPlan?->toApiArray(),
                'loan_feature' => $this->subscriptionAccess->allowsLoanFeature($business),
            ],
        ]);
    }

    public function initializePayment(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'plan_slug' => ['nullable', 'string', 'exists:subscription_plans,slug'],
        ]);

        $plan = isset($data['plan_slug'])
            ? SubscriptionPlan::query()->where('slug', $data['plan_slug'])->where('is_active', true)->firstOrFail()
            : $business->subscriptionPlan ?? SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();

        $business->update(['subscription_plan_id' => $plan->id]);

        $checkout = $this->paystack->initializeCheckout($business, $request->user(), $plan);

        return response()->json([
            'data' => $checkout,
        ]);
    }
}
