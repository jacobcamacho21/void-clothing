<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates TLS at its load balancer and forwards requests
        // over plain HTTP with X-Forwarded-* headers. Without this, Laravel
        // thinks every request is http://, which breaks signed URLs (email
        // verification, password reset) since the scheme is part of the
        // signature hash. '*' is safe here since Render is the only thing
        // that can reach this container directly.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // Shoppers and staff sign in through different guards; send each one
        // back to the login page that belongs to the area they asked for.
        $middleware->redirectGuestsTo(function (Request $request) {
            return $request->is('shop/account*') || $request->is('shop/checkout*')
                ? route('shop.login')
                : route('staff.login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            return $request->is('shop/*') ? route('shop.home') : route('admin.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
