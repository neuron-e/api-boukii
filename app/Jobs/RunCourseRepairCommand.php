<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunCourseRepairCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private ?int $schoolId;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $schoolId)
    {
        $this->schoolId = $schoolId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->schoolId) {
            Log::warning('RunCourseRepairCommand skipped because school_id is missing.');
            return;
        }

        try {
            Artisan::call('course:repair-orphaned-data', [
                '--school_id' => $this->schoolId,
            ]);
        } catch (\Throwable $exception) {
            Log::error('RunCourseRepairCommand failed', [
                'school_id' => $this->schoolId,
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }
}
