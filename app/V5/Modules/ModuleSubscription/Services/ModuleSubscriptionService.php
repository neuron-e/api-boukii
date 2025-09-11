<?php

namespace App\V5\Modules\ModuleSubscription\Services;

use App\Models\Module;
use App\Models\School;
use App\Models\SchoolModuleSubscription;
use App\Models\User;
use App\Domain\Modules\ModulesRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ModuleSubscriptionService
{
    /**
     * Get active subscriptions for a school
     */
    public function getSchoolActiveSubscriptions(int $schoolId): Collection
    {
        return SchoolModuleSubscription::with('module')
            ->where('school_id', $schoolId)
            ->where(function ($query) {
                $query->where('status', 'active')
                      ->orWhere(function ($q) {
                          $q->where('status', 'trial')
                            ->where('trial_ends_at', '>', now());
                      });
            })
            ->get();
    }

    /**
     * Get all subscriptions for a school
     */
    public function getSchoolSubscriptions(int $schoolId): Collection
    {
        return SchoolModuleSubscription::with(['module', 'activatedBy'])
            ->where('school_id', $schoolId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if school has access to a module
     */
    public function hasModuleAccess(int $schoolId, string $moduleSlug): bool
    {
        $module = Module::where('slug', $moduleSlug)->first();
        if (!$module) {
            return false;
        }

        // Core modules are always available
        if ($module->isCore()) {
            return true;
        }

        $subscription = SchoolModuleSubscription::where('school_id', $schoolId)
            ->where('module_id', $module->id)
            ->first();

        return $subscription?->canAccess() ?? false;
    }

    /**
     * Get available modules for subscription (not currently subscribed)
     */
    public function getAvailableModulesForSchool(int $schoolId): array
    {
        $currentSubscriptions = SchoolModuleSubscription::where('school_id', $schoolId)
            ->whereIn('status', ['active', 'trial'])
            ->pluck('module_id')
            ->toArray();

        $contractableModules = Module::getContractableModules()
            ->whereNotIn('id', $currentSubscriptions)
            ->get();

        return $contractableModules->map(function ($module) {
            $registryData = ModulesRegistry::getModule($module->slug);
            return array_merge($module->toArray(), [
                'pricing' => $registryData['pricing'] ?? [],
                'features' => $registryData['features'] ?? [],
                'description' => $registryData['description'] ?? '',
            ]);
        })->toArray();
    }

    /**
     * Subscribe school to a module
     */
    public function subscribeToModule(
        int $schoolId,
        string $moduleSlug,
        string $subscriptionType = 'basic',
        ?User $activatedBy = null,
        array $settings = [],
        ?Carbon $expiresAt = null
    ): SchoolModuleSubscription {
        $module = Module::where('slug', $moduleSlug)->firstOrFail();
        
        // Check dependencies
        $this->validateModuleDependencies($schoolId, $moduleSlug);

        // Get pricing and limits
        $pricing = ModulesRegistry::getModulePricing($moduleSlug);
        $tierInfo = $pricing[$subscriptionType] ?? [];
        
        $subscription = SchoolModuleSubscription::create([
            'school_id' => $schoolId,
            'module_id' => $module->id,
            'status' => 'active',
            'subscription_type' => $subscriptionType,
            'activated_at' => now(),
            'expires_at' => $expiresAt,
            'settings' => $settings,
            'limits' => $this->getDefaultLimitsForTier($moduleSlug, $subscriptionType),
            'monthly_cost' => $tierInfo['price'] ?? 0,
            'activated_by' => $activatedBy?->id,
        ]);

        // Log subscription activity
        activity()
            ->performedOn($subscription)
            ->withProperties([
                'school_id' => $schoolId,
                'module_slug' => $moduleSlug,
                'subscription_type' => $subscriptionType,
            ])
            ->log('module_subscribed');

        return $subscription;
    }

    /**
     * Start trial for a module
     */
    public function startTrial(
        int $schoolId,
        string $moduleSlug,
        int $trialDays = 30,
        ?User $activatedBy = null
    ): SchoolModuleSubscription {
        $module = Module::where('slug', $moduleSlug)->firstOrFail();
        
        // Check if trial already exists
        $existingSubscription = SchoolModuleSubscription::where('school_id', $schoolId)
            ->where('module_id', $module->id)
            ->first();

        if ($existingSubscription) {
            throw new \InvalidArgumentException('Subscription already exists for this module');
        }

        // Check dependencies
        $this->validateModuleDependencies($schoolId, $moduleSlug);

        $subscription = SchoolModuleSubscription::create([
            'school_id' => $schoolId,
            'module_id' => $module->id,
            'status' => 'trial',
            'subscription_type' => 'basic', // Default trial tier
            'activated_at' => now(),
            'trial_ends_at' => now()->addDays($trialDays),
            'settings' => [],
            'limits' => $this->getDefaultLimitsForTier($moduleSlug, 'basic'),
            'monthly_cost' => 0,
            'activated_by' => $activatedBy?->id,
        ]);

        activity()
            ->performedOn($subscription)
            ->withProperties([
                'school_id' => $schoolId,
                'module_slug' => $moduleSlug,
                'trial_days' => $trialDays,
            ])
            ->log('module_trial_started');

        return $subscription;
    }

    /**
     * Upgrade subscription
     */
    public function upgradeSubscription(
        int $subscriptionId,
        string $newTier,
        ?User $upgradedBy = null
    ): SchoolModuleSubscription {
        $subscription = SchoolModuleSubscription::findOrFail($subscriptionId);
        
        $moduleSlug = $subscription->module->slug;
        $pricing = ModulesRegistry::getModulePricing($moduleSlug);
        $tierInfo = $pricing[$newTier] ?? [];

        $subscription->update([
            'subscription_type' => $newTier,
            'status' => 'active',
            'limits' => $this->getDefaultLimitsForTier($moduleSlug, $newTier),
            'monthly_cost' => $tierInfo['price'] ?? 0,
            'activated_by' => $upgradedBy?->id,
        ]);

        activity()
            ->performedOn($subscription)
            ->withProperties([
                'old_tier' => $subscription->subscription_type,
                'new_tier' => $newTier,
            ])
            ->log('module_subscription_upgraded');

        return $subscription->fresh();
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(int $subscriptionId, ?User $cancelledBy = null): bool
    {
        $subscription = SchoolModuleSubscription::findOrFail($subscriptionId);
        
        $subscription->update([
            'status' => 'inactive',
            'activated_by' => $cancelledBy?->id,
        ]);

        activity()
            ->performedOn($subscription)
            ->withProperties([
                'module_slug' => $subscription->module->slug,
                'cancelled_by' => $cancelledBy?->id,
            ])
            ->log('module_subscription_cancelled');

        return true;
    }

    /**
     * Process expired subscriptions
     */
    public function processExpiredSubscriptions(): int
    {
        $expiredCount = 0;

        SchoolModuleSubscription::where('expires_at', '<', now())
            ->whereIn('status', ['active', 'trial'])
            ->chunk(100, function ($subscriptions) use (&$expiredCount) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update(['status' => 'expired']);
                    $expiredCount++;

                    activity()
                        ->performedOn($subscription)
                        ->log('module_subscription_expired');
                }
            });

        return $expiredCount;
    }

    /**
     * Get subscriptions expiring soon
     */
    public function getExpiringSoon(int $days = 30): Collection
    {
        return SchoolModuleSubscription::with(['school', 'module'])
            ->expiringSoon($days)
            ->get();
    }

    /**
     * Validate module dependencies
     */
    protected function validateModuleDependencies(int $schoolId, string $moduleSlug): void
    {
        $activeModules = $this->getSchoolActiveSubscriptions($schoolId)
            ->pluck('module.slug')
            ->toArray();

        // Add core modules (always available)
        $coreModules = Module::getCoreModules()->pluck('slug')->toArray();
        $activeModules = array_merge($activeModules, $coreModules);

        $validation = ModulesRegistry::canActivateModule($moduleSlug, $activeModules);
        
        if (!$validation['can_activate']) {
            throw new \InvalidArgumentException(
                'Missing dependencies: ' . implode(', ', $validation['missing_dependencies'])
            );
        }
    }

    /**
     * Get default limits for a tier
     */
    protected function getDefaultLimitsForTier(string $moduleSlug, string $tier): array
    {
        $pricing = ModulesRegistry::getModulePricing($moduleSlug);
        $tierInfo = $pricing[$tier] ?? [];
        
        // Extract limits from pricing config (remove 'price' key)
        $limits = array_filter($tierInfo, fn($key) => $key !== 'price', ARRAY_FILTER_USE_KEY);
        
        return $limits;
    }

    /**
     * Get module usage statistics for a school
     */
    public function getModuleUsageStats(int $schoolId, string $moduleSlug): array
    {
        $subscription = SchoolModuleSubscription::with('module')
            ->where('school_id', $schoolId)
            ->whereHas('module', fn($q) => $q->where('slug', $moduleSlug))
            ->first();

        if (!$subscription) {
            return [];
        }

        // This would typically fetch usage data from various module tables
        // For now, return basic subscription info
        return [
            'subscription_type' => $subscription->subscription_type,
            'status' => $subscription->status,
            'limits' => $subscription->limits,
            'expires_at' => $subscription->expires_at,
            'trial_ends_at' => $subscription->trial_ends_at,
            'monthly_cost' => $subscription->monthly_cost,
        ];
    }
}