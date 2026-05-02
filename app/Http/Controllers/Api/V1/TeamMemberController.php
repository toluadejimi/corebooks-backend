<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessRole;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Services\TeamMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    public function __construct(
        private readonly TeamMemberService $teamMembers,
    ) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $business->loadMissing('locations');
        $locById = $business->locations->keyBy('id');

        $members = $business->users()
            ->orderBy('name')
            ->get()
            ->map(function (User $u) use ($locById) {
                $lid = $u->pivot->location_id ?? null;

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => BusinessRole::normalize($u->pivot->role)->value,
                    'location_uuid' => $lid ? ($locById->get($lid)?->uuid) : null,
                    'location_name' => $lid ? ($locById->get($lid)?->name) : null,
                ];
            });

        return response()->json(['data' => $members]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string'],
            'location_uuid' => ['nullable', 'uuid'],
        ]);

        $user = $this->teamMembers->invite($business, $request->user(), $data);

        $business->loadMissing('locations');
        $locById = $business->locations->keyBy('id');
        $pivot = $user->businesses()->where('businesses.id', $business->id)->first()->pivot;
        $role = BusinessRole::normalize($pivot->role);
        $lid = $pivot->location_id ?? null;

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role->value,
                'location_uuid' => $lid ? ($locById->get($lid)?->uuid) : null,
                'location_name' => $lid ? ($locById->get($lid)?->name) : null,
            ],
        ], 201);
    }

    public function update(Request $request, Business $business, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['nullable', 'string'],
            'location_uuid' => ['nullable', 'uuid'],
        ]);

        $this->teamMembers->updateMember($business, $request->user(), $user, $data);

        $user->refresh();
        $business->loadMissing('locations');
        $locById = $business->locations->keyBy('id');
        $pivot = $user->businesses()->where('businesses.id', $business->id)->first()->pivot;
        $role = BusinessRole::normalize($pivot->role);
        $lid = $pivot->location_id ?? null;

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role->value,
                'location_uuid' => $lid ? ($locById->get($lid)?->uuid) : null,
                'location_name' => $lid ? ($locById->get($lid)?->name) : null,
            ],
        ]);
    }

    public function destroy(Request $request, Business $business, User $user): JsonResponse
    {
        $this->teamMembers->removeMember($business, $request->user(), $user);

        return response()->json(['ok' => true]);
    }
}
