<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Season;
use App\Models\School;

class V5SimpleUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Versión simplificada que solo crea usuarios sin tocar tablas V5 que puedan no existir
     */
    public function run(): void
    {
        $this->command->info('🚀 V5 Simple Users Seeder - Creating test users for existing schools...');
        
        DB::beginTransaction();
        
        try {
            // 1. Verificar escuelas existentes
            $schools = $this->getExistingSchools();
            $this->command->info("✅ Found schools: " . $schools->count());
            
            // 2. Crear usuarios de test
            $users = $this->createTestUsers();
            $this->command->info("✅ Test users created: " . $users->count());
            
            // 3. Asociar usuarios con escuelas usando school_users (tabla existente)
            $this->assignUsersToSchools($users, $schools);
            $this->command->info("✅ User-school associations created");
            
            // 4. Mostrar resumen
            $this->displayUserSummary($users, $schools);
            
            DB::commit();
            $this->command->info('🎯 V5 Simple Users setup completed successfully!');
            
        } catch (\Exception $e) {
            DB::rollback();
            $this->command->error('❌ Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener escuelas existentes
     */
    private function getExistingSchools()
    {
        return School::where('active', 1)->get();
    }
    
    /**
     * Crear usuarios de test con diferentes escenarios
     */
    private function createTestUsers()
    {
        $users = collect();
        
        // ESCENARIO 1: SUPERADMIN - Acceso a TODAS las escuelas
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@boukii-v5.com'],
            [
                'username' => 'superadmin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('password123'),
                'type' => 'admin',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $users->push($superAdmin);
        
        // ESCENARIO 2: ADMIN DE 1 SOLA ESCUELA 
        $singleSchoolAdmin = User::updateOrCreate(
            ['email' => 'admin.single@boukii-v5.com'],
            [
                'username' => 'admin_single',
                'first_name' => 'Marie',
                'last_name' => 'SingleSchool',
                'password' => Hash::make('password123'),
                'type' => 'admin',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $users->push($singleSchoolAdmin);
        
        // ESCENARIO 3: ADMIN DE MÚLTIPLES ESCUELAS
        $multiSchoolAdmin = User::updateOrCreate(
            ['email' => 'admin.multi@boukii-v5.com'],
            [
                'username' => 'admin_multi',
                'first_name' => 'Jean',
                'last_name' => 'MultiSchool',
                'password' => Hash::make('password123'),
                'type' => 'admin',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $users->push($multiSchoolAdmin);
        
        // ESCENARIO 4: MONITOR con acceso limitado
        $monitor = User::updateOrCreate(
            ['email' => 'monitor@boukii-v5.com'],
            [
                'username' => 'monitor_test',
                'first_name' => 'Pierre',
                'last_name' => 'Monitor',
                'password' => Hash::make('password123'),
                'type' => 'monitor',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $users->push($monitor);
        
        // ESCENARIO 5: STAFF con permisos básicos
        $staff = User::updateOrCreate(
            ['email' => 'staff@boukii-v5.com'],
            [
                'username' => 'staff_test',
                'first_name' => 'Sophie',
                'last_name' => 'Staff',
                'password' => Hash::make('password123'),
                'type' => 'staff',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $users->push($staff);
        
        return $users;
    }
    
    /**
     * Asociar usuarios con escuelas según el escenario
     */
    private function assignUsersToSchools($users, $schools)
    {
        foreach ($users as $user) {
            switch ($user->email) {
                case 'superadmin@boukii-v5.com':
                    // Superadmin: TODAS las escuelas
                    foreach ($schools as $school) {
                        DB::table('school_users')->updateOrInsert(
                            ['school_id' => $school->id, 'user_id' => $user->id],
                            ['created_at' => now(), 'updated_at' => now()]
                        );
                    }
                    break;
                    
                case 'admin.single@boukii-v5.com':
                case 'monitor@boukii-v5.com':
                    // Admin single y monitor: Solo primera escuela
                    if ($schools->count() > 0) {
                        DB::table('school_users')->updateOrInsert(
                            ['school_id' => $schools->first()->id, 'user_id' => $user->id],
                            ['created_at' => now(), 'updated_at' => now()]
                        );
                    }
                    break;
                    
                case 'admin.multi@boukii-v5.com':
                case 'staff@boukii-v5.com':
                    // Admin multi y staff: Múltiples escuelas (hasta 3)
                    $schoolsToAssign = $schools->take(3);
                    foreach ($schoolsToAssign as $school) {
                        DB::table('school_users')->updateOrInsert(
                            ['school_id' => $school->id, 'user_id' => $user->id],
                            ['created_at' => now(), 'updated_at' => now()]
                        );
                    }
                    break;
            }
        }
    }
    
    /**
     * Mostrar resumen de usuarios creados
     */
    private function displayUserSummary($users, $schools)
    {
        $this->command->info('');
        $this->command->info('🎿 === V5 SIMPLE USERS SUMMARY ===');
        $this->command->info('📊 Basic test users for V5 authentication');
        $this->command->info('─────────────────────────────────────────────');
        
        foreach ($users as $user) {
            $userSchools = DB::table('school_users')
                ->join('schools', 'schools.id', '=', 'school_users.school_id')
                ->where('school_users.user_id', $user->id)
                ->pluck('schools.name')
                ->toArray();
                
            $this->command->info("👤 {$user->first_name} {$user->last_name} ({$user->email})");
            $this->command->info("   Role: {$user->type} | Schools: " . count($userSchools));
            if (count($userSchools) <= 3) {
                $this->command->info("   Schools: " . implode(', ', $userSchools));
            } else {
                $this->command->info("   Schools: ALL (" . count($userSchools) . " total)");
            }
            $this->command->info('');
        }
        
        $this->command->info('🏫 SCHOOLS AVAILABLE:');
        foreach ($schools as $school) {
            $this->command->info("   • {$school->name} (ID: {$school->id})");
        }
        
        $this->command->info('');
        $this->command->info('🔑 TEST CREDENTIALS (all use password: password123):');
        $this->command->info('   • superadmin@boukii-v5.com - ALL schools access');
        $this->command->info('   • admin.single@boukii-v5.com - Single school only');
        $this->command->info('   • admin.multi@boukii-v5.com - Multiple schools');
        $this->command->info('   • monitor@boukii-v5.com - Monitor role');
        $this->command->info('   • staff@boukii-v5.com - Staff role');
        $this->command->info('─────────────────────────────────────────────');
        $this->command->info('✅ Ready for V5 authentication testing!');
        $this->command->info('🎯 Safe for existing database - no V5 table dependencies');
    }
}