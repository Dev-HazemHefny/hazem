<?php

namespace App\Http\Middleware;

use App\Exceptions\ForbiddenException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCronSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.cron.secret');
        $header = $request->header('X-Cron-Secret');

        if (! $secret || ! hash_equals($secret, (string) $header)) {
            throw new ForbiddenException('Invalid cron secret.');
        }

        return $next($request);
    }
}
