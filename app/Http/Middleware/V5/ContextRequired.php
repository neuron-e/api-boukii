<?php

namespace App\Http\Middleware\V5;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * ContextRequired Middleware V5
 * 
 * Validates that authenticated requests have proper school and season context.
 * This middleware ensures all V5 API requests operate within a specific 
 * school and season context for proper multi-tenant operation.
 */
class ContextRequired
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\JsonResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip context validation for auth endpoints
        if ($this->isAuthEndpoint($request)) {
            return $next($request);
        }

        // Check if user is authenticated
        if (!Auth::check()) {
            return $this->unauthorizedResponse('Authentication required');
        }

        $user = Auth::user();
        $token = $user->currentAccessToken();

        // Check if token has required abilities
        if (!$token || !$token->can('*')) {
            return $this->unauthorizedResponse('Invalid token permissions');
        }

        // Extract context from token or headers
        $schoolId = $this->getSchoolContext($request, $token);
        $seasonId = $this->getSeasonContext($request, $token);

        // Validate school context
        if (!$schoolId) {
            return $this->contextMissingResponse('School context required. Please select a school.');
        }

        // Validate season context  
        if (!$seasonId) {
            return $this->contextMissingResponse('Season context required. Please select a season.');
        }

        // Verify user has access to this school
        $hasSchoolAccess = $user->schools()->where('schools.id', $schoolId)->exists();
        if (!$hasSchoolAccess) {
            return $this->forbiddenResponse('Access denied to this school');
        }

        // Verify season belongs to school and user can access it
        $season = \App\Models\Season::where('id', $seasonId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$season) {
            return $this->contextMissingResponse('Invalid season for this school');
        }

        // Check if user can access inactive seasons
        if (!$season->is_active) {
            // Check if user has admin privileges for this specific school
            $canAccessClosed = $user->hasSchoolRole('superadmin', $schoolId) || 
                              $user->hasSchoolRole('admin', $schoolId);

            if (!$canAccessClosed) {
                return $this->forbiddenResponse('Cannot access closed seasons');
            }
        }

        // Add context to request for easy access in controllers
        $request->merge([
            '_school_id' => $schoolId,
            '_season_id' => $seasonId,
            '_school' => \App\Models\School::find($schoolId),
            '_season' => $season
        ]);

        // Add context headers to response
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->header('X-Current-School-ID', $schoolId);
            $response->header('X-Current-Season-ID', $seasonId);
        }

        return $response;
    }

    /**
     * Check if the request is for an auth endpoint that doesn't require context
     */
    private function isAuthEndpoint(Request $request): bool
    {
        $authRoutes = [
            'api/v5/auth/check-user',
            'api/v5/auth/select-school', 
            'api/v5/auth/select-season',
            'api/v5/auth/logout',
            'api/v5/auth/me' // Me endpoint handles context optionally
        ];

        $path = trim($request->getPathInfo(), '/');
        
        return in_array($path, $authRoutes);
    }

    /**
     * Extract school context from request headers or token
     */
    private function getSchoolContext(Request $request, PersonalAccessToken $token): ?int
    {
        // Try header first (preferred method)
        $headerSchoolId = $request->header('X-School-ID');
        if ($headerSchoolId && is_numeric($headerSchoolId)) {
            return (int) $headerSchoolId;
        }

        // Try token abilities as fallback
        $tokenSchoolId = $token->abilities['school_id'] ?? null;
        if ($tokenSchoolId && is_numeric($tokenSchoolId)) {
            return (int) $tokenSchoolId;
        }

        return null;
    }

    /**
     * Extract season context from request headers or token
     */
    private function getSeasonContext(Request $request, PersonalAccessToken $token): ?int
    {
        // Try header first (preferred method)
        $headerSeasonId = $request->header('X-Season-ID');
        if ($headerSeasonId && is_numeric($headerSeasonId)) {
            return (int) $headerSeasonId;
        }

        // Try token abilities as fallback
        $tokenSeasonId = $token->abilities['season_id'] ?? null;
        if ($tokenSeasonId && is_numeric($tokenSeasonId)) {
            return (int) $tokenSeasonId;
        }

        return null;
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED'
        ], 401);
    }

    /**
     * Return context missing response
     */
    private function contextMissingResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'CONTEXT_REQUIRED'
        ], 400);
    }

    /**
     * Return forbidden response
     */
    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'FORBIDDEN'
        ], 403);
    }
}