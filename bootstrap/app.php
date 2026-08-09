<?php

use App\Http\Middleware\CheckPermissionMiddleware;
use App\Http\Middleware\CheckSessionLockMiddleware;
use App\Http\Middleware\CustomerMiddleware;
use App\Http\Middleware\GlobalLogger;
use App\Http\Middleware\JourneyLogger;
use App\Http\Middleware\RequireTotpMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UserMiddleware;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('admin')
                ->group(base_path('routes/admin.php'));
        },
    )
    // Registered explicitly (rather than via withRouting's `channels:` param) so
    // /broadcasting/auth runs behind our own Sanctum 'user' guard middleware instead of
    // the framework's default 'web' (session) guard — this API has no session auth, every
    // request (including the channel-auth handshake) carries a bearer token instead.
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        attributes: ['middleware' => ['user']],
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->append(GlobalLogger::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(JourneyLogger::class);

        $middleware->alias([
            'permission'   => CheckPermissionMiddleware::class,
            'user'         => UserMiddleware::class,
            'customer'     => CustomerMiddleware::class,
            'totp'         => RequireTotpMiddleware::class,
            'session.lock' => CheckSessionLockMiddleware::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => true,
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            $responder = new class {
                use \App\Traits\ApiResponse {
                    error as public;
                }
            };

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return $responder->error(
                    message: 'Validation failed.',
                    responseCode: '422',
                    statusCode: 422,
                    errors: $e->errors()
                );
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                if ($e->getPrevious() instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return $responder->error(
                        message: 'The requested data was not found.',
                        responseCode: '404',
                        statusCode: 404
                    );
                }

                return $responder->error(
                    message: 'The requested URL does not exist.',
                    responseCode: '404',
                    statusCode: 404
                );
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                return $responder->error(
                    message: 'This HTTP method is not allowed for this endpoint.',
                    responseCode: '405',
                    statusCode: 405
                );
            }

            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return $responder->error(
                    message: 'Unauthenticated.',
                    responseCode: '401',
                    statusCode: 401
                );
            }

            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return $responder->error(
                    message: 'This action is unauthorized.',
                    responseCode: '403',
                    statusCode: 403
                );
            }

            if ($e instanceof \TypeError) {
                return $responder->error(
                    message: 'The requested resource was not found.',
                    responseCode: '404',
                    statusCode: 404
                );
            }

            if ($e instanceof \Illuminate\Database\QueryException) {
                report($e);

                return $responder->error(
                    message: 'We are unable to process your request please try again (DB).',
                    responseCode: '100',
                    statusCode: 500
                );
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                report($e);

                return $responder->error(
                    message: 'We are unable to process your request please try again (KL).',
                    responseCode: (string) $e->getStatusCode(),
                    statusCode: $e->getStatusCode()
                );
            }

            report($e);

            return $responder->error(
                message: 'We are unable to process your request please try again (GB).',
                responseCode: '100',
                statusCode: 500,
            );
        });
    })->create();