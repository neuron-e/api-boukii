<?php

namespace App\Http\Controllers\Api\V5;

use App\Http\Controllers\Controller;
use App\V5\Modules\ModuleSubscription\Services\ModuleSubscriptionService;
use App\Domain\Modules\ModulesRegistry;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ModuleSubscriptionController extends Controller
{
    protected ModuleSubscriptionService $moduleService;

    public function __construct(ModuleSubscriptionService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    /**
     * Get available modules catalog
     */
    public function catalog(Request $request)
    {
        $coreModules = ModulesRegistry::getCoreModules();
        $contractableModules = ModulesRegistry::getContractableModules();

        return response()->json([
            'core_modules' => $coreModules,
            'contractable_modules' => $contractableModules
        ]);
    }

    /**
     * Get school's active subscriptions
     */
    public function index(Request $request)
    {
        $schoolId = $this->getSchoolId($request);
        $subscriptions = $this->moduleService->getSchoolSubscriptions($schoolId);

        return response()->json([
            'subscriptions' => $subscriptions,
            'active_count' => $subscriptions->where('status', 'active')->count(),
            'trial_count' => $subscriptions->where('status', 'trial')->count()
        ]);
    }

    /**
     * Get available modules for subscription
     */
    public function available(Request $request)
    {
        $schoolId = $this->getSchoolId($request);
        $availableModules = $this->moduleService->getAvailableModulesForSchool($schoolId);

        return response()->json([
            'available_modules' => $availableModules
        ]);
    }

    /**
     * Subscribe to a module
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'module_slug' => 'required|string|exists:modules,slug',
            'subscription_type' => ['required', 'string', Rule::in(['free', 'basic', 'premium', 'enterprise'])],
            'settings' => 'nullable|array',
            'expires_at' => 'nullable|date|after:now'
        ]);

        try {
            $schoolId = $this->getSchoolId($request);
            $expiresAt = $validated['expires_at'] ? \Carbon\Carbon::parse($validated['expires_at']) : null;
            
            $subscription = $this->moduleService->subscribeToModule(
                $schoolId,
                $validated['module_slug'],
                $validated['subscription_type'],
                $request->user(),
                $validated['settings'] ?? [],
                $expiresAt
            );

            return response()->json([
                'message' => 'Successfully subscribed to module',
                'subscription' => $subscription->load(['module', 'activatedBy'])
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Subscription Error',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Start trial for a module
     */
    public function startTrial(Request $request)
    {
        $validated = $request->validate([
            'module_slug' => 'required|string|exists:modules,slug',
            'trial_days' => 'nullable|integer|min:1|max:90'
        ]);

        try {
            $schoolId = $this->getSchoolId($request);
            
            $subscription = $this->moduleService->startTrial(
                $schoolId,
                $validated['module_slug'],
                $validated['trial_days'] ?? 30,
                $request->user()
            );

            return response()->json([
                'message' => 'Trial started successfully',
                'subscription' => $subscription->load(['module', 'activatedBy'])
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Trial Error',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Upgrade subscription
     */
    public function upgrade(Request $request, int $subscriptionId)
    {
        $validated = $request->validate([
            'subscription_type' => ['required', 'string', Rule::in(['free', 'basic', 'premium', 'enterprise'])]
        ]);

        try {
            $subscription = $this->moduleService->upgradeSubscription(
                $subscriptionId,
                $validated['subscription_type'],
                $request->user()
            );

            return response()->json([
                'message' => 'Subscription upgraded successfully',
                'subscription' => $subscription->load(['module', 'activatedBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upgrade Error',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request, int $subscriptionId)
    {
        try {
            $this->moduleService->cancelSubscription($subscriptionId, $request->user());

            return response()->json([
                'message' => 'Subscription cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Cancellation Error',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get module usage statistics
     */
    public function usage(Request $request, string $moduleSlug)
    {
        $schoolId = $this->getSchoolId($request);
        $stats = $this->moduleService->getModuleUsageStats($schoolId, $moduleSlug);

        if (empty($stats)) {
            return response()->json([
                'error' => 'Module Not Found',
                'message' => 'No subscription found for this module'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'module_slug' => $moduleSlug,
            'school_id' => $schoolId,
            'usage_stats' => $stats
        ]);
    }

    /**
     * Check module access
     */
    public function checkAccess(Request $request, string $moduleSlug)
    {
        $schoolId = $this->getSchoolId($request);
        $hasAccess = $this->moduleService->hasModuleAccess($schoolId, $moduleSlug);

        $module = Module::where('slug', $moduleSlug)->first();
        if (!$module) {
            return response()->json([
                'error' => 'Module Not Found',
                'message' => 'The specified module does not exist'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'module_slug' => $moduleSlug,
            'school_id' => $schoolId,
            'has_access' => $hasAccess,
            'is_core' => $module->isCore(),
            'module_info' => [
                'name' => $module->name,
                'priority' => $module->priority,
                'dependencies' => $module->getDependencies()
            ]
        ]);
    }

    /**
     * Get subscription details
     */
    public function show(Request $request, int $subscriptionId)
    {
        $schoolId = $this->getSchoolId($request);
        
        $subscription = \App\Models\SchoolModuleSubscription::with(['module', 'activatedBy', 'school'])
            ->where('id', $subscriptionId)
            ->where('school_id', $schoolId)
            ->first();

        if (!$subscription) {
            return response()->json([
                'error' => 'Subscription Not Found',
                'message' => 'The specified subscription was not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Get module features for current tier
        $moduleSlug = $subscription->module->slug;
        $features = ModulesRegistry::getModuleFeatures($moduleSlug, $subscription->subscription_type);

        return response()->json([
            'subscription' => $subscription,
            'features' => $features,
            'can_access' => $subscription->canAccess()
        ]);
    }

    /**
     * Get school ID from context
     */
    protected function getSchoolId(Request $request): int
    {
        // Try to get from token context first
        $schoolId = $request->user()?->currentAccessToken()?->tokenable_id ?? 
                   $request->header('X-School-ID') ?? 
                   $request->input('school_id');

        if (!$schoolId) {
            abort(Response::HTTP_BAD_REQUEST, 'School context is required');
        }

        return (int) $schoolId;
    }
}