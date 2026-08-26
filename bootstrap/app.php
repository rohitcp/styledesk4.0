<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * StyleDesk resolves the tenant two different ways.
         *
         *   tenant.subdomain  public booking on *.styledesk.app, from the host
         *   tenant.user       central app on styledesk.app, from users.tenant_id
         *
         * `tenant.central-only` guards routes that must never be reachable on
         * a tenant subdomain (billing, account deletion, the admin console).
         */
        $middleware->alias([
            'tenant.subdomain' => InitializeTenancyBySubdomain::class,
            'tenant.user' => \App\Http\Middleware\InitializeTenancyFromUser::class,
            'tenant.central-only' => PreventAccessFromCentralDomains::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
