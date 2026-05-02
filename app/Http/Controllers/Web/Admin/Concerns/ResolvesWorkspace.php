<?php

namespace App\Http\Controllers\Web\Admin\Concerns;

use App\Enums\BusinessRole;
use App\Models\Business;
use Illuminate\Http\Request;

trait ResolvesWorkspace
{
    protected function workspace(Request $request, Business $business): array
    {
        /** @var BusinessRole $role */
        $role = $request->attributes->get('business_role');

        return [
            'business' => $business,
            'memberRole' => $role,
            'canManage' => $role->atLeast(BusinessRole::Manager),
            'user' => $request->user(),
        ];
    }
}
