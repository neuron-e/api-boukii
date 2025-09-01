<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class TestV5UsersCommand extends Command
{
    protected $signature = 'boukii:test-v5-users';
    protected $description = 'Test V5 users functionality and authentication readiness';

    public function handle()
    {
        $this->info('🎿 Testing V5 Users Setup...');
        $this->info('─────────────────────────────────────────');
        
        // Test 1: Check users exist
        $this->testUsersExist();
        
        // Test 2: Check school associations
        $this->testSchoolAssociations();
        
        // Test 3: Test password verification
        $this->testPasswordVerification();
        
        // Test 4: Check required tables
        $this->testRequiredTables();
        
        $this->info('─────────────────────────────────────────');
        $this->info('✅ V5 Users test completed!');
        
        return Command::SUCCESS;
    }
    
    private function testUsersExist()
    {
        $this->info('🔍 Testing user creation...');
        
        $testUsers = [
            'superadmin@boukii-v5.com' => 'Superadmin (All schools)',
            'admin.single@boukii-v5.com' => 'Single school admin',
            'admin.multi@boukii-v5.com' => 'Multi school admin',
            'monitor@boukii-v5.com' => 'Monitor',
            'staff@boukii-v5.com' => 'Staff',
        ];
        
        foreach ($testUsers as $email => $description) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $this->line("   ✅ {$description}: {$user->first_name} {$user->last_name} (ID: {$user->id})");
            } else {
                $this->error("   ❌ Missing user: {$email}");
            }
        }
    }
    
    private function testSchoolAssociations()
    {
        $this->info('🏫 Testing school associations...');
        
        $superadmin = User::where('email', 'superadmin@boukii-v5.com')->first();
        if ($superadmin) {
            $schoolCount = DB::table('school_users')
                ->where('user_id', $superadmin->id)
                ->count();
            $this->line("   ✅ Superadmin associated with {$schoolCount} schools");
        }
        
        $singleAdmin = User::where('email', 'admin.single@boukii-v5.com')->first();
        if ($singleAdmin) {
            $schoolCount = DB::table('school_users')
                ->where('user_id', $singleAdmin->id)
                ->count();
            $this->line("   ✅ Single admin associated with {$schoolCount} school(s)");
        }
        
        $multiAdmin = User::where('email', 'admin.multi@boukii-v5.com')->first();
        if ($multiAdmin) {
            $schoolCount = DB::table('school_users')
                ->where('user_id', $multiAdmin->id)
                ->count();
            $this->line("   ✅ Multi admin associated with {$schoolCount} school(s)");
        }
    }
    
    private function testPasswordVerification()
    {
        $this->info('🔐 Testing password verification...');
        
        $user = User::where('email', 'superadmin@boukii-v5.com')->first();
        if ($user) {
            $isValidPassword = Hash::check('password123', $user->password);
            if ($isValidPassword) {
                $this->line('   ✅ Password verification working correctly');
            } else {
                $this->error('   ❌ Password verification failed');
            }
        }
    }
    
    private function testRequiredTables()
    {
        $this->info('📊 Checking required tables...');
        
        $requiredTables = ['users', 'schools', 'school_users', 'seasons'];
        
        foreach ($requiredTables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->line("   ✅ Table '{$table}' exists with {$count} records");
            } else {
                $this->error("   ❌ Missing required table: {$table}");
            }
        }
        
        // Check for V5 tables (optional)
        $v5Tables = ['user_season_roles', 'personal_access_tokens'];
        foreach ($v5Tables as $table) {
            if (Schema::hasTable($table)) {
                $this->line("   ✅ V5 table '{$table}' available");
            } else {
                $this->warn("   ⚠️  V5 table '{$table}' not yet created (expected if migrations pending)");
            }
        }
    }
}