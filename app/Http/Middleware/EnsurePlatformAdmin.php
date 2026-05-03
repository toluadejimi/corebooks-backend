<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $emails = config('salesapp.platform_admin_emails', []);
        if ($emails === [] || ! in_array(strtolower($user->email), $emails, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Platform admin only.');
        }

        return $next($request);
    }
}
