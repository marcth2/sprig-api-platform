<?php

use App\Http\Middleware\ApiVersion;
use App\Http\Middleware\ForceJsonResponse;
use App\Shared\Data\ApiErrorData;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestsDuringMaintenance(except: [
            '/api/health',
            '/api/health/*',
        ]);

        $middleware->api(
            prepend: [
                ApiVersion::class,
                ForceJsonResponse::class,
            ],
            append: ['throttle:60,1'],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render callbacks are matched in registration order — specific types must be
        // registered before the generic \Throwable fallback, or the fallback wins every time.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(new ApiErrorData($e->getMessage(), 401), 401);
            }
        });

        $exceptions->render(function (InvalidArgumentException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(new ApiErrorData($e->getMessage(), 404), 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                /** @var array<string, array<int, string>> $errors */
                $errors = $e->errors();

                return response()->json(new ApiErrorData($e->getMessage(), 422, $errors), 422);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(new ApiErrorData('Server error', 500), 500);
            }
        });
    })->create();
