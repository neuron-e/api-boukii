<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Season;
use App\Models\School;

class V5EnhancedUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Crea usuarios de test siguiendo el v5-migration-plan.md:
     * - Usuario superadmin con acceso a todas las escuelas
     * - Usuario con 1 sola escuela 
     * - Usuario con varias escuelas
     * - Escuelas activas con temporadas activas
     */
    public function run(): void
    {
        $this->command->info('🚀 V5 Enhanced Users Seeder - Creating comprehensive test users...');
        
        DB::beginTransaction();
        
        try {
            // 1. Verificar/crear escuelas activas
            $schools = $this->ensureActiveSchools();
            $this->command->info("✅ Active schools: " . $schools->count());
            
            // 2. Crear temporadas activas para cada escuela
            $seasons = $this->ensureActiveSeasonsForSchools($schools);
            $this->command->info("✅ Active seasons: " . $seasons->count());
            
            // 3. Crear usuarios de test con diferentes escenarios
            $users = $this->createTestUsers($schools, $seasons);
            $this->command->info("✅ Test users created: " . $users->count());
            
            // 4. Asignar roles y permisos en temporadas
            $this->assignUserSeasonRoles($users, $seasons);
            $this->command->info("✅ User-season roles assigned");
            
            // 5. Mostrar resumen
            $this->displayTestUserSummary($users, $schools, $seasons);
            
            DB::commit();
            $this->command->info('🎯 V5 Enhanced Users setup completed successfully!');
            
        } catch (\Exception $e) {
            DB::rollback();
            $this->command->error('❌ Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Asegurar que tengamos escuelas activas para testing
     */
    private function ensureActiveSchools()
    {
        $schools = collect();
        
        // Escuela 1: ESF Val d'Isère (Multi-escuela scenario)
        $school1 = School::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'ESF Val d\'Isère',
                'description' => 'École du Ski Français - Val d\'Isère',
                'contact_address' => 'Avenue Olympique, Val d\'Isère',
                'contact_city' => 'Val d\'Isère',
                'contact_cp' => '73150',
                'contact_country' => 'France',
                'contact_phone' => '+33 4 79 06 02 34',
                'contact_email' => 'contact@esf-valdisere.com',
                'active' => 1,
                'is_test_school' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $schools->push($school1);
        
        // Escuela 2: ESS Veveyse (Single-escuela scenario)  
        $school2 = School::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'École Suisse de Ski de Veveyse',
                'description' => 'École Suisse de Ski - Veveyse',
                'contact_address' => 'Route des Pistes 12',
                'contact_city' => 'Châtel-St-Denis',
                'contact_cp' => '1618',
                'contact_country' => 'Switzerland',
                'contact_phone' => '+41 21 948 84 50',
                'contact_email' => 'info@essveveyse.ch',
                'active' => 1,
                'is_test_school' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $schools->push($school2);
        
        // Escuela 3: ESF Chamonix (Multi-escuela scenario)
        $school3 = School::updateOrCreate(
            ['id' => 3],
            [
                'name' => 'ESF Chamonix Mont-Blanc',
                'description' => 'École du Ski Français - Chamonix Mont-Blanc',
                'contact_address' => '183 Avenue Michel Croz',
                'contact_city' => 'Chamonix-Mont-Blanc',
                'contact_cp' => '74400',
                'contact_country' => 'France',
                'contact_phone' => '+33 4 50 53 22 57',
                'contact_email' => 'contact@esf-chamonix.com',
                'active' => 1,
                'is_test_school' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $schools->push($school3);
        
        // Escuela 4: Ski School Grindelwald (Multi-escuela scenario)
        $school4 = School::updateOrCreate(
            ['id' => 4],
            [
                'name' => 'Ski School Grindelwald',
                'description' => 'Swiss Ski School - Grindelwald',
                'contact_address' => 'Dorfstrasse 110',
                'contact_city' => 'Grindelwald',
                'contact_cp' => '3818',
                'contact_country' => 'Switzerland',
                'contact_phone' => '+41 33 854 12 80',
                'contact_email' => 'info@skischool-grindelwald.ch',
                'active' => 1,
                'is_test_school' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $schools->push($school4);
        
        return $schools;
    }
    
    /**
     * Crear temporadas activas para cada escuela
     */
    private function ensureActiveSeasonsForSchools($schools)
    {
        $seasons = collect();
        
        foreach ($schools as $school) {
            // Temporada actual 2024-2025 (activa)
            $currentSeason = Season::updateOrCreate([
                'school_id' => $school->id,
                'name' => '2024-2025'
            ], [
                'start_date' => '2024-12-01',
                'end_date' => '2025-04-30',
                'is_active' => 1,
                'hour_start' => '08:00:00',
                'hour_end' => '18:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $seasons->push($currentSeason);
            
            // Temporada previa 2023-2024 (inactiva, para testing histórico)
            Season::updateOrCreate([
                'school_id' => $school->id,
                'name' => '2023-2024'
            ], [
                'start_date' => '2023-12-01',
                'end_date' => '2024-04-30',
                'is_active' => 0,
                'hour_start' => '08:00:00',
                'hour_end' => '18:00:00',
                'created_at' => now()->subYear(),
                'updated_at' => now(),
            ]);
        }
        
        return $seasons;
    }
    
    /**
     * Crear usuarios de test con diferentes escenarios
     */
    private function createTestUsers($schools, $seasons)
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
        
        // Asociar superadmin con TODAS las escuelas
        foreach ($schools as $school) {
            DB::table('school_users')->updateOrInsert(
                ['school_id' => $school->id, 'user_id' => $superAdmin->id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
        $users->push($superAdmin);
        
        // ESCENARIO 2: ADMIN DE 1 SOLA ESCUELA (ESS Veveyse)
        $singleSchoolAdmin = User::updateOrCreate(
            ['email' => 'admin.veveyse@boukii-v5.com'],
            [
                'username' => 'admin_veveyse',
                'first_name' => 'Marie',
                'last_name' => 'Dupont',
                'password' => Hash::make('password123'),
                'type' => 'admin',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        // Solo asociar con escuela ID 2 (ESS Veveyse)
        DB::table('school_users')->updateOrInsert(
            ['school_id' => 2, 'user_id' => $singleSchoolAdmin->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
        $users->push($singleSchoolAdmin);
        
        // ESCENARIO 3: ADMIN DE MÚLTIPLES ESCUELAS (pero no todas)
        $multiSchoolAdmin = User::updateOrCreate(
            ['email' => 'admin.multi@boukii-v5.com'],
            [
                'username' => 'admin_multi',
                'first_name' => 'Jean',
                'last_name' => 'Moreau',
                'password' => Hash::make('password123'),
                'type' => 'admin',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        // Asociar con escuelas 1, 3 y 4 (pero NO con la 2)
        foreach ([1, 3, 4] as $schoolId) {
            DB::table('school_users')->updateOrInsert(
                ['school_id' => $schoolId, 'user_id' => $multiSchoolAdmin->id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
        $users->push($multiSchoolAdmin);
        
        // ESCENARIO 4: MONITOR con acceso limitado
        $monitor = User::updateOrCreate(
            ['email' => 'monitor@boukii-v5.com'],
            [
                'username' => 'monitor_test',
                'first_name' => 'Pierre',
                'last_name' => 'Moniteur',
                'password' => Hash::make('password123'),
                'type' => 'monitor',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        // Asociar monitor solo con escuela 2
        DB::table('school_users')->updateOrInsert(
            ['school_id' => 2, 'user_id' => $monitor->id],
            ['created_at' => now(), 'updated_at' => now()]
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
        
        // Asociar staff con escuelas 1 y 2
        foreach ([1, 2] as $schoolId) {
            DB::table('school_users')->updateOrInsert(
                ['school_id' => $schoolId, 'user_id' => $staff->id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
        $users->push($staff);
        
        return $users;
    }
    
    /**
     * Asignar roles en temporadas para todos los usuarios
     */
    private function assignUserSeasonRoles($users, $seasons)
    {
        foreach ($users as $user) {
            // Obtener escuelas del usuario
            $userSchoolIds = DB::table('school_users')
                ->where('user_id', $user->id)
                ->pluck('school_id');
            
            // Asignar rol en todas las temporadas activas de sus escuelas
            foreach ($seasons as $season) {
                if ($userSchoolIds->contains($season->school_id)) {
                    // Determinar rol basado en tipo de usuario
                    $role = match($user->type) {
                        'admin' => 'admin',
                        'monitor' => 'monitor',
                        'staff' => 'staff',
                        default => 'user'
                    };
                    
                    DB::table('user_season_roles')->updateOrInsert(
                        ['user_id' => $user->id, 'season_id' => $season->id],
                        ['role' => $role, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }
    }
    
    /**
     * Mostrar resumen de usuarios de test creados
     */
    private function displayTestUserSummary($users, $schools, $seasons)
    {
        $this->command->info('');
        $this->command->info('🎿 === V5 ENHANCED USERS TEST SUMMARY ===');
        $this->command->info('📊 Following v5-migration-plan.md requirements');
        $this->command->info('─────────────────────────────────────────────');
        
        foreach ($users as $user) {
            $userSchools = DB::table('school_users')
                ->join('schools', 'schools.id', '=', 'school_users.school_id')
                ->where('school_users.user_id', $user->id)
                ->pluck('schools.name')
                ->toArray();
            
            $userSeasons = DB::table('user_season_roles')
                ->join('seasons', 'seasons.id', '=', 'user_season_roles.season_id')
                ->where('user_season_roles.user_id', $user->id)
                ->where('seasons.is_active', 1)
                ->count();
                
            $this->command->info("👤 {$user->first_name} {$user->last_name} ({$user->email})");
            $this->command->info("   Role: {$user->type} | Schools: " . count($userSchools) . " | Active Seasons: {$userSeasons}");
            $this->command->info("   Schools: " . implode(', ', $userSchools));
            $this->command->info('');
        }
        
        $this->command->info('🏫 SCHOOLS STATUS:');
        foreach ($schools as $school) {
            $activeSeasons = Season::where('school_id', $school->id)->where('is_active', 1)->count();
            $this->command->info("   • {$school->name} - {$activeSeasons} active season(s)");
        }
        
        $this->command->info('');
        $this->command->info('🔑 TEST CREDENTIALS (all use password: password123):');
        $this->command->info('   • superadmin@boukii-v5.com - ALL schools access');
        $this->command->info('   • admin.veveyse@boukii-v5.com - ESS Veveyse ONLY');
        $this->command->info('   • admin.multi@boukii-v5.com - Multiple schools (1,3,4)');
        $this->command->info('   • monitor@boukii-v5.com - Monitor role');
        $this->command->info('   • staff@boukii-v5.com - Staff role');
        $this->command->info('─────────────────────────────────────────────');
        $this->command->info('✅ Ready for V5 authentication testing!');
        $this->command->info('🎯 All scenarios covered as per migration plan');
    }
}