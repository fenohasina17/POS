<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Middleware\SetActivePointOfSale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(append: [
            SetActivePointOfSale::class,
        ]);

        $middleware->throttleApi(limiter: 'api');
    })
    ->booted(function () {
        // Limite globale API : 120 req/min par IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Limite stricte auth : 5 tentatives/min par IP
        // Bloque le brute-force et le credential stuffing
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Trop de tentatives. Réessayez dans 60 secondes.',
                    ], 429);
                });
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
