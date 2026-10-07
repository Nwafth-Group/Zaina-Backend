<?php

use App\Http\Middleware\LanguageMiddleware;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->append(\App\Http\Middleware\QueryTokenToAuthHeader::class);
        $middleware->append(LanguageMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                // Mirror the envelope produced by CustomFormRequest so that every
                // validation path (FormRequests + inline $request->validate()) is
                // consistent: 422 with a `params` map of field => first message.
                $params = collect($e->errors())->map(fn (array $messages) => $messages[0]);

                return response()->json([
                    'status' => 422,
                    'success' => false,
                    'message' => __('auth.validation_failed'),
                    'data' => null,
                    'params' => $params,
                ], 422);
            }
        });

        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponseTrait::handlingErrorResponse(
                    $e->getMessage() ?: __('app.unauthorized'),
                    401
                );
            }
        });

        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponseTrait::handlingErrorResponse($e->getMessage() ?: __('app.resource_not_found'), 404);
            }
        });

        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponseTrait::handlingErrorResponse($e->getMessage() ?: __('app.resource_not_found'), 404);
            }
        });

        $exceptions->renderable(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                // This is the one that was failing. It will now work.
                return ApiResponseTrait::handlingErrorResponse(
                    $e->getMessage() ?: __('app.unauthorized'),
                    403
                );
            }
        });

        $exceptions->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponseTrait::handlingErrorResponse(
                    $e->getMessage() ?: __('app.method_not_allowed'),
                    405
                );
            }
        });

        $exceptions->renderable(function (HttpException $e, Request $request) {
            if ($request->is('api/*') && $e->getStatusCode() === 500) {
                return ApiResponseTrait::handlingErrorResponse(
                    $e->getMessage() ?: __('app.internal_server_error'),
                    500
                );
            }
        });

        $exceptions->renderable(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponseTrait::handlingErrorResponse(
                    $e->getMessage() ?: __('app.unexpected_error'),
                    $e->getStatusCode()
                );
            }
        });

        $exceptions->renderable(function (\Illuminate\Database\QueryException $e, Request $request) {
            if ($request->is('api/*')) {
                \Illuminate\Support\Facades\Log::error('Database QueryException: ' . $e->getMessage(), [
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                ]);

                return response()->json([
                    'status' => 500,
                    'success' => false,
                    'message' => __('app.database_error'),
                    'data' => null,
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }
        });

        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                \Illuminate\Support\Facades\Log::error('Unhandled Exception: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                $isDbError = $e instanceof \PDOException;
                $friendlyMessage = $isDbError
                    ? __('app.database_error')
                    : (config('app.debug') ? ($e->getMessage() ?: __('app.unexpected_error')) : __('app.unexpected_error'));

                return response()->json([
                    'status' => 500,
                    'success' => false,
                    'message' => $friendlyMessage,
                    'data' => null,
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }
        });
    })->create();
