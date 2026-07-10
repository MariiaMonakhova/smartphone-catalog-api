<?php

use App\Exceptions\ExchangeRateUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // A currency conversion was requested but NBU rates are unavailable
        // (and nothing usable is cached) — this is a transient upstream outage.
        $exceptions->render(function (ExchangeRateUnavailableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        });
    })->create();
