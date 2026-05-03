<?php

namespace App\Services;

use App\Models\Business;

class SubscriptionAccess
{
    public function allowsAppAccess(Business $business): bool
    {
        $business->loadMissing('subscriptionPlan');

        return match ($business->subscription_status) {
            'active' => $this->activePeriodValid($business),
            'trialing' => $business->subscription_trial_ends_at !== null
                && $business->subscription_trial_ends_at->isFuture(),
            default => false,
        };
    }

    private function activePeriodValid(Business $business): bool
    {
        if ($business->subscription_current_period_end === null) {
            return true;
        }

        return $business->subscription_current_period_end->isFuture();
    }

    public function allowsLoanFeature(Business $business): bool
    {
        $business->loadMissing('subscriptionPlan');

        return (bool) ($business->subscriptionPlan?->feature_business_loan);
    }
}
