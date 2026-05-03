<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Services\BusinessTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BusinessSubscriptionPlatformWebController extends Controller
{
    public function __construct(
        private readonly BusinessTokenService $tokens,
    ) {}
    public function index(Request $request): View
    {
        $businesses = Business::query()
            ->with('subscriptionPlan')
            ->orderBy('name')
            ->paginate(40);

        return view('admin.platform.business-subscriptions.index', [
            'user' => $request->user(),
            'businesses' => $businesses,
        ]);
    }

    public function edit(Request $request, Business $business): View
    {
        $business->load('subscriptionPlan');
        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.platform.business-subscriptions.edit', [
            'user' => $request->user(),
            'business' => $business,
            'plans' => $plans,
        ]);
    }

    public function update(Request $request, Business $business): RedirectResponse
    {
        if ($request->input('subscription_plan_id') === '' || $request->input('subscription_plan_id') === null) {
            $request->merge(['subscription_plan_id' => null]);
        }
        foreach (['subscription_trial_ends_at', 'subscription_current_period_end'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $status = $request->input('subscription_status');

        $planRules = $status === 'inactive'
            ? ['nullable', 'integer', 'exists:subscription_plans,id']
            : ['required', 'integer', 'exists:subscription_plans,id'];

        $data = $request->validate([
            'subscription_plan_id' => $planRules,
            'subscription_status' => ['required', 'string', 'in:inactive,active,trialing'],
            'subscription_trial_ends_at' => ['nullable', 'date'],
            'subscription_current_period_end' => ['nullable', 'date'],
            'token_credit_adjust' => ['nullable', 'integer', 'between:-1000000,1000000'],
        ]);

        if ($data['subscription_status'] === 'trialing') {
            if (empty($data['subscription_trial_ends_at'])) {
                return back()->withErrors(['subscription_trial_ends_at' => 'Trial end date is required when status is Trialing.'])->withInput();
            }
            $trialEnd = Carbon::parse($data['subscription_trial_ends_at'], config('app.timezone'));
            if (! $trialEnd->isFuture()) {
                return back()->withErrors(['subscription_trial_ends_at' => 'Trial end must be in the future.'])->withInput();
            }
        }

        if ($data['subscription_status'] === 'active' && ! empty($data['subscription_current_period_end'])) {
            $periodEnd = Carbon::parse($data['subscription_current_period_end'], config('app.timezone'));
            if (! $periodEnd->isFuture()) {
                return back()->withErrors(['subscription_current_period_end' => 'Billing period end must be in the future, or leave empty for no fixed end.'])->withInput();
            }
        }

        $rawPlan = $data['subscription_plan_id'] ?? null;
        $planId = $rawPlan !== null ? (int) $rawPlan : null;
        if ($data['subscription_status'] !== 'inactive' && $planId === null) {
            return back()->withErrors(['subscription_plan_id' => 'Choose a plan for active or trialing workspaces.'])->withInput();
        }

        $trialAt = null;
        if ($data['subscription_status'] === 'trialing' && ! empty($data['subscription_trial_ends_at'])) {
            $trialAt = Carbon::parse($data['subscription_trial_ends_at'], config('app.timezone'));
        }

        $periodEnd = null;
        if ($data['subscription_status'] === 'active' && ! empty($data['subscription_current_period_end'])) {
            $periodEnd = Carbon::parse($data['subscription_current_period_end'], config('app.timezone'));
        }

        $business->update([
            'subscription_plan_id' => $planId,
            'subscription_status' => $data['subscription_status'],
            'subscription_trial_ends_at' => $data['subscription_status'] === 'trialing' ? $trialAt : null,
            'subscription_current_period_end' => $data['subscription_status'] === 'active' ? $periodEnd : null,
        ]);

        $adjust = (int) ($data['token_credit_adjust'] ?? 0);
        if ($adjust !== 0) {
            if ($adjust > 0) {
                $this->tokens->credit($business, $request->user(), 'admin_adjust', $adjust, ['note' => 'subscription screen']);
            } else {
                $amount = abs($adjust);
                $business->refresh();
                if ((int) $business->token_balance < $amount) {
                    return back()->withErrors(['token_credit_adjust' => 'Cannot remove more tokens than this business has.'])->withInput();
                }
                $this->tokens->debit($business, $request->user(), 'admin_deduct', $amount, ['note' => 'subscription screen deduction']);
            }
        }

        return redirect()->route('admin.platform.business-subscriptions.index')->with('status', 'Subscription updated for '.$business->name.'.');
    }
}
