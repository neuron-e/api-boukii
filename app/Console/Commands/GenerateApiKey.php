<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timing:generate-api-key 
                           {school_id : The school ID to generate the key for}
                           {--name= : Human readable name for the API key}
                           {--scopes=timing:write : Comma-separated list of scopes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new API key for timing ingestion';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $schoolId = $this->argument('school_id');
        $name = $this->option('name') ?: "Timing API Key " . now()->format('Y-m-d H:i:s');
        $scopes = explode(',', $this->option('scopes') ?: 'timing:write');

        // Validate school exists
        $school = \App\Models\School::find($schoolId);
        if (!$school) {
            $this->error("School with ID {$schoolId} not found");
            return 1;
        }

        try {
            $result = \App\Models\ApiKey::createKey($name, $schoolId, $scopes);
            
            $this->info("✓ API Key generated successfully!");
            $this->newLine();
            $this->line("School: {$school->name} (ID: {$schoolId})");
            $this->line("Name: {$name}");
            $this->line("Scopes: " . implode(', ', $scopes));
            $this->newLine();
            $this->warn("API Key (SAVE THIS - it won't be shown again):");
            $this->line($result['key']);
            $this->newLine();
            $this->comment("Use this key in the X-Api-Key header for timing API requests.");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to generate API key: " . $e->getMessage());
            return 1;
        }
    }
}
