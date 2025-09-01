<?php

namespace Database\Seeders;

use App\Domain\Modules\ModulesRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ModulesRegistry::all() as $module) {
            DB::table('modules')->updateOrInsert(
                ['slug' => $module['slug']],
                [
                    'name' => $module['name'],
                    'deps' => json_encode($module['deps']),
                    'priority' => $module['priority'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
