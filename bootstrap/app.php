<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyFrontendOrigin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'frontend' => VerifyFrontendOrigin::class,
        ]);
        $middleware->append(SecurityHeaders::class);

        // Optional: lock the app to its own domain(s) — TRUSTED_HOSTS=admin.example.com,api.example.com
        if ($hosts = array_filter(array_map('trim', explode(',', (string) env('TRUSTED_HOSTS', ''))))) {
            $middleware->trustHosts(at: array_map(fn ($h) => '^'.preg_quote($h, '#').'$', $hosts));
        }
        // Behind a reverse proxy / load balancer / Cloudflare set TRUSTED_PROXIES=* (or a comma list of IPs),
        // otherwise every visitor looks like the proxy's IP and the rate limits are shared by everyone.
        if ($proxies = trim((string) env('TRUSTED_PROXIES', ''))) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Unknown / blocked API URLs answer with an empty body — nothing to read, nothing to learn.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response('', 404);
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
