<?php

use App\Http\Middleware\LanguageMiddleware;
use App\Traits\ApiResponseTrait;
use Dotenv\Exception\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        
    $middleware->append(LanguageMiddleware::class);
    })
     ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                // 3. Call the method STATICALLY
                return ApiResponseTrait::handlingErrorResponse($e->getMessage() ?: __('app.validation_failed'), 422, $e->errors());
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

        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                // You will no longer hit this handler for this error
                return ApiResponseTrait::handlingErrorResponse(
                    $e->getMessage() ?: __('app.unexpected_error'),
                    500
                );
            }
        });
    })->create();
