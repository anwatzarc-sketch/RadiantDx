<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Support\DatabaseUnavailable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => CheckPermission::class,
            'active' => EnsureUserIsActive::class,
            'password.changed' => EnsurePasswordIsChanged::class,
        ]);

        $middleware->redirectGuestsTo(fn (): string => route('login'));

        /*
         * TLS is terminated by the hosting panel's reverse proxy, which then
         * speaks plain HTTP to PHP. Without trusting it, every request looks
         * insecure from in here: isSecure() is false, and asset() and route()
         * emit http:// links into a page the browser loaded over https, which
         * it then refuses to load as mixed content.
         *
         * Trusting every proxy is safe only because nothing can reach PHP
         * except through that proxy — the application is not exposed on a port
         * of its own. On a host where that stops being true, name the proxy
         * addresses here instead.
         *
         * Only the four forwarded headers that are actually used are honoured;
         * leaving the set at Symfony's default would additionally trust
         * X-Forwarded-Prefix, which rewrites generated paths.
         */
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * A database that cannot be reached is answered with a page rather
         * than a stack trace.
         *
         * With SESSION_DRIVER=database the failure surfaces inside
         * StartSession, before the request is routed, so it takes out every
         * page at once — sign-in included. The debug renderer has nothing
         * useful to show for it either: only the frames of a session handler
         * that asked an unreachable server a perfectly reasonable question.
         *
         * Narrow on purpose. DatabaseUnavailable::caused() returns false for
         * a statement the server answered and refused, and returning null
         * here leaves those to render exactly as they did before, stack trace
         * and all. This is not a blanket catch for QueryException.
         *
         * The exception is still reported. Laravel logs it before render is
         * called, and the log channel is file-based, so the operational
         * record survives the outage that produced it.
         */
        $exceptions->render(function (Throwable $exception, Request $request): ?Response {
            if (! DatabaseUnavailable::caused($exception)) {
                return null;
            }

            return DatabaseUnavailable::response($request, $exception);
        });
    })->create();
