<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\School;
use App\Models\SchoolModuleSubscription;
use App\V5\Modules\ModuleSubscription\Services\ModuleSubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitializeModulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'modules:initialize {--school-id= : Initialize for specific school ID} {--with-trials : Add trial subscriptions for premium modules}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize the modules system with default subscriptions for schools';

    protected ModuleSubscriptionService $moduleService;

    public function __construct(ModuleSubscriptionService $moduleService)
    {
        parent::__construct();
        $this->moduleService = $moduleService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Initializing Modules System...');

        // Sync modules from registry
        $this->syncModules();

        // Initialize school subscriptions
        $schoolId = $this->option('school-id');
        $withTrials = $this->option('with-trials');

        if ($schoolId) {
            $this->initializeSchool((int) $schoolId, $withTrials);
        } else {
            $this->initializeAllSchools($withTrials);
        }

        $this->info('✅ Modules system initialized successfully!');
    }

    protected function syncModules(): void
    {
        $this->info('📦 Syncing modules from registry...');
        
        Module::syncFromRegistry();
        
        $moduleCount = Module::count();
        $this->info("   → Synced {$moduleCount} modules");
    }

    protected function initializeAllSchools(bool $withTrials): void
    {
        $schools = School::all();
        $this->info("🏫 Initializing {$schools->count()} schools...");

        $schools->each(function (School $school) use ($withTrials) {
            $this->initializeSchool($school->id, $withTrials);
        });
    }

    protected function initializeSchool(int $schoolId, bool $withTrials): void
    {
        $school = School::find($schoolId);
        if (!$school) {
            $this->error("❌ School ID {$schoolId} not found");
            return;
        }

        $this->info("   🏫 Initializing {$school->name} (ID: {$schoolId})...");

        // Get existing subscriptions
        $existingModules = SchoolModuleSubscription::where('school_id', $schoolId)
            ->pluck('module_id')
            ->toArray();

        $subscriptionsCreated = 0;

        // Subscribe to high priority modules by default
        $highPriorityModules = Module::where('priority', 'high')->get();
        
        foreach ($highPriorityModules as $module) {
            if (in_array($module->id, $existingModules)) {
                continue;
            }

            try {
                $this->moduleService->subscribeToModule(
                    $schoolId,
                    $module->slug,
                    'basic'
                );
                $subscriptionsCreated++;
                $this->info("     ✓ Subscribed to {$module->name}");
            } catch (\Exception $e) {
                $this->warn("     ⚠ Could not subscribe to {$module->name}: {$e->getMessage()}");
            }
        }

        // Add trial subscriptions for premium modules if requested
        if ($withTrials) {
            $mediumPriorityModules = Module::where('priority', 'medium')
                ->whereNotIn('id', $existingModules)
                ->get();

            foreach ($mediumPriorityModules as $module) {
                try {
                    $this->moduleService->startTrial(
                        $schoolId,
                        $module->slug,
                        30
                    );
                    $subscriptionsCreated++;
                    $this->info("     ✓ Started trial for {$module->name}");
                } catch (\Exception $e) {
                    $this->warn("     ⚠ Could not start trial for {$module->name}: {$e->getMessage()}");
                }
            }
        }

        $this->info("     → Created {$subscriptionsCreated} new subscriptions");
    }
}
