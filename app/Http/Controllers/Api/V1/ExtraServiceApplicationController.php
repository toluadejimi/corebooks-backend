<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ExtraService;
use App\Models\ExtraServiceApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExtraServiceApplicationController extends Controller
{
    public function index(Request $request, Business $business): JsonResponse
    {
        $rows = ExtraServiceApplication::query()
            ->where('business_id', $business->id)
            ->with('extraService')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (ExtraServiceApplication $a) => $a->toApiArray())->values()->all(),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'extra_service_id' => ['required', 'integer', 'exists:extra_services,id'],
            'applicant_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $service = ExtraService::query()
            ->whereKey((int) $data['extra_service_id'])
            ->where('is_active', true)
            ->first();

        if ($service === null) {
            throw ValidationException::withMessages([
                'extra_service_id' => ['This service is not available.'],
            ]);
        }

        $pending = ExtraServiceApplication::query()
            ->where('business_id', $business->id)
            ->where('extra_service_id', $service->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'extra_service_id' => ['You already have a pending application for this service.'],
            ]);
        }

        $app = ExtraServiceApplication::query()->create([
            'uuid' => (string) Str::uuid(),
            'business_id' => $business->id,
            'extra_service_id' => $service->id,
            'status' => 'pending',
            'applicant_notes' => $data['applicant_notes'] ?? null,
        ]);

        return response()->json([
            'data' => $app->load('extraService')->toApiArray(),
        ], 201);
    }
}
