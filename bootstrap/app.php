<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetCurrentWorkspace::class,
        ]);

        $middleware->preventRequestForgery(except: [
            'webhooks/*',
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            // CSRF expired session
            if ($response->getStatusCode() === 419) {
                return back()->with('error', 'انتهت صلاحية الجلسة، يرجى المحاولة مرة أخرى.');
            }

            // For Inertia GET visits that result in 403, 404, 500, or 503 outside local/testing
            if (! app()->environment(['local', 'testing'])
                && in_array($response->getStatusCode(), [403, 404, 500, 503], true)
                && $request->header('X-Inertia')
            ) {
                return Inertia::render('Error', [
                    'status' => $response->getStatusCode(),
                    'message' => $exception->getMessage(),
                ])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
