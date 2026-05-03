<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessRole;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use App\Services\BusinessCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly BusinessCreator $businessCreator,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email', ''))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'subscription_plan_slug' => ['nullable', 'string', 'exists:subscription_plans,slug'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        if (! empty($data['business_name'])) {
            $this->businessCreator->create(
                $user,
                $data['business_name'],
                $data['subscription_plan_slug'] ?? null,
            );
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email', ''))),
        ]);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->whereRaw('LOWER(email) = ?', [$data['email']])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    private function userPayload(User $user): array
    {
        $user->load(['businesses' => fn ($q) => $q->orderBy('name')->withPivot('location_id')->with('subscriptionPlan')]);

        return [
            'id' => $user->id,
            'uuid' => null,
            'name' => $user->name,
            'email' => $user->email,
            'businesses' => $user->businesses->map(fn (Business $b) => [
                'uuid' => $b->uuid,
                'name' => $b->name,
                'role' => BusinessRole::normalize($b->pivot->role)->value,
                'assigned_location_uuid' => $b->pivot->location_id
                    ? Location::query()->where('business_id', $b->id)->where('id', $b->pivot->location_id)->value('uuid')
                    : null,
                'subscription' => [
                    'status' => $b->subscription_status,
                    'trial_ends_at' => $b->subscription_trial_ends_at?->toIso8601String(),
                    'current_period_end' => $b->subscription_current_period_end?->toIso8601String(),
                    'plan' => $b->subscriptionPlan?->toApiArray(),
                ],
            ]),
        ];
    }
}
