<?php
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Configurar conexiones a ambas bases de datos
$capsule = new DB;

// Conexión PROD
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

// Conexión DEV
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

echo "=== MIGRANDO COURSE_SUBGROUPS FALTANTES ===\n";

try {
    DB::connection('boukii_pro')->beginTransaction();
    
    // Mapeo de cursos DEV -> PROD
    $course_mappings = [
        428 => 432, // Gruppenkurse GT KW1
        429 => 433, // Gruppen Kurse GT KW2
        430 => 434  // Privat
    ];
    
    $total_migrated = 0;
    
    foreach ($course_mappings as $dev_course_id => $prod_course_id) {
        echo "\n--- Procesando curso $dev_course_id -> $prod_course_id ---\n";
        
        // Obtener course_groups en DEV para este curso
        $dev_course_groups = DB::connection('boukii_dev')
            ->table('course_groups')
            ->where('course_id', $dev_course_id)
            ->get();
        
        echo "Course groups en DEV: " . $dev_course_groups->count() . "\n";
        
        if ($dev_course_groups->count() === 0) {
            echo "No hay grupos para migrar en este curso.\n";
            continue;
        }
        
        // Para cada grupo en DEV, encontrar su correspondiente en PROD y migrar subgrupos
        foreach ($dev_course_groups as $dev_group) {
            // Buscar el grupo correspondiente en PROD por course_id y algún identificador único
            $prod_group = DB::connection('boukii_pro')
                ->table('course_groups')
                ->where('course_id', $prod_course_id)
                ->where('start_date', $dev_group->start_date)
                ->where('end_date', $dev_group->end_date)
                ->first();
            
            if (!$prod_group) {
                echo "  WARN: No se encontró grupo correspondiente para DEV ID {$dev_group->id}\n";
                continue;
            }
            
            // Verificar si ya tiene subgrupos en PROD
            $existing_subgroups = DB::connection('boukii_pro')
                ->table('course_subgroups')
                ->where('course_group_id', $prod_group->id)
                ->count();
            
            if ($existing_subgroups > 0) {
                echo "  Grupo {$prod_group->id} ya tiene $existing_subgroups subgrupos\n";
                continue;
            }
            
            // Obtener subgrupos de DEV
            $dev_subgroups = DB::connection('boukii_dev')
                ->table('course_subgroups')
                ->where('course_group_id', $dev_group->id)
                ->get();
            
            echo "  Migrando " . $dev_subgroups->count() . " subgrupos para grupo {$prod_group->id}\n";
            
            // Migrar cada subgrupo
            foreach ($dev_subgroups as $dev_subgroup) {
                $subgroup_data = [
                    'course_group_id' => $prod_group->id,
                    'name' => $dev_subgroup->name,
                    'description' => $dev_subgroup->description,
                    'min_age' => $dev_subgroup->min_age,
                    'max_age' => $dev_subgroup->max_age,
                    'min_participants' => $dev_subgroup->min_participants,
                    'max_participants' => $dev_subgroup->max_participants,
                    'created_at' => $dev_subgroup->created_at,
                    'updated_at' => $dev_subgroup->updated_at,
                    'deleted_at' => $dev_subgroup->deleted_at
                ];
                
                // Remover campos que podrían no existir
                $subgroup_data = array_filter($subgroup_data, function($value) {
                    return $value !== null;
                });
                
                DB::connection('boukii_pro')->table('course_subgroups')->insert($subgroup_data);
                $total_migrated++;
            }
        }
    }
    
    DB::connection('boukii_pro')->commit();
    
    echo "\n✅ MIGRACIÓN COMPLETADA:\n";
    echo "Total course_subgroups migrados: $total_migrated\n";
    
    // Verificación final
    echo "\n=== VERIFICACIÓN FINAL ===\n";
    foreach ($course_mappings as $dev_course_id => $prod_course_id) {
        $prod_groups_count = DB::connection('boukii_prod')
            ->table('course_groups')
            ->where('course_id', $prod_course_id)
            ->count();
        
        $prod_subgroups_count = DB::connection('boukii_pro')
            ->table('course_subgroups')
            ->whereIn('course_group_id', 
                DB::connection('boukii_pro')
                    ->table('course_groups')
                    ->where('course_id', $prod_course_id)
                    ->pluck('id')
            )
            ->count();
        
        echo "Curso $prod_course_id: $prod_groups_count grupos, $prod_subgroups_count subgrupos\n";
    }

} catch (Exception $e) {
    DB::connection('boukii_pro')->rollback();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}