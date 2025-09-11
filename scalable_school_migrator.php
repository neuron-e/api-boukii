<?php
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

class ScalableSchoolMigrator {
    private $source_db;
    private $target_db;
    private $source_school_id;
    private $target_school_id;
    private $id_mappings = [];
    private $migration_log = [];
    
    public function __construct($source_db, $target_db, $source_school_id, $target_school_id) {
        $this->source_db = $source_db;
        $this->target_db = $target_db;
        $this->source_school_id = $source_school_id;
        $this->target_school_id = $target_school_id;
        
        $this->setupDatabaseConnections();
    }
    
    private function setupDatabaseConnections() {
        $capsule = new DB;
        
        // Source connection (dev)
        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'database'  => $this->source_db,
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ], 'source');
        
        // Target connection (prod)
        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'database'  => $this->target_db,
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ], 'target');
        
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
    
    public function migrate() {
        echo "=== MIGRACIÓN ESCALABLE DE ESCUELA ===" . PHP_EOL;
        echo "FROM: {$this->source_db}.school_{$this->source_school_id}" . PHP_EOL;
        echo "TO: {$this->target_db}.school_{$this->target_school_id}" . PHP_EOL . PHP_EOL;
        
        try {
            DB::connection('target')->beginTransaction();
            
            // NIVEL 1: Migrar tablas base
            $this->migrateLevel1_BaseTables();
            
            // NIVEL 2: Migrar relaciones pivot básicas
            $this->migrateLevel2_PivotRelations();
            
            // NIVEL 3: Migrar estructura de cursos
            $this->migrateLevel3_CourseStructure();
            
            // NIVEL 4: Migrar certificaciones
            $this->migrateLevel4_Certifications();
            
            // NIVEL 5: Migrar reservas y pagos
            $this->migrateLevel5_BookingsPayments();
            
            // NIVEL 6: Migrar configuraciones
            $this->migrateLevel6_Configuration();
            
            DB::connection('target')->commit();
            
            $this->printMigrationSummary();
            echo "MIGRACIÓN COMPLETADA EXITOSAMENTE" . PHP_EOL;
            
        } catch (Exception $e) {
            DB::connection('target')->rollback();
            echo "ERROR DURANTE MIGRACIÓN: " . $e->getMessage() . PHP_EOL;
            echo "TRANSACCIÓN REVERTIDA" . PHP_EOL;
            throw $e;
        }
    }
    
    private function migrateLevel1_BaseTables() {
        echo "=== NIVEL 1: TABLAS BASE ===" . PHP_EOL;
        
        // 1. Migrar school principal
        $this->migrateSchool();
        
        // 2. Migrar usuarios
        $this->migrateUsers();
        
        // 3. Migrar clientes  
        $this->migrateClients();
        
        // 4. Migrar monitores
        $this->migrateMonitors();
        
        // 5. Migrar sports (solo los relacionados)
        $this->migrateSports();
        
        // 6. Migrar stations
        $this->migrateStations();
        
        // 7. Migrar degrees
        $this->migrateDegrees();
        
        // 8. Migrar seasons
        $this->migrateSeasons();
        
        echo "Nivel 1 completado." . PHP_EOL . PHP_EOL;
    }
    
    private function migrateSchool() {
        $school = DB::connection('source')->table('schools')->where('id', $this->source_school_id)->first();
        if (!$school) {
            throw new Exception("School {$this->source_school_id} not found in source database");
        }
        
        $school_data = (array) $school;
        $school_data['id'] = $this->target_school_id;
        unset($school_data['created_at'], $school_data['updated_at']);
        
        $inserted_id = DB::connection('target')->table('schools')->insertGetId($school_data);
        $this->id_mappings['schools'][$this->source_school_id] = $inserted_id;
        $this->log("schools", 1, "Migrated main school: {$school->name}");
    }
    
    private function migrateUsers() {
        // Obtener usuarios relacionados con la school
        $user_ids = DB::connection('source')->table('school_users')
            ->where('school_id', $this->source_school_id)
            ->pluck('user_id')->toArray();
            
        if (empty($user_ids)) {
            $this->log("users", 0, "No users found");
            return;
        }
        
        $users = DB::connection('source')->table('users')
            ->whereIn('id', $user_ids)
            ->whereNull('deleted_at')
            ->get();
            
        $migrated_count = 0;
        foreach ($users as $user) {
            $user_data = (array) $user;
            $original_id = $user_data['id'];
            unset($user_data['id']);
            
            // Verificar si ya existe por email
            $existing = DB::connection('target')->table('users')->where('email', $user->email)->first();
            if ($existing) {
                $this->id_mappings['users'][$original_id] = $existing->id;
                $this->log("users", 0, "User already exists: {$user->email}");
            } else {
                $inserted_id = DB::connection('target')->table('users')->insertGetId($user_data);
                $this->id_mappings['users'][$original_id] = $inserted_id;
                $migrated_count++;
            }
        }
        
        $this->log("users", $migrated_count, "Migrated users");
    }
    
    private function migrateClients() {
        $client_ids = DB::connection('source')->table('clients_schools')
            ->where('school_id', $this->source_school_id)
            ->pluck('client_id')->toArray();
            
        if (empty($client_ids)) {
            $this->log("clients", 0, "No clients found");
            return;
        }
        
        $clients = DB::connection('source')->table('clients')
            ->whereIn('id', $client_ids)
            ->whereNull('deleted_at')
            ->get();
            
        $migrated_count = 0;
        foreach ($clients as $client) {
            $client_data = (array) $client;
            $original_id = $client_data['id'];
            unset($client_data['id']);
            
            // Mapear foreign keys si existen
            if (isset($client_data['user_id']) && isset($this->id_mappings['users'][$client_data['user_id']])) {
                $client_data['user_id'] = $this->id_mappings['users'][$client_data['user_id']];
            }
            
            $inserted_id = DB::connection('target')->table('clients')->insertGetId($client_data);
            $this->id_mappings['clients'][$original_id] = $inserted_id;
            $migrated_count++;
        }
        
        $this->log("clients", $migrated_count, "Migrated clients");
    }
    
    private function migrateMonitors() {
        $monitor_ids = DB::connection('source')->table('monitors_schools')
            ->where('school_id', $this->source_school_id)
            ->pluck('monitor_id')->toArray();
            
        if (empty($monitor_ids)) {
            $this->log("monitors", 0, "No monitors found");
            return;
        }
        
        $monitors = DB::connection('source')->table('monitors')
            ->whereIn('id', $monitor_ids)
            ->whereNull('deleted_at')
            ->get();
            
        $migrated_count = 0;
        foreach ($monitors as $monitor) {
            $monitor_data = (array) $monitor;
            $original_id = $monitor_data['id'];
            unset($monitor_data['id']);
            
            // Mapear user_id si existe
            if (isset($monitor_data['user_id']) && isset($this->id_mappings['users'][$monitor_data['user_id']])) {
                $monitor_data['user_id'] = $this->id_mappings['users'][$monitor_data['user_id']];
            } elseif (isset($monitor_data['user_id'])) {
                // Si no está mapeado, buscar por email o poner null
                $monitor_data['user_id'] = null;
            }
            
            $inserted_id = DB::connection('target')->table('monitors')->insertGetId($monitor_data);
            $this->id_mappings['monitors'][$original_id] = $inserted_id;
            $migrated_count++;
        }
        
        $this->log("monitors", $migrated_count, "Migrated monitors");
    }
    
    private function migrateSports() {
        $sport_ids = DB::connection('source')->table('school_sports')
            ->where('school_id', $this->source_school_id)
            ->pluck('sport_id')->toArray();
            
        if (empty($sport_ids)) {
            $this->log("sports", 0, "No sports found");
            return;
        }
        
        $sports = DB::connection('source')->table('sports')
            ->whereIn('id', $sport_ids)
            ->whereNull('deleted_at')
            ->get();
            
        $migrated_count = 0;
        foreach ($sports as $sport) {
            // Verificar si ya existe por name
            $existing = DB::connection('target')->table('sports')->where('name', $sport->name)->first();
            if ($existing) {
                $this->id_mappings['sports'][$sport->id] = $existing->id;
            } else {
                $sport_data = (array) $sport;
                $original_id = $sport_data['id'];
                unset($sport_data['id']);
                
                $inserted_id = DB::connection('target')->table('sports')->insertGetId($sport_data);
                $this->id_mappings['sports'][$original_id] = $inserted_id;
                $migrated_count++;
            }
        }
        
        $this->log("sports", $migrated_count, "Migrated sports");
    }
    
    private function migrateStations() {
        $station_ids = DB::connection('source')->table('stations_schools')
            ->where('school_id', $this->source_school_id)
            ->pluck('station_id')->toArray();
            
        if (empty($station_ids)) {
            $this->log("stations", 0, "No stations found");
            return;
        }
        
        $stations = DB::connection('source')->table('stations')
            ->whereIn('id', $station_ids)
            ->get();
            
        $migrated_count = 0;
        foreach ($stations as $station) {
            $existing = DB::connection('target')->table('stations')->where('name', $station->name)->first();
            if ($existing) {
                $this->id_mappings['stations'][$station->id] = $existing->id;
            } else {
                $station_data = (array) $station;
                $original_id = $station_data['id'];
                unset($station_data['id']);
                
                $inserted_id = DB::connection('target')->table('stations')->insertGetId($station_data);
                $this->id_mappings['stations'][$original_id] = $inserted_id;
                $migrated_count++;
            }
        }
        
        $this->log("stations", $migrated_count, "Migrated stations");
    }
    
    private function migrateDegrees() {
        $degrees = DB::connection('source')->table('degrees')
            ->where('school_id', $this->source_school_id)
            ->whereNull('deleted_at')
            ->get();
            
        $migrated_count = 0;
        foreach ($degrees as $degree) {
            $degree_data = (array) $degree;
            $original_id = $degree_data['id'];
            $degree_data['school_id'] = $this->target_school_id;
            unset($degree_data['id']);
            
            // Mapear sport_id si existe
            if (isset($degree_data['sport_id']) && isset($this->id_mappings['sports'][$degree_data['sport_id']])) {
                $degree_data['sport_id'] = $this->id_mappings['sports'][$degree_data['sport_id']];
            }
            
            $inserted_id = DB::connection('target')->table('degrees')->insertGetId($degree_data);
            $this->id_mappings['degrees'][$original_id] = $inserted_id;
            $migrated_count++;
        }
        
        $this->log("degrees", $migrated_count, "Migrated degrees");
    }
    
    private function migrateSeasons() {
        $seasons = DB::connection('source')->table('seasons')
            ->where('school_id', $this->source_school_id)
            ->whereNull('deleted_at')
            ->get();
            
        $migrated_count = 0;
        foreach ($seasons as $season) {
            $season_data = (array) $season;
            $original_id = $season_data['id'];
            $season_data['school_id'] = $this->target_school_id;
            unset($season_data['id']);
            
            $inserted_id = DB::connection('target')->table('seasons')->insertGetId($season_data);
            $this->id_mappings['seasons'][$original_id] = $inserted_id;
            $migrated_count++;
        }
        
        $this->log("seasons", $migrated_count, "Migrated seasons");
    }
    
    private function migrateLevel2_PivotRelations() {
        echo "=== NIVEL 2: RELACIONES PIVOT ===" . PHP_EOL;
        
        $this->migratePivotTable('school_users', 'user_id', 'users');
        $this->migratePivotTable('clients_schools', 'client_id', 'clients');
        $this->migratePivotTable('monitors_schools', 'monitor_id', 'monitors');
        $this->migratePivotTable('school_sports', 'sport_id', 'sports');
        $this->migratePivotTable('stations_schools', 'station_id', 'stations');
        
        $this->migrateClientSports();
        $this->migrateMonitorSports();
        $this->migrateSchoolColors();
        $this->migrateSchoolSalaryLevels();
        
        echo "Nivel 2 completado." . PHP_EOL . PHP_EOL;
    }
    
    private function migratePivotTable($table_name, $foreign_key, $mapping_key) {
        $records = DB::connection('source')->table($table_name)
            ->where('school_id', $this->source_school_id)
            ->get();
            
        $migrated_count = 0;
        foreach ($records as $record) {
            $record_data = (array) $record;
            $record_data['school_id'] = $this->target_school_id;
            
            if (isset($this->id_mappings[$mapping_key][$record_data[$foreign_key]])) {
                $record_data[$foreign_key] = $this->id_mappings[$mapping_key][$record_data[$foreign_key]];
                
                DB::connection('target')->table($table_name)->insert($record_data);
                $migrated_count++;
            }
        }
        
        $this->log($table_name, $migrated_count, "Migrated pivot relations");
    }
    
    private function migrateClientSports() {
        if (empty($this->id_mappings['clients'])) return;
        
        $client_sports = DB::connection('source')->table('client_sports')
            ->whereIn('client_id', array_keys($this->id_mappings['clients']))
            ->get();
            
        $migrated_count = 0;
        foreach ($client_sports as $cs) {
            $cs_data = (array) $cs;
            if (isset($this->id_mappings['clients'][$cs_data['client_id']]) && 
                isset($this->id_mappings['sports'][$cs_data['sport_id']])) {
                
                $cs_data['client_id'] = $this->id_mappings['clients'][$cs_data['client_id']];
                $cs_data['sport_id'] = $this->id_mappings['sports'][$cs_data['sport_id']];
                
                DB::connection('target')->table('client_sports')->insert($cs_data);
                $migrated_count++;
            }
        }
        
        $this->log("client_sports", $migrated_count, "Migrated client sports");
    }
    
    private function migrateMonitorSports() {
        if (empty($this->id_mappings['monitors'])) return;
        
        $monitor_sports = DB::connection('source')->table('monitor_sports')
            ->whereIn('monitor_id', array_keys($this->id_mappings['monitors']))
            ->get();
            
        $migrated_count = 0;
        foreach ($monitor_sports as $ms) {
            $ms_data = (array) $ms;
            if (isset($this->id_mappings['monitors'][$ms_data['monitor_id']]) && 
                isset($this->id_mappings['sports'][$ms_data['sport_id']])) {
                
                $ms_data['monitor_id'] = $this->id_mappings['monitors'][$ms_data['monitor_id']];
                $ms_data['sport_id'] = $this->id_mappings['sports'][$ms_data['sport_id']];
                
                DB::connection('target')->table('monitor_sports')->insert($ms_data);
                $migrated_count++;
            }
        }
        
        $this->log("monitor_sports", $migrated_count, "Migrated monitor sports");
    }
    
    private function migrateSchoolColors() {
        $colors = DB::connection('source')->table('school_colors')
            ->where('school_id', $this->source_school_id)
            ->get();
            
        $migrated_count = 0;
        foreach ($colors as $color) {
            $color_data = (array) $color;
            $color_data['school_id'] = $this->target_school_id;
            
            DB::connection('target')->table('school_colors')->insert($color_data);
            $migrated_count++;
        }
        
        $this->log("school_colors", $migrated_count, "Migrated school colors");
    }
    
    private function migrateSchoolSalaryLevels() {
        $levels = DB::connection('source')->table('school_salary_levels')
            ->where('school_id', $this->source_school_id)
            ->get();
            
        $migrated_count = 0;
        foreach ($levels as $level) {
            $level_data = (array) $level;
            $level_data['school_id'] = $this->target_school_id;
            
            DB::connection('target')->table('school_salary_levels')->insert($level_data);
            $migrated_count++;
        }
        
        $this->log("school_salary_levels", $migrated_count, "Migrated salary levels");
    }
    
    private function migrateLevel3_CourseStructure() {
        echo "=== NIVEL 3: ESTRUCTURA DE CURSOS ===" . PHP_EOL;
        
        $this->migrateCourses();
        $this->migrateCourseGroups();
        $this->migrateCourseSubgroups();
        $this->migrateCourseDates();
        
        echo "Nivel 3 completado." . PHP_EOL . PHP_EOL;
    }
    
    private function migrateCourses() {
        $courses = DB::connection('source')->table('courses')
            ->where('school_id', $this->source_school_id)
            ->whereNull('deleted_at')
            ->get();
            
        $migrated_count = 0;
        foreach ($courses as $course) {
            $course_data = (array) $course;
            $original_id = $course_data['id'];
            $course_data['school_id'] = $this->target_school_id;
            unset($course_data['id']);
            
            // Mapear foreign keys
            if (isset($course_data['sport_id']) && isset($this->id_mappings['sports'][$course_data['sport_id']])) {
                $course_data['sport_id'] = $this->id_mappings['sports'][$course_data['sport_id']];
            }
            if (isset($course_data['season_id']) && isset($this->id_mappings['seasons'][$course_data['season_id']])) {
                $course_data['season_id'] = $this->id_mappings['seasons'][$course_data['season_id']];
            }
            
            $inserted_id = DB::connection('target')->table('courses')->insertGetId($course_data);
            $this->id_mappings['courses'][$original_id] = $inserted_id;
            $migrated_count++;
        }
        
        $this->log("courses", $migrated_count, "Migrated courses");
    }
    
    private function migrateCourseGroups() {
        if (empty($this->id_mappings['courses'])) return;
        
        $course_groups = DB::connection('source')->table('course_groups')
            ->whereIn('course_id', array_keys($this->id_mappings['courses']))
            ->get();
            
        $migrated_count = 0;
        foreach ($course_groups as $group) {
            $group_data = (array) $group;
            $original_id = $group_data['id'];
            unset($group_data['id']);
            
            if (isset($this->id_mappings['courses'][$group_data['course_id']])) {
                $group_data['course_id'] = $this->id_mappings['courses'][$group_data['course_id']];
                
                $inserted_id = DB::connection('target')->table('course_groups')->insertGetId($group_data);
                $this->id_mappings['course_groups'][$original_id] = $inserted_id;
                $migrated_count++;
            }
        }
        
        $this->log("course_groups", $migrated_count, "Migrated course groups");
    }
    
    private function migrateCourseSubgroups() {
        if (empty($this->id_mappings['course_groups'])) return;
        
        $course_subgroups = DB::connection('source')->table('course_subgroups')
            ->whereIn('course_group_id', array_keys($this->id_mappings['course_groups']))
            ->get();
            
        $migrated_count = 0;
        foreach ($course_subgroups as $subgroup) {
            $subgroup_data = (array) $subgroup;
            $original_id = $subgroup_data['id'];
            unset($subgroup_data['id']);
            
            if (isset($this->id_mappings['course_groups'][$subgroup_data['course_group_id']])) {
                $subgroup_data['course_group_id'] = $this->id_mappings['course_groups'][$subgroup_data['course_group_id']];
                
                $inserted_id = DB::connection('target')->table('course_subgroups')->insertGetId($subgroup_data);
                $this->id_mappings['course_subgroups'][$original_id] = $inserted_id;
                $migrated_count++;
            }
        }
        
        $this->log("course_subgroups", $migrated_count, "Migrated course subgroups");
    }
    
    private function migrateCourseDates() {
        if (empty($this->id_mappings['courses'])) return;
        
        $course_dates = DB::connection('source')->table('course_dates')
            ->whereIn('course_id', array_keys($this->id_mappings['courses']))
            ->get();
            
        $migrated_count = 0;
        foreach ($course_dates as $date) {
            $date_data = (array) $date;
            $original_id = $date_data['id'];
            unset($date_data['id']);
            
            if (isset($this->id_mappings['courses'][$date_data['course_id']])) {
                $date_data['course_id'] = $this->id_mappings['courses'][$date_data['course_id']];
                
                $inserted_id = DB::connection('target')->table('course_dates')->insertGetId($date_data);
                $this->id_mappings['course_dates'][$original_id] = $inserted_id;
                $migrated_count++;
            }
        }
        
        $this->log("course_dates", $migrated_count, "Migrated course dates");
    }
    
    private function migrateLevel4_Certifications() {
        echo "=== NIVEL 4: CERTIFICACIONES ===" . PHP_EOL;
        
        $this->migrateMonitorSportAuthorizedDegrees();
        $this->migrateDegreesSchoolSportGoals();
        
        echo "Nivel 4 completado." . PHP_EOL . PHP_EOL;
    }
    
    private function migrateMonitorSportAuthorizedDegrees() {
        if (empty($this->id_mappings['monitors'])) return;
        
        $degrees = DB::connection('source')->table('monitor_sport_authorized_degrees')
            ->whereIn('monitor_id', array_keys($this->id_mappings['monitors']))
            ->get();
            
        $migrated_count = 0;
        foreach ($degrees as $degree) {
            $degree_data = (array) $degree;
            unset($degree_data['id']);
            
            if (isset($this->id_mappings['monitors'][$degree_data['monitor_id']]) &&
                isset($this->id_mappings['sports'][$degree_data['sport_id']]) &&
                isset($this->id_mappings['degrees'][$degree_data['degree_id']])) {
                
                $degree_data['monitor_id'] = $this->id_mappings['monitors'][$degree_data['monitor_id']];
                $degree_data['sport_id'] = $this->id_mappings['sports'][$degree_data['sport_id']];
                $degree_data['degree_id'] = $this->id_mappings['degrees'][$degree_data['degree_id']];
                
                DB::connection('target')->table('monitor_sport_authorized_degrees')->insert($degree_data);
                $migrated_count++;
            }
        }
        
        $this->log("monitor_sport_authorized_degrees", $migrated_count, "Migrated monitor degrees");
    }
    
    private function migrateDegreesSchoolSportGoals() {
        if (empty($this->id_mappings['degrees'])) return;
        
        try {
            $goals = DB::connection('source')->table('degrees_school_sport_goals')
                ->whereIn('degree_id', array_keys($this->id_mappings['degrees']))
                ->get();
                
            $migrated_count = 0;
            foreach ($goals as $goal) {
                $goal_data = (array) $goal;
                unset($goal_data['id']);
                
                if (isset($this->id_mappings['degrees'][$goal_data['degree_id']]) &&
                    isset($this->id_mappings['sports'][$goal_data['sport_id']])) {
                    
                    $goal_data['degree_id'] = $this->id_mappings['degrees'][$goal_data['degree_id']];
                    $goal_data['sport_id'] = $this->id_mappings['sports'][$goal_data['sport_id']];
                    $goal_data['school_id'] = $this->target_school_id;
                    
                    DB::connection('target')->table('degrees_school_sport_goals')->insert($goal_data);
                    $migrated_count++;
                }
            }
            
            $this->log("degrees_school_sport_goals", $migrated_count, "Migrated degree goals");
        } catch (Exception $e) {
            $this->log("degrees_school_sport_goals", 0, "Table not found or accessible");
        }
    }
    
    private function migrateLevel5_BookingsPayments() {
        echo "=== NIVEL 5: RESERVAS Y PAGOS ===" . PHP_EOL;
        
        $this->migrateBookings();
        $this->migrateBookingUsers();
        $this->migratePayments();
        $this->migrateVouchers();
        
        echo "Nivel 5 completado." . PHP_EOL . PHP_EOL;
    }
    
    private function migrateBookings() {
        $bookings = DB::connection('source')->table('bookings')
            ->where('school_id', $this->source_school_id)
            ->whereNull('deleted_at')
            ->get();
            
        $migrated_count = 0;
        foreach ($bookings as $booking) {
            $booking_data = (array) $booking;
            $original_id = $booking_data['id'];
            $booking_data['school_id'] = $this->target_school_id;
            unset($booking_data['id']);
            
            // Mapear foreign keys
            if (isset($booking_data['client_id']) && isset($this->id_mappings['clients'][$booking_data['client_id']])) {
                $booking_data['client_id'] = $this->id_mappings['clients'][$booking_data['client_id']];
            }
            if (isset($booking_data['course_id']) && isset($this->id_mappings['courses'][$booking_data['course_id']])) {
                $booking_data['course_id'] = $this->id_mappings['courses'][$booking_data['course_id']];
            }
            if (isset($booking_data['season_id']) && isset($this->id_mappings['seasons'][$booking_data['season_id']])) {
                $booking_data['season_id'] = $this->id_mappings['seasons'][$booking_data['season_id']];
            }
            
            $inserted_id = DB::connection('target')->table('bookings')->insertGetId($booking_data);
            $this->id_mappings['bookings'][$original_id] = $inserted_id;
            $migrated_count++;
        }
        
        $this->log("bookings", $migrated_count, "Migrated bookings");
    }
    
    private function migrateBookingUsers() {
        if (empty($this->id_mappings['bookings'])) return;
        
        $booking_users = DB::connection('source')->table('booking_users')
            ->whereIn('booking_id', array_keys($this->id_mappings['bookings']))
            ->get();
            
        $migrated_count = 0;
        foreach ($booking_users as $bu) {
            $bu_data = (array) $bu;
            unset($bu_data['id']);
            
            if (isset($this->id_mappings['bookings'][$bu_data['booking_id']])) {
                $bu_data['booking_id'] = $this->id_mappings['bookings'][$bu_data['booking_id']];
                
                if (isset($bu_data['client_id']) && isset($this->id_mappings['clients'][$bu_data['client_id']])) {
                    $bu_data['client_id'] = $this->id_mappings['clients'][$bu_data['client_id']];
                }
                
                DB::connection('target')->table('booking_users')->insert($bu_data);
                $migrated_count++;
            }
        }
        
        $this->log("booking_users", $migrated_count, "Migrated booking users");
    }
    
    private function migratePayments() {
        if (empty($this->id_mappings['bookings'])) return;
        
        $payments = DB::connection('source')->table('payments')
            ->whereIn('booking_id', array_keys($this->id_mappings['bookings']))
            ->get();
            
        $migrated_count = 0;
        foreach ($payments as $payment) {
            $payment_data = (array) $payment;
            unset($payment_data['id']);
            
            if (isset($this->id_mappings['bookings'][$payment_data['booking_id']])) {
                $payment_data['booking_id'] = $this->id_mappings['bookings'][$payment_data['booking_id']];
                
                DB::connection('target')->table('payments')->insert($payment_data);
                $migrated_count++;
            }
        }
        
        $this->log("payments", $migrated_count, "Migrated payments");
    }
    
    private function migrateVouchers() {
        $vouchers = DB::connection('source')->table('vouchers')
            ->where('school_id', $this->source_school_id)
            ->whereNull('deleted_at')
            ->get();
            
        $migrated_count = 0;
        foreach ($vouchers as $voucher) {
            $voucher_data = (array) $voucher;
            $original_id = $voucher_data['id'];
            $voucher_data['school_id'] = $this->target_school_id;
            unset($voucher_data['id']);
            
            $inserted_id = DB::connection('target')->table('vouchers')->insertGetId($voucher_data);
            $this->id_mappings['vouchers'][$original_id] = $inserted_id;
            $migrated_count++;
        }
        
        $this->log("vouchers", $migrated_count, "Migrated vouchers");
    }
    
    private function migrateLevel6_Configuration() {
        echo "=== NIVEL 6: CONFIGURACIÓN ===" . PHP_EOL;
        
        $this->migrateMails();
        
        echo "Nivel 6 completado." . PHP_EOL . PHP_EOL;
    }
    
    private function migrateMails() {
        $mails = DB::connection('source')->table('mails')
            ->where('school_id', $this->source_school_id)
            ->whereNull('deleted_at')
            ->get();
            
        $migrated_count = 0;
        foreach ($mails as $mail) {
            $mail_data = (array) $mail;
            $mail_data['school_id'] = $this->target_school_id;
            unset($mail_data['id']);
            
            DB::connection('target')->table('mails')->insert($mail_data);
            $migrated_count++;
        }
        
        $this->log("mails", $migrated_count, "Migrated mail templates");
    }
    
    private function log($table, $count, $message) {
        $this->migration_log[] = "$table: $count - $message";
        echo "$table: $count records migrated" . PHP_EOL;
    }
    
    private function printMigrationSummary() {
        echo "\n=== RESUMEN DE MIGRACIÓN ===" . PHP_EOL;
        $total_migrated = 0;
        
        foreach ($this->migration_log as $log_entry) {
            echo $log_entry . PHP_EOL;
            preg_match('/(\d+)/', $log_entry, $matches);
            if (isset($matches[1])) {
                $total_migrated += intval($matches[1]);
            }
        }
        
        echo "\nTOTAL RECORDS MIGRATED: $total_migrated" . PHP_EOL;
        echo "ID MAPPINGS CREATED: " . count($this->id_mappings, COUNT_RECURSIVE) . PHP_EOL;
    }
}

// Ejecutar migración si se llama directamente
if (php_sapi_name() === 'cli' && basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $source_db = $argv[1] ?? 'boukii_dev';
    $target_db = $argv[2] ?? 'boukii_pro';
    $source_school_id = $argv[3] ?? 13;
    $target_school_id = $argv[4] ?? 15;
    
    echo "Iniciando migración escalable..." . PHP_EOL;
    echo "Source: $source_db.school_$source_school_id" . PHP_EOL;
    echo "Target: $target_db.school_$target_school_id" . PHP_EOL;
    echo "Presiona Enter para continuar o Ctrl+C para cancelar...";
    fgets(STDIN);
    
    $migrator = new ScalableSchoolMigrator($source_db, $target_db, $source_school_id, $target_school_id);
    $migrator->migrate();
}