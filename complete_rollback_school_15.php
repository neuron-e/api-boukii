<?php
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Configurar conexión a la base de datos
$capsule = new DB;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => '127.0.0.1',
    'database'  => 'boukii_pro',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== ROLLBACK COMPLETO DE SCHOOL 15 EN BOUKII_PRO ===" . PHP_EOL;
echo "ADVERTENCIA: Esto eliminará TODOS los datos relacionados con school_id = 15" . PHP_EOL;
echo "Presiona Enter para continuar o Ctrl+C para cancelar...";
fgets(STDIN);

$deleted_counts = [];

try {
    DB::beginTransaction();
    
    // NIVEL 5: RESERVAS Y PAGOS (eliminar primero por FK constraints)
    echo "\n=== NIVEL 5: RESERVAS Y PAGOS ===" . PHP_EOL;
    
    // Obtener IDs de bookings de la school 15
    $booking_ids = DB::table('bookings')->where('school_id', 15)->pluck('id')->toArray();
    if (!empty($booking_ids)) {
        // Eliminar voucher_logs si existe
        try {
            $count = DB::table('voucher_logs')->whereIn('booking_id', $booking_ids)->delete();
            $deleted_counts['voucher_logs'] = $count;
            echo "voucher_logs: $count eliminados" . PHP_EOL;
        } catch (Exception $e) {
            echo "voucher_logs: tabla no existe o ya limpia" . PHP_EOL;
        }
        
        // Eliminar payments
        $count = DB::table('payments')->whereIn('booking_id', $booking_ids)->delete();
        $deleted_counts['payments'] = $count;
        echo "payments: $count eliminados" . PHP_EOL;
        
        // Eliminar booking_users
        $count = DB::table('booking_users')->whereIn('booking_id', $booking_ids)->delete();
        $deleted_counts['booking_users'] = $count;
        echo "booking_users: $count eliminados" . PHP_EOL;
    }
    
    // Eliminar bookings
    $count = DB::table('bookings')->where('school_id', 15)->delete();
    $deleted_counts['bookings'] = $count;
    echo "bookings: $count eliminados" . PHP_EOL;
    
    // NIVEL 4: CERTIFICACIONES
    echo "\n=== NIVEL 4: CERTIFICACIONES ===" . PHP_EOL;
    
    // Obtener IDs de monitores, degrees relacionados con school 15
    $monitor_ids = DB::table('monitors_schools')->where('school_id', 15)->pluck('monitor_id')->toArray();
    $degree_ids = DB::table('degrees')->where('school_id', 15)->pluck('id')->toArray();
    
    if (!empty($monitor_ids)) {
        // Eliminar monitor_sport_authorized_degrees
        $count = DB::table('monitor_sport_authorized_degrees')->whereIn('monitor_id', $monitor_ids)->delete();
        $deleted_counts['monitor_sport_authorized_degrees'] = $count;
        echo "monitor_sport_authorized_degrees: $count eliminados" . PHP_EOL;
    }
    
    if (!empty($degree_ids)) {
        // Eliminar degrees_school_sport_goals
        try {
            $count = DB::table('degrees_school_sport_goals')->whereIn('degree_id', $degree_ids)->delete();
            $deleted_counts['degrees_school_sport_goals'] = $count;
            echo "degrees_school_sport_goals: $count eliminados" . PHP_EOL;
        } catch (Exception $e) {
            echo "degrees_school_sport_goals: tabla no existe o ya limpia" . PHP_EOL;
        }
    }
    
    // NIVEL 3: CURSOS
    echo "\n=== NIVEL 3: CURSOS ===" . PHP_EOL;
    
    // Obtener IDs de cursos de school 15
    $course_ids = DB::table('courses')->where('school_id', 15)->pluck('id')->toArray();
    if (!empty($course_ids)) {
        // Eliminar course_dates
        $count = DB::table('course_dates')->whereIn('course_id', $course_ids)->delete();
        $deleted_counts['course_dates'] = $count;
        echo "course_dates: $count eliminados" . PHP_EOL;
        
        // Obtener course_group_ids
        $course_group_ids = DB::table('course_groups')->whereIn('course_id', $course_ids)->pluck('id')->toArray();
        if (!empty($course_group_ids)) {
            // Eliminar course_subgroups
            $count = DB::table('course_subgroups')->whereIn('course_group_id', $course_group_ids)->delete();
            $deleted_counts['course_subgroups'] = $count;
            echo "course_subgroups: $count eliminados" . PHP_EOL;
        }
        
        // Eliminar course_groups
        $count = DB::table('course_groups')->whereIn('course_id', $course_ids)->delete();
        $deleted_counts['course_groups'] = $count;
        echo "course_groups: $count eliminados" . PHP_EOL;
    }
    
    // Eliminar courses
    $count = DB::table('courses')->where('school_id', 15)->delete();
    $deleted_counts['courses'] = $count;
    echo "courses: $count eliminados" . PHP_EOL;
    
    // NIVEL 2: RELACIONES PIVOT
    echo "\n=== NIVEL 2: RELACIONES PIVOT ===" . PHP_EOL;
    
    // Obtener IDs de clientes relacionados
    $client_ids = DB::table('clients_schools')->where('school_id', 15)->pluck('client_id')->toArray();
    
    if (!empty($client_ids)) {
        // Eliminar client_sports
        $count = DB::table('client_sports')->whereIn('client_id', $client_ids)->delete();
        $deleted_counts['client_sports'] = $count;
        echo "client_sports: $count eliminados" . PHP_EOL;
    }
    
    if (!empty($monitor_ids)) {
        // Eliminar monitor_sports
        $count = DB::table('monitor_sports')->whereIn('monitor_id', $monitor_ids)->delete();
        $deleted_counts['monitor_sports'] = $count;
        echo "monitor_sports: $count eliminados" . PHP_EOL;
    }
    
    // Eliminar todas las relaciones pivot de school 15
    $pivot_tables = [
        'clients_schools',
        'monitors_schools', 
        'school_users',
        'school_sports',
        'stations_schools',
        'school_colors',
        'school_salary_levels'
    ];
    
    foreach ($pivot_tables as $table) {
        try {
            $count = DB::table($table)->where('school_id', 15)->delete();
            $deleted_counts[$table] = $count;
            echo "$table: $count eliminados" . PHP_EOL;
        } catch (Exception $e) {
            echo "$table: error o no existe - " . $e->getMessage() . PHP_EOL;
        }
    }
    
    // NIVEL 1: TABLAS BASE
    echo "\n=== NIVEL 1: TABLAS BASE ===" . PHP_EOL;
    
    // Eliminar datos con school_id directo
    $direct_tables = [
        'degrees',
        'seasons', 
        'vouchers',
        'mails'
    ];
    
    foreach ($direct_tables as $table) {
        try {
            $count = DB::table($table)->where('school_id', 15)->delete();
            $deleted_counts[$table] = $count;
            echo "$table: $count eliminados" . PHP_EOL;
        } catch (Exception $e) {
            echo "$table: error - " . $e->getMessage() . PHP_EOL;
        }
    }
    
    // Finalmente eliminar la school
    $count = DB::table('schools')->where('id', 15)->delete();
    $deleted_counts['schools'] = $count;
    echo "schools: $count eliminados" . PHP_EOL;
    
    DB::commit();
    
    echo "\n=== RESUMEN DE ELIMINACIONES ===" . PHP_EOL;
    $total_deleted = 0;
    foreach ($deleted_counts as $table => $count) {
        echo "$table: $count records" . PHP_EOL;
        $total_deleted += $count;
    }
    echo "\nTOTAL ELIMINADO: $total_deleted records" . PHP_EOL;
    echo "ROLLBACK COMPLETADO EXITOSAMENTE" . PHP_EOL;
    
} catch (Exception $e) {
    DB::rollback();
    echo "\nERROR DURANTE ROLLBACK: " . $e->getMessage() . PHP_EOL;
    echo "TRANSACCIÓN REVERTIDA" . PHP_EOL;
}