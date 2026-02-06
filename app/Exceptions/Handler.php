<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // For API requests, normalize error shape
        if ($request->expectsJson() || $request->is('api/*')) {
            $status = 500;
            $message = 'Server Error';
            $errors = null;
            $code = null;

            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            } elseif ($e instanceof ValidationException) {
                $status = 422;
                $message = 'Validation failed';
                $errors = $e->errors();
                $code = 'validation_error';
            } elseif ($e instanceof AuthenticationException) {
                $status = 401;
                $message = 'Unauthenticated';
                $code = 'unauthenticated';
            } elseif ($e instanceof ModelNotFoundException) {
                $status = 404;
                $model = class_basename($e->getModel());
                $message = $model.' not found';
                $code = 'not_found';
            } elseif ($e instanceof NotFoundHttpException) {
                $status = 404;
                $message = 'Route not found';
                $code = 'route_not_found';
            } elseif ($e instanceof MethodNotAllowedHttpException) {
                $status = 405;
                $message = 'Method not allowed';
                $code = 'method_not_allowed';
            } elseif ($e instanceof HttpException) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: 'HTTP error';
                $code = 'http_error';
            } else {
                \Log::error($e);
                $message = $e->getMessage() ?: $message;
                $code = 'server_error';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
                'code' => $code,
            ], $status);
        }

        return parent::render($request, $e);
    }
}
