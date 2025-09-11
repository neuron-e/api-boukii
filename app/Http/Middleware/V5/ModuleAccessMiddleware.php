<?php

namespace App\Http\Middleware\V5;

use App\V5\Modules\ModuleSubscription\Services\ModuleSubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ModuleAccessMiddleware
{
    protected ModuleSubscriptionService $moduleService;

    public function __construct(ModuleSubscriptionService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $moduleSlug
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $moduleSlug)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Authentication required'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Get school ID from token context or request
        $schoolId = $user->currentAccessToken()?->tokenable_id ?? 
                   $request->header('X-School-ID') ?? 
                   $request->input('school_id');

        if (!$schoolId) {
            return response()->json([
                'error' => 'No School Context',
                'message' => 'School context is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check module access
        if (!$this->moduleService->hasModuleAccess($schoolId, $moduleSlug)) {
            return response()->json([
                'error' => 'Module Access Denied',
                'message' => "Access to '{$moduleSlug}' module is not available for this school",
                'module' => $moduleSlug,
                'school_id' => $schoolId
            ], Response::HTTP_FORBIDDEN);
        }

        // Add module context to request
        $request->attributes->set('module_slug', $moduleSlug);
        $request->attributes->set('school_id', $schoolId);

        return $next($request);
    }
}