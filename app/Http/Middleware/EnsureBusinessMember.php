<?php

namespace App\Http\Middleware;

use App\Enums\BusinessRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->route('business');
        $user = $request->user();

        if ($business === null || $user === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $pivot = $user->businesses()->where('businesses.id', $business->id)->first()?->pivot;
        if ($pivot === null) {
            abort(Response::HTTP_FORBIDDEN, 'Not a member of this business.');
        }

        $request->attributes->set('business_role', BusinessRole::normalize($pivot->role));

        return $next($request);
    }
}
