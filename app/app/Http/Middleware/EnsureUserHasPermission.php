<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $hasPermission = collect(explode('|', $permission))
            ->contains(fn (string $permission) => $request->user()?->hasPermission($permission));

        abort_unless($hasPermission, 403);

        return $next($request);
    }
}
