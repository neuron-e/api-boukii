<?php

namespace App\Console\Commands;

use App\Models\ClientObservation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CleanupClientObservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'client-observations:cleanup {--dry-run : Only report how many rows would be deleted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove empty client observations (general/notes/historical all blank)';

    public function handle(): int
    {
        $query = ClientObservation::query()
            ->where(function ($q) {
                $q->whereNull('general')->orWhere('general', '');
            })
            ->where(function ($q) {
                $q->whereNull('notes')->orWhere('notes', '');
            })
            ->where(function ($q) {
                $q->whereNull('historical')->orWhere('historical', '');
            });

        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->info("Empty client observations found: {$count}");
            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('No empty client observations to remove.');
            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Deleted {$deleted} empty client observations.");

        return self::SUCCESS;
    }
}
