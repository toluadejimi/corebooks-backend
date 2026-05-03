<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionPlanWebController extends Controller
{
    public function index(Request $request): View
    {
        $plans = SubscriptionPlan::query()->orderBy('sort_order')->get();

        return view('admin.platform.plans.index', [
            'user' => $request->user(),
            'plans' => $plans,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.platform.plans.form', [
            'user' => $request->user(),
            'plan' => new SubscriptionPlan([
                'billing_interval' => 'monthly',
                'max_records' => 5000,
                'sort_order' => 100,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        SubscriptionPlan::query()->create($data);

        return redirect()->route('admin.platform.plans.index')->with('status', 'Plan created.');
    }

    public function edit(Request $request, SubscriptionPlan $plan): View
    {
        return view('admin.platform.plans.form', [
            'user' => $request->user(),
            'plan' => $plan,
        ]);
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan->id));

        return redirect()->route('admin.platform.plans.index')->with('status', 'Plan updated.');
    }

    public function destroy(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->businesses()->exists()) {
            return back()->withErrors(['plan' => 'Detach businesses before deleting a plan.']);
        }
        $plan->delete();

        return redirect()->route('admin.platform.plans.index')->with('status', 'Plan deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'];
        if ($ignoreId !== null) {
            $slugRule[] = 'unique:subscription_plans,slug,'.$ignoreId;
        } else {
            $slugRule[] = 'unique:subscription_plans,slug';
        }

        $data = $request->validate([
            'slug' => $slugRule,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_ngn' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['required', 'string', 'in:monthly,yearly'],
            'max_records' => ['required', 'integer', 'min:1', 'max:10000000'],
            'features_text' => ['nullable', 'string', 'max:8000'],
            'feature_inventory' => ['sometimes', 'boolean'],
            'feature_accounting_reports' => ['sometimes', 'boolean'],
            'feature_tax_reports' => ['sometimes', 'boolean'],
            'feature_database_backup' => ['sometimes', 'boolean'],
            'feature_business_loan' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $features = [];
        if (! empty($data['features_text'])) {
            $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['features_text']))));
        }

        $priceKobo = (int) round((float) $data['price_ngn'] * 100);

        return [
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_amount_kobo' => $priceKobo,
            'billing_interval' => $data['billing_interval'],
            'max_records' => (int) $data['max_records'],
            'features' => $features,
            'feature_inventory' => $request->boolean('feature_inventory'),
            'feature_accounting_reports' => $request->boolean('feature_accounting_reports'),
            'feature_tax_reports' => $request->boolean('feature_tax_reports'),
            'feature_database_backup' => $request->boolean('feature_database_backup'),
            'feature_business_loan' => $request->boolean('feature_business_loan'),
            'sort_order' => (int) $data['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
