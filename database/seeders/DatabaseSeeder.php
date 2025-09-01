<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ModulesSeeder::class,
            // Basic data
            SportsTableSeeder::class,
            SchoolSalaryLevelsTableSeeder::class,

            // V5 Enhanced Test Setup - Comprehensive testing scenarios
            V5EnhancedUsersSeeder::class,
            V5TestDataSeeder::class,

            // Additional V5 modules
            V5RentingDemoSeeder::class,
        ]);
    }
}
