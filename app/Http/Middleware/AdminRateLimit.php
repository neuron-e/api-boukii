<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Response;

/**
 * Rate Limiting específico para Admin Panel Angular
 * Permite más requests para analytics y operaciones masivas
 */
class AdminRateLimit
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next)
    {
        $key = $this->resolveRequestSignature($request);
        
        // Límites específicos por tipo de endpoint
        $limits = $this->getEndpointLimits($request);
        
        if ($this->limiter->tooManyAttempts($key, $limits['maxAttempts'])) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Rate limit exceeded.',
                'retry_after' => $this->limiter->availableIn($key)
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $this->limiter->hit($key, $limits['decayMinutes'] * 60);

        $response = $next($request);

        // Añadir headers informativos
        $response->headers->add([
            'X-RateLimit-Limit' => $limits['maxAttempts'],
            'X-RateLimit-Remaining' => $this->limiter->remaining($key, $limits['maxAttempts']),
            'X-RateLimit-Reset' => $this->limiter->availableIn($key) + time(),
        ]);

        return $response;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        $userId = $user ? $user->id : $request->ip();
        
        return 'admin_rate_limit:' . $userId;
    }

    protected function getEndpointLimits(Request $request): array
    {
        $path = $request->path();

        // Endpoints pesados (dashboard y tablas grandes)
        if (
            str_contains($path, 'admin/dashboard') ||
            str_contains($path, 'admin/bookings/table') ||
            str_contains($path, 'admin/analytics/executive-dashboard') ||
            str_contains($path, 'admin/analytics/operational-dashboard') ||
            str_contains($path, 'admin/analytics/financial-dashboard')
        ) {
            return [
                'maxAttempts' => 20, // 20 requests por minuto
                'decayMinutes' => 1
            ];
        }
        
        // Analytics endpoints - más permisivos por dashboard intensivo
        if (str_contains($path, 'admin/analytics') || str_contains($path, 'admin/finance')) {
            return [
                'maxAttempts' => 120, // 120 requests por minuto
                'decayMinutes' => 1
            ];
        }
        
        // CRUD operations - para operaciones masivas del admin
        if (preg_match('/api\/(clients|courses|monitors|bookings|payments)/', $path)) {
            return [
                'maxAttempts' => 180, // 180 requests por minuto para CRUD
                'decayMinutes' => 1
            ];
        }
        
        // Export operations - más restrictivo
        if (str_contains($path, 'export') || str_contains($path, 'download')) {
            return [
                'maxAttempts' => 10,  // Solo 10 exports por minuto
                'decayMinutes' => 1
            ];
        }
        
        // Login endpoint - muy restrictivo por seguridad
        if (str_contains($path, 'admin/login')) {
            return [
                'maxAttempts' => 5,   // Solo 5 intentos de login por minuto
                'decayMinutes' => 1
            ];
        }
        
        // Default para admin endpoints
        return [
            'maxAttempts' => 100,
            'decayMinutes' => 1
        ];
    }
}