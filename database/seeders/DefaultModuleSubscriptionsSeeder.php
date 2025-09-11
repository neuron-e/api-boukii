<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\School;
use App\Models\SchoolModuleSubscription;
use App\V5\Modules\ModuleSubscription\Services\ModuleSubscriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DefaultModuleSubscriptionsSeeder extends Seeder
{
    protected ModuleSubscriptionService $moduleService;

    public function __construct()
    {
        $this->moduleService = app(ModuleSubscriptionService::class);
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Creating default module subscriptions...');

        // First, ensure modules are synced from registry
        Module::syncFromRegistry();

        $schools = School::all();
        $totalSubscriptions = 0;

        foreach ($schools as $school) {
            $this->command->info("🏫 Processing {$school->name}...");
            
            $schoolSubscriptions = $this->createDefaultSubscriptionsForSchool($school);
            $totalSubscriptions += $schoolSubscriptions;
        }

        $this->command->info("✅ Created {$totalSubscriptions} default module subscriptions for {$schools->count()} schools");
    }

    protected function createDefaultSubscriptionsForSchool(School $school): int
    {
        $subscriptionsCreated = 0;

        // Get existing subscriptions to avoid duplicates
        $existingModuleIds = SchoolModuleSubscription::where('school_id', $school->id)
            ->pluck('module_id')
            ->toArray();

        // Subscribe to high priority modules by default (basic tier)
        $highPriorityModules = Module::where('priority', 'high')
            ->whereNotIn('id', $existingModuleIds)
            ->get();

        foreach ($highPriorityModules as $module) {
            try {
                $this->moduleService->subscribeToModule(
                    $school->id,
                    $module->slug,
                    'basic',
                    null, // No specific user activated it
                    [], // Default settings
                    now()->addYear() // Expires in 1 year
                );
                $subscriptionsCreated++;
                $this->command->info("   ✓ Subscribed to {$module->name}");
            } catch (\Exception $e) {
                $this->command->warn("   ⚠ Could not subscribe to {$module->name}: {$e->getMessage()}");
            }
        }

        // Add trial subscriptions for medium priority modules (30 day trial)
        $mediumPriorityModules = Module::where('priority', 'medium')
            ->whereNotIn('id', $existingModuleIds)
            ->limit(2) // Limit to 2 trial modules per school
            ->get();

        foreach ($mediumPriorityModules as $module) {
            try {
                $this->moduleService->startTrial(
                    $school->id,
                    $module->slug,
                    30 // 30 day trial
                );
                $subscriptionsCreated++;
                $this->command->info("   ✓ Started trial for {$module->name}");
            } catch (\Exception $e) {
                $this->command->warn("   ⚠ Could not start trial for {$module->name}: {$e->getMessage()}");
            }
        }

        return $subscriptionsCreated;
    }
}