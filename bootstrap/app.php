<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\AddRequestId;
use App\Http\Middleware\BootstrapTenantFromToken;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\VerifyCronSecret;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'bootstrap.tenant' => BootstrapTenantFromToken::class,
            'tenant' => EnsureTenantContext::class,
            'admin' => EnsureAdmin::class,
            'cron.secret' => VerifyCronSecret::class,
        ]);

        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            BootstrapTenantFromToken::class,
        );

        $middleware->api(prepend: [
            AddRequestId::class,
        ]);

        $middleware->web(prepend: [
            AddRequestId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('web/v1/*') || $request->expectsJson(),
        );

        $exceptions->render(function (DomainException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->is('web/v1/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->httpStatus, $e->details);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->is('web/v1/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(
                'VALIDATION_ERROR',
                'The given data was invalid.',
                422,
                $e->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->is('web/v1/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error('UNAUTHENTICATED', $e->getMessage() ?: 'Unauthenticated.', 401);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->is('web/v1/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error('NOT_FOUND', 'Resource not found.', 404);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->is('web/v1/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error('RATE_LIMITED', 'Too many requests.', 429);
        });
    })->create();
