<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Location;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LocationController extends Controller
{
    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $loc = Location::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'is_default' => false,
            'version' => 1,
        ]);

        return response()->json(['data' => $this->locationRow($loc)], 201);
    }

    public function update(Request $request, Business $business, Location $location): JsonResponse
    {
        abort_if($location->business_id !== $business->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'make_default' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $location->name = $data['name'];
            $location->version = (int) $location->version + 1;
        }

        if (! empty($data['make_default'])) {
            Location::query()->where('business_id', $business->id)->update(['is_default' => false]);
            $location->is_default = true;
            $location->version = (int) $location->version + 1;
        }

        $location->save();

        return response()->json(['data' => $this->locationRow($location->fresh())]);
    }

    public function destroy(Business $business, Location $location): JsonResponse
    {
        abort_if($location->business_id !== $business->id, 404);

        if ($business->locations()->count() <= 1) {
            return response()->json([
                'message' => 'You must keep at least one branch.',
            ], 422);
        }

        if ($location->is_default) {
            return response()->json([
                'message' => 'Set another branch as default before deleting this one.',
            ], 422);
        }

        if (Sale::query()->where('business_id', $business->id)->where('location_id', $location->id)->exists()) {
            return response()->json([
                'message' => 'Cannot delete a branch that has historical sales.',
            ], 422);
        }

        if (ProductBatch::query()->where('business_id', $business->id)->where('location_id', $location->id)->where('qty', '>', 0)->exists()) {
            return response()->json([
                'message' => 'Transfer or sell remaining stock before deleting this branch.',
            ], 422);
        }

        if (PurchaseOrder::query()->where('business_id', $business->id)->where('location_id', $location->id)->exists()) {
            return response()->json([
                'message' => 'Cannot delete a branch referenced by purchase records.',
            ], 422);
        }

        $location->delete();

        return response()->json(['ok' => true]);
    }

    private function locationRow(Location $l): array
    {
        return [
            'uuid' => $l->uuid,
            'name' => $l->name,
            'is_default' => (bool) $l->is_default,
        ];
    }
}
