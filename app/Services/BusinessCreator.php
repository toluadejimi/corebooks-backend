<?php

namespace App\Services;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Location;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Str;

class BusinessCreator
{
    public function create(User $owner, string $name, ?string $planSlug = null): Business
    {
        $planQuery = SubscriptionPlan::query()->where('is_active', true);
        if ($planSlug !== null && $planSlug !== '') {
            $planQuery->where('slug', $planSlug);
        }
        /** @var SubscriptionPlan $plan */
        $plan = $planQuery->orderBy('sort_order')->firstOrFail();

        $business = Business::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'country' => 'NG',
            'subscription_plan_id' => $plan->id,
            'subscription_status' => 'trialing',
            'subscription_trial_ends_at' => now()->addDays(14),
            'subscription_current_period_end' => null,
        ]);

        $business->users()->attach($owner->id, ['role' => BusinessRole::Owner->value]);

        Location::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Main',
            'is_default' => true,
        ]);

        return $business->fresh(['subscriptionPlan']);
    }
}
