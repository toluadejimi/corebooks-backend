<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessRole;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Location;
use App\Services\BusinessCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class BusinessController extends Controller
{
    public function __construct(
        private readonly BusinessCreator $businessCreator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $businesses = $request->user()
            ->businesses()
            ->with('subscriptionPlan')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $businesses->map(fn (Business $b) => [
                ...$this->businessSummary($b),
                'role' => BusinessRole::normalize($b->pivot->role)->value,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subscription_plan_slug' => ['nullable', 'string', 'exists:subscription_plans,slug'],
        ]);

        $business = $this->businessCreator->create(
            $request->user(),
            $data['name'],
            $data['subscription_plan_slug'] ?? null,
        );

        return response()->json([
            'data' => $this->businessSummary($business->load('subscriptionPlan')),
        ], 201);
    }

    public function show(Request $request, Business $business): JsonResponse
    {
        $business->load(['locations' => fn ($q) => $q->orderByDesc('is_default'), 'subscriptionPlan']);

        $pivot = $request->user()->businesses()->where('businesses.id', $business->id)->first()?->pivot;

        return response()->json([
            'data' => $this->businessDetail($business, $pivot),
        ]);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['sometimes', 'string', 'size:2'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'default_vat_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:500'],
            'public_shop_enabled' => ['sometimes', 'boolean'],
            'public_shop_slug' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('businesses', 'public_shop_slug')->ignore($business->id),
            ],
        ]);

        if (array_key_exists('public_shop_slug', $data) && ($data['public_shop_slug'] === '' || $data['public_shop_slug'] === null)) {
            $data['public_shop_slug'] = null;
        }

        $hadReceiptFooter = array_key_exists('receipt_footer', $data);
        $footer = Arr::pull($data, 'receipt_footer');
        if ($hadReceiptFooter) {
            $settings = $business->settings ?? [];
            if ($footer === null || $footer === '') {
                unset($settings['receipt_footer']);
            } else {
                $settings['receipt_footer'] = $footer;
            }
            $business->settings = $settings;
        }

        $business->fill($data);
        $business->version = (int) $business->version + 1;
        $business->save();

        $pivot = $request->user()->businesses()->where('businesses.id', $business->id)->first()?->pivot;
        $business->load(['locations' => fn ($q) => $q->orderByDesc('is_default'), 'subscriptionPlan']);

        return response()->json([
            'data' => $this->businessDetail($business, $pivot),
        ]);
    }

    private function businessDetail(Business $business, ?object $pivot): array
    {
        return [
            'uuid' => $business->uuid,
            'name' => $business->name,
            'logo_url' => $business->logo_url,
            'phone' => $business->phone,
            'address_line1' => $business->address_line1,
            'address_line2' => $business->address_line2,
            'city' => $business->city,
            'state' => $business->state,
            'country' => $business->country ?? 'NG',
            'currency' => $business->currency,
            'default_vat_rate' => (float) $business->default_vat_rate,
            'tax_id' => $business->tax_id,
            'receipt_footer' => data_get($business->settings, 'receipt_footer'),
            'public_shop_enabled' => (bool) $business->public_shop_enabled,
            'public_shop_slug' => $business->public_shop_slug,
            'public_shop_url' => $business->public_shop_enabled ? url('/shop/'.$business->uuid) : null,
            'public_shop_short_url' => ($business->public_shop_enabled && filled($business->public_shop_slug))
                ? url('/s/'.$business->public_shop_slug)
                : null,
            'my_role' => $pivot ? BusinessRole::normalize($pivot->role)->value : null,
            'locations' => $business->locations->map(fn (Location $l) => [
                'uuid' => $l->uuid,
                'name' => $l->name,
                'is_default' => $l->is_default,
            ]),
            'subscription' => [
                'status' => $business->subscription_status,
                'trial_ends_at' => $business->subscription_trial_ends_at?->toIso8601String(),
                'current_period_end' => $business->subscription_current_period_end?->toIso8601String(),
                'plan' => $business->subscriptionPlan?->toApiArray(),
            ],
        ];
    }

    private function businessSummary(Business $b): array
    {
        $b->loadMissing('subscriptionPlan');

        return [
            'uuid' => $b->uuid,
            'name' => $b->name,
            'logo_url' => $b->logo_url,
            'currency' => $b->currency,
            'default_vat_rate' => (float) $b->default_vat_rate,
            'country' => $b->country ?? 'NG',
            'subscription' => [
                'status' => $b->subscription_status,
                'trial_ends_at' => $b->subscription_trial_ends_at?->toIso8601String(),
                'current_period_end' => $b->subscription_current_period_end?->toIso8601String(),
                'plan' => $b->subscriptionPlan?->toApiArray(),
            ],
        ];
    }
}
