<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'viewing.session' => \App\Http\Middleware\ValidateViewingSession::class,
            'rate.limit.pin' => \App\Http\Middleware\RateLimitPinAttempts::class,
            'account.approved' => \App\Http\Middleware\EnsureAccountApproved::class,
        ]);

        // Laravel 12 uses framework defaults for the web group; wire app middleware
        // so CSRF exceptions in App\Http\Middleware\VerifyCsrfToken actually apply.
        // Without this, long-lived gallery pages send stale _token values and get 419
        // on admin/verify-password even though that path is documented as excluded.
        $middleware->web(
            append: [
                \App\Http\Middleware\SetLocale::class,
            ],
            replace: [
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
