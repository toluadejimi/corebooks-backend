<?php

namespace App\Services;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Str;

class BusinessCreator
{
    public function create(User $owner, string $name): Business
    {
        $business = Business::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'country' => 'NG',
        ]);

        $business->users()->attach($owner->id, ['role' => BusinessRole::Owner->value]);

        Location::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Main',
            'is_default' => true,
        ]);

        return $business->fresh();
    }
}
