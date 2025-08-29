<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use App\V5\Logging\V5Logger;
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
            try {
                V5Logger::logSystemError($e);
            } catch (\Throwable $ignore) {
            }
        });

        $this->renderable(function (ValidationException $e, Request $request): ?JsonResponse {
            if ($this->isApiV5($request)) {
                V5Logger::logValidationError($e->errors());
                return $this->problem('Validation failed', Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
            }
            return null;
        });

        $this->renderable(function (AuthorizationException $e, Request $request): ?JsonResponse {
            if ($this->isApiV5($request)) {
                return $this->problem('Forbidden', Response::HTTP_FORBIDDEN);
            }
            return null;
        });

        $this->renderable(function (ModelNotFoundException|NotFoundHttpException $e, Request $request): ?JsonResponse {
            if ($this->isApiV5($request)) {
                return $this->problem('Resource not found', Response::HTTP_NOT_FOUND);
            }
            return null;
        });

        $this->renderable(function (HttpExceptionInterface $e, Request $request): ?JsonResponse {
            if ($this->isApiV5($request)) {
                $status = $e->getStatusCode();
                return $this->problem(Response::$statusTexts[$status] ?? 'HTTP Error', $status);
            }
            return null;
        });
    }

    private function isApiV5(Request $request): bool
    {
        return $request->expectsJson() && ($request->is('api/v5/*') || $request->is('api/v5'));
    }

    private function problem(string $detail, int $status, array $errors = null): JsonResponse
    {
        $problem = [
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
            'detail' => $detail,
            'correlation_id' => V5Logger::getCorrelationId(),
        ];

        if ($errors) {
            $problem['errors'] = $errors;
        }

        return response()->json($problem, $status, ['Content-Type' => 'application/problem+json']);
    }

    /**
     * Handle unauthenticated users for API requests
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // For API requests, return JSON response instead of redirecting
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}
