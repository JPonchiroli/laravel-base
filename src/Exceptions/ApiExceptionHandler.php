<?php

namespace Jponchiroli\LaravelBase\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler
{
    public static function register(object $handler): void
    {
        if (method_exists($handler, 'shouldRenderJsonWhen')) {
            $handler->shouldRenderJsonWhen(fn (Request $request) => static::isApi($request));
        }

        $handler->renderable(fn (ValidationException $e, Request $request) => static::when($request, 'validation', 422, ['errors' => $e->errors()]));
        $handler->renderable(fn (AuthenticationException $e, Request $request) => static::when($request, 'unauthenticated', 401));
        $handler->renderable(fn (AuthorizationException $e, Request $request) => static::when($request, 'forbidden', 403));
        $handler->renderable(fn (ModelNotFoundException|NotFoundHttpException $e, Request $request) => static::when($request, 'not_found', 404));
        $handler->renderable(fn (MethodNotAllowedHttpException $e, Request $request) => static::when($request, 'method_not_allowed', 405));
        $handler->renderable(function (Throwable $e, Request $request) {
            if (config('app.debug')) {
                return null;
            }

            return static::when($request, 'server', $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500);
        });
    }

    protected static function isApi(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected static function when(Request $request, string $key, int $status, array $extra = []): ?JsonResponse
    {
        if (! static::isApi($request)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => __("laravel-base::messages.error.$key"),
            ...$extra,
        ], $status);
    }
}
