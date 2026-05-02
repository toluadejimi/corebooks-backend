<?php

namespace App\Services;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamMemberService
{
    public function actorRole(User $actor, Business $business): BusinessRole
    {
        $pivot = $actor->businesses()->where('businesses.id', $business->id)->first()?->pivot;
        if ($pivot === null) {
            abort(403);
        }

        return BusinessRole::normalize($pivot->role);
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, role: string, location_uuid?: string|null}  $data
     */
    public function invite(Business $business, User $actor, array $data): User
    {
        $actorRole = $this->actorRole($actor, $business);
        $assignable = BusinessRole::assignableBy($actorRole);

        $validated = validator($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in($assignable)],
            'location_uuid' => ['nullable', 'uuid'],
        ])->validate();

        $validated['email'] = Str::lower(trim($validated['email']));

        $targetRole = BusinessRole::normalize($validated['role']);
        $locationId = $this->resolveLocationIdForInvite($business, $targetRole, $validated['location_uuid'] ?? null);

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$validated['email']])->first();
        if ($existing === null) {
            if (empty($validated['password'])) {
                throw ValidationException::withMessages([
                    'password' => ['Password is required when creating a new user.'],
                ]);
            }
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
        } else {
            if ($existing->businesses()->where('businesses.id', $business->id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['This user is already a member of the business.'],
                ]);
            }
            $user = $existing;
        }

        $business->users()->attach($user->id, [
            'role' => $targetRole->value,
            'location_id' => $locationId,
        ]);

        return $user->fresh();
    }

    /**
     * @param  array{role?: string, location_uuid?: string|null}  $data
     */
    public function updateMember(Business $business, User $actor, User $target, array $data): void
    {
        if (! $target->businesses()->where('businesses.id', $business->id)->exists()) {
            abort(404);
        }

        $actorRole = $this->actorRole($actor, $business);
        $assignable = BusinessRole::assignableBy($actorRole);

        $validated = validator($data, [
            'role' => ['nullable', 'string', Rule::in($assignable)],
            'location_uuid' => ['nullable', 'uuid'],
        ])->validate();

        if (! isset($validated['role']) && ! array_key_exists('location_uuid', $data)) {
            throw ValidationException::withMessages([
                'role' => ['Provide role and/or location_uuid.'],
            ]);
        }

        $current = BusinessRole::normalize(
            $target->businesses()->where('businesses.id', $business->id)->first()->pivot->role
        );

        $newRole = isset($validated['role'])
            ? BusinessRole::normalize($validated['role'])
            : $current;

        if (isset($validated['role'])) {
            if ($actorRole === BusinessRole::Manager) {
                if ($current === BusinessRole::Owner) {
                    abort(403, 'Managers cannot change an owner.');
                }
                if ($newRole === BusinessRole::Owner) {
                    abort(403, 'Managers cannot assign the owner role.');
                }
            }
        }

        $locationId = $target->businesses()->where('businesses.id', $business->id)->first()->pivot->location_id;

        if (array_key_exists('location_uuid', $data)) {
            if ($newRole === BusinessRole::Sales) {
                $locationId = $this->resolveLocationIdForInvite($business, $newRole, $data['location_uuid'] ?? null);
            } else {
                $locationId = null;
            }
        } elseif ($newRole !== BusinessRole::Sales && isset($validated['role'])) {
            $locationId = null;
        }

        if ($newRole === BusinessRole::Sales && $locationId === null) {
            throw ValidationException::withMessages([
                'location_uuid' => ['Sales members must have a branch assigned.'],
            ]);
        }

        $pivot = [
            'role' => $newRole->value,
            'location_id' => $locationId,
        ];

        $business->users()->updateExistingPivot($target->id, $pivot);
    }

    public function updateMemberRole(Business $business, User $actor, User $target, string $newRole): void
    {
        $this->updateMember($business, $actor, $target, ['role' => $newRole]);
    }

    private function resolveLocationIdForInvite(Business $business, BusinessRole $role, ?string $locationUuid): ?int
    {
        if ($role !== BusinessRole::Sales) {
            return null;
        }

        if ($locationUuid === null || $locationUuid === '') {
            throw ValidationException::withMessages([
                'location_uuid' => ['Sales team members must be assigned to a branch.'],
            ]);
        }

        $id = $business->locations()->where('uuid', $locationUuid)->value('id');
        if ($id === null) {
            throw ValidationException::withMessages([
                'location_uuid' => ['Invalid branch for this business.'],
            ]);
        }

        return (int) $id;
    }

    public function removeMember(Business $business, User $actor, User $target): void
    {
        if (! $target->businesses()->where('businesses.id', $business->id)->exists()) {
            abort(404);
        }

        $actorRole = $this->actorRole($actor, $business);
        $current = BusinessRole::normalize(
            $target->businesses()->where('businesses.id', $business->id)->first()->pivot->role
        );

        if ($actorRole === BusinessRole::Manager) {
            if ($current !== BusinessRole::Sales) {
                abort(403, 'Managers can only remove sales team members.');
            }
        }

        if ($current === BusinessRole::Owner) {
            $ownerCount = $business->users()
                ->wherePivot('role', BusinessRole::Owner->value)
                ->count();
            if ($ownerCount <= 1) {
                abort(422, 'Cannot remove the last owner from the business.');
            }
        }

        if ($target->id === $actor->id) {
            abort(422, 'You cannot remove yourself from the business here.');
        }

        $business->users()->detach($target->id);
    }
}
