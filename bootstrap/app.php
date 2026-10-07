<?php

use App\Http\Middleware\ForceJsonOnApiDomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        // /up без домена: healthcheck может ходить по IP контейнера.
        health: '/up',
        then: function (): void {
            // Один контейнер, два домена — разделяем роутингом.
            // Web — на app.domain, API — на app.api_domain без префикса "api"
            // (api.example.com/hello вместо example.com/api/hello).
            // Только config(), не env(): после config:cache env() вернёт null,
            // а Route::domain(null) молча матчит любой хост.
            Route::middleware('web')
                ->domain(config('app.domain'))
                ->group(base_path('routes/web.php'));

            Route::middleware('api')
                ->domain(config('app.api_domain'))
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->prepend(ForceJsonOnApiDomain::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->getHost() === config('app.api_domain')
                || $request->expectsJson(),
        );
    })->create();
