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

class BusinessController extends Controller
{
    public function __construct(
        private readonly BusinessCreator $businessCreator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $businesses = $request->user()
            ->businesses()
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
        ]);

        $business = $this->businessCreator->create($request->user(), $data['name']);

        return response()->json([
            'data' => $this->businessSummary($business),
        ], 201);
    }

    public function show(Request $request, Business $business): JsonResponse
    {
        $business->load(['locations' => fn ($q) => $q->orderByDesc('is_default')]);

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
        ]);

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
        $business->load(['locations' => fn ($q) => $q->orderByDesc('is_default')]);

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
            'my_role' => $pivot ? BusinessRole::normalize($pivot->role)->value : null,
            'locations' => $business->locations->map(fn (Location $l) => [
                'uuid' => $l->uuid,
                'name' => $l->name,
                'is_default' => $l->is_default,
            ]),
        ];
    }

    private function businessSummary(Business $b): array
    {
        return [
            'uuid' => $b->uuid,
            'name' => $b->name,
            'logo_url' => $b->logo_url,
            'currency' => $b->currency,
            'default_vat_rate' => (float) $b->default_vat_rate,
            'country' => $b->country ?? 'NG',
        ];
    }
}
