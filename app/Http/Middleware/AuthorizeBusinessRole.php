<?php

namespace App\Http\Middleware;

use App\Enums\BusinessRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeBusinessRole
{
    /**
     * Require membership role at least the given role (e.g. manager => manager or owner).
     *
     * @param  string  ...$roles  First argument from Laravel is the minimum role string.
     */
    public function handle(Request $request, Closure $next, string $minimum): Response
    {
        $required = match (strtolower($minimum)) {
            'owner' => BusinessRole::Owner,
            'manager' => BusinessRole::Manager,
            'sales' => BusinessRole::Sales,
            default => abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Invalid role gate.'),
        };

        $actual = $request->attributes->get('business_role');
        if (! $actual instanceof BusinessRole) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! $actual->atLeast($required)) {
            abort(Response::HTTP_FORBIDDEN, 'Insufficient permissions for this action.');
        }

        return $next($request);
    }
}
