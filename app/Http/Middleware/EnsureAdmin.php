<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Exceptions\ForbiddenException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== UserRole::Admin) {
            throw new ForbiddenException('Admin access required.');
        }

        return $next($request);
    }
}
