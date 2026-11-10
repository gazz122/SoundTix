<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceHttpsOnPublicTunnel;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectGatekeepersToScanner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
        then: function () {
            RateLimiter::for('api', function (Request $request) {
                return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
            });

            RateLimiter::for('auth', function (Request $request) {
                $target = $request->filled('email')
                    ? Str::lower($request->string('email'))
                    : $request->ip();

                return Limit::perMinute(5)->by($target.'|'.$request->ip());
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The dev tunnel (and any TLS-terminating proxy) forwards plain HTTP,
        // so trust its X-Forwarded-* headers or asset URLs come out as http://
        // and the browser blocks them on the https page.
        $middleware->trustProxies(at: '*');
        $middleware->throttleApi('api');
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'redirect.gatekeepers' => RedirectGatekeepersToScanner::class,
        ]);
        $middleware->web(append: [HandleInertiaRequests::class, ForceHttpsOnPublicTunnel::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
