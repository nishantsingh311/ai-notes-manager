<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again shortly.',
            ], 429, $exception->getHeaders());
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')
                || $exception instanceof ValidationException
                || $exception instanceof AuthenticationException) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();

                if ($status < 500) {
                    $message = match ($status) {
                        401 => 'Unauthenticated.',
                        403 => 'Forbidden.',
                        404 => 'Resource not found.',
                        405 => 'Method not allowed.',
                        default => 'The request could not be completed.',
                    };

                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], $status, $exception->getHeaders());
                }
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            return response()->json([
                'success' => false,
                'message' => 'An unexpected server error occurred.',
            ], $status);
        });
    })->create();
