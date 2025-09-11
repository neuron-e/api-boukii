<?php
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Configurar conexiones
$capsule = new DB;

$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => '127.0.0.1',
    'database'  => 'boukii_pro',
    'username'  => 'root',
    'password'  => 'Manolo11.',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
], 'boukii_pro');

$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => '127.0.0.1',
    'database'  => 'boukii_dev',
    'username'  => 'root',
    'password'  => 'Manolo11.',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
], 'boukii_dev');

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== CORRIGIENDO MONITOR_ID EN COURSE_SUBGROUPS ===\n";

try {
    DB::connection('boukii_pro')->beginTransaction();
    
    // Mapeo de cursos DEV -> PROD
    $course_mappings = [
        428 => 432, // Gruppenkurse GT KW1
        429 => 433, // Gruppen Kurse GT KW2
        430 => 434  // Privat
    ];
    
    // Crear mapeo de monitores DEV -> PROD usando email
    echo "Creando mapeo de monitores...\n";
    $monitor_mappings = [];
    
    $dev_monitors = DB::connection('boukii_dev')
        ->table('monitors')
        ->join('monitors_schools', 'monitors.id', '=', 'monitors_schools.monitor_id')
        ->where('monitors_schools.school_id', 13)
        ->select('monitors.*')
        ->get();
    
    foreach ($dev_monitors as $dev_monitor) {
        $prod_monitor = DB::connection('boukii_pro')
            ->table('monitors')
            ->join('monitors_schools', 'monitors.id', '=', 'monitors_schools.monitor_id')
            ->where('monitors_schools.school_id', 15)
            ->where('monitors.email', $dev_monitor->email)
            ->select('monitors.*')
            ->first();
        
        if ($prod_monitor) {
            $monitor_mappings[$dev_monitor->id] = $prod_monitor->id;
        }
    }
    
    echo "Monitores mapeados: " . count($monitor_mappings) . "\n";
    
    $total_fixed = 0;
    
    foreach ($course_mappings as $dev_course_id => $prod_course_id) {
        echo "\n--- Procesando curso $dev_course_id -> $prod_course_id ---\n";
        
        // Obtener grupos DEV y PROD para hacer matching
        $dev_groups = DB::connection('boukii_dev')
            ->table('course_groups')
            ->where('course_id', $dev_course_id)
            ->whereNull('deleted_at')
            ->get();
        
        $prod_groups = DB::connection('boukii_pro')
            ->table('course_groups')
            ->where('course_id', $prod_course_id)
            ->whereNull('deleted_at')
            ->get();
        
        echo "Grupos DEV: " . $dev_groups->count() . ", PROD: " . $prod_groups->count() . "\n";
        
        // Para cada grupo DEV, buscar correspondiente en PROD
        foreach ($dev_groups as $dev_group) {
            $prod_group = $prod_groups->first(function($group) use ($dev_group) {
                return $group->start_date === $dev_group->start_date 
                    && $group->end_date === $dev_group->end_date
                    && $group->start_time === $dev_group->start_time
                    && $group->end_time === $dev_group->end_time;
            });
            
            if (!$prod_group) {
                echo "  WARN: No se encontró grupo PROD para DEV {$dev_group->id}\n";
                continue;
            }
            
            // Obtener subgrupos de ambos
            $dev_subgroups = DB::connection('boukii_dev')
                ->table('course_subgroups')
                ->where('course_group_id', $dev_group->id)
                ->whereNull('deleted_at')
                ->get();
            
            $prod_subgroups = DB::connection('boukii_pro')
                ->table('course_subgroups')
                ->where('course_group_id', $prod_group->id)
                ->whereNull('deleted_at')
                ->get();
            
            // Actualizar monitor_id en subgrupos PROD
            foreach ($dev_subgroups as $index => $dev_subgroup) {
                if (isset($prod_subgroups[$index]) && $dev_subgroup->monitor_id) {
                    $prod_subgroup = $prod_subgroups[$index];
                    
                    if (isset($monitor_mappings[$dev_subgroup->monitor_id])) {
                        $new_monitor_id = $monitor_mappings[$dev_subgroup->monitor_id];
                        
                        DB::connection('boukii_pro')
                            ->table('course_subgroups')
                            ->where('id', $prod_subgroup->id)
                            ->update([
                                'monitor_id' => $new_monitor_id,
                                'updated_at' => now()
                            ]);
                        
                        echo "    Subgrupo {$prod_subgroup->id}: monitor {$new_monitor_id}\n";
                        $total_fixed++;
                    } else {
                        echo "    WARN: Monitor DEV {$dev_subgroup->monitor_id} no encontrado en mapeo\n";
                    }
                }
            }
        }
    }
    
    DB::connection('boukii_pro')->commit();
    
    echo "\n✅ MONITOR_ID CORREGIDOS:\n";
    echo "Total subgrupos con monitor asignado: $total_fixed\n";
    
    // Verificación final
    echo "\n=== VERIFICACIÓN FINAL ===\n";
    foreach ($course_mappings as $dev_course_id => $prod_course_id) {
        $monitors_assigned = DB::connection('boukii_pro')
            ->table('course_subgroups')
            ->whereIn('course_group_id',
                DB::connection('boukii_pro')
                    ->table('course_groups')
                    ->where('course_id', $prod_course_id)
                    ->pluck('id')
            )
            ->whereNotNull('monitor_id')
            ->whereNull('deleted_at')
            ->count();
        
        $dev_monitors_assigned = DB::connection('boukii_dev')
            ->table('course_subgroups')
            ->whereIn('course_group_id',
                DB::connection('boukii_dev')
                    ->table('course_groups')
                    ->where('course_id', $dev_course_id)
                    ->pluck('id')
            )
            ->whereNotNull('monitor_id')
            ->whereNull('deleted_at')
            ->count();
        
        echo "Curso $prod_course_id: $monitors_assigned monitores asignados (DEV tenía: $dev_monitors_assigned)\n";
    }

} catch (Exception $e) {
    DB::connection('boukii_pro')->rollback();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}