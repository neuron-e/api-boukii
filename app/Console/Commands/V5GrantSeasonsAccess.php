<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\School;

class V5GrantSeasonsAccess extends Command
{
    protected $signature = 'v5:grant-seasons-access 
        {email : User email}
        {--school-id= : School ID to associate if needed}';

    protected $description = 'Grant access to V5 seasons endpoints by associating user to a school and assigning proper role';

    public function handle(): int
    {
        $email = trim($this->argument('email'));
        $schoolId = $this->option('school-id') ? (int) $this->option('school-id') : null;

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("User not found: {$email}");
            return self::FAILURE;
        }

        $this->info("User found: {$user->name} <{$user->email}>");

        if ($schoolId) {
            $school = School::find($schoolId);
            if (!$school) {
                $this->error("School not found: ID {$schoolId}");
                return self::FAILURE;
            }

            // Ensure association in school_users
            $exists = DB::table('school_users')
                ->where('user_id', $user->id)
                ->where('school_id', $schoolId)
                ->exists();

            if (!$exists) {
                $columns = collect(DB::select('SHOW COLUMNS FROM school_users'))->pluck('Field');
                $insert = [
                    'user_id' => $user->id,
                    'school_id' => $schoolId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if ($columns->contains('role')) {
                    $insert['role'] = 'manager';
                }
                DB::table('school_users')->insert($insert);
                $this->info("Associated user to school {$school->name} (ID {$schoolId}).");
            } else {
                $this->info("User already associated to school ID {$schoolId}.");
            }
        }

        // Assign Spatie role superadmin to bypass seasons.manage middleware (until fine-grained perms are split)
        try {
            if (!$user->hasRole('superadmin')) {
                $user->assignRole('superadmin');
                $this->info('Assigned role: superadmin');
            } else {
                $this->info('User already has role: superadmin');
            }
        } catch (\Throwable $e) {
            // Create role if missing
            try {
                \Spatie\Permission\Models\Role::findOrCreate('superadmin');
                $user->assignRole('superadmin');
                $this->info('Created and assigned role: superadmin');
            } catch (\Throwable $e2) {
                $this->error('Failed to assign superadmin role: '.$e2->getMessage());
                return self::FAILURE;
            }
        }

        $this->info('Done. The user should now access /api/v5/seasons endpoints.');
        return self::SUCCESS;
    }
}

