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

echo "=== ANÁLISIS DETALLADO DISCREPANCIAS CHURWALDEN ===\n\n";

// Enfoque específico en el curso Gruppen KW2 que tiene discrepancias
$dev_course_id = 429;  // Gruppen Kurse GT KW2 en DEV
$prod_course_id = 433; // Gruppen Kurse GT KW2 en PROD

echo "FOCO: Curso Gruppen KW2 (DEV $dev_course_id vs PROD $prod_course_id)\n";
echo "API reporta: DEV=1 reserva/120 plazas vs PROD=0 reservas/60 plazas\n\n";

// 1. Verificar BOOKING_USERS específicamente para este curso
echo "1. BOOKING_USERS PARA GRUPPEN KW2:\n";

$dev_booking_users = DB::connection('boukii_dev')
    ->table('booking_users')
    ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
    ->where('booking_users.course_id', $dev_course_id)
    ->where('bookings.school_id', 13)
    ->whereNull('booking_users.deleted_at')
    ->select('booking_users.*', 'bookings.status as booking_status')
    ->get();

echo "DEV booking_users encontrados: " . $dev_booking_users->count() . "\n";
foreach ($dev_booking_users as $bu) {
    echo "  DEV booking_user {$bu->id}: booking={$bu->booking_id}, course_date={$bu->course_date_id}, status={$bu->status}, booking_status={$bu->booking_status}\n";
}

$prod_booking_users = DB::connection('boukii_pro')
    ->table('booking_users')
    ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
    ->where('booking_users.course_id', $prod_course_id)
    ->where('bookings.school_id', 15)
    ->whereNull('booking_users.deleted_at')
    ->select('booking_users.*', 'bookings.status as booking_status')
    ->get();

echo "PROD booking_users encontrados: " . $prod_booking_users->count() . "\n";
foreach ($prod_booking_users as $bu) {
    echo "  PROD booking_user {$bu->id}: booking={$bu->booking_id}, course_date={$bu->course_date_id}, status={$bu->status}, booking_status={$bu->booking_status}\n";
}

// 2. Verificar si el course_date_id existe y es correcto
echo "\n2. VERIFICACIÓN COURSE_DATES:\n";
if ($dev_booking_users->count() > 0) {
    $dev_course_date_id = $dev_booking_users->first()->course_date_id;
    $dev_course_date = DB::connection('boukii_dev')->table('course_dates')->where('id', $dev_course_date_id)->first();
    echo "DEV course_date {$dev_course_date_id}: course_id={$dev_course_date->course_id}, date={$dev_course_date->date}\n";
    
    if ($dev_course_date->course_id != $dev_course_id) {
        echo "  ❌ ERROR: course_date apunta a curso {$dev_course_date->course_id}, no a $dev_course_id\n";
    }
}

if ($prod_booking_users->count() > 0) {
    $prod_course_date_id = $prod_booking_users->first()->course_date_id;
    $prod_course_date = DB::connection('boukii_pro')->table('course_dates')->where('id', $prod_course_date_id)->first();
    echo "PROD course_date {$prod_course_date_id}: course_id={$prod_course_date->course_id}, date={$prod_course_date->date}\n";
    
    if ($prod_course_date->course_id != $prod_course_id) {
        echo "  ❌ ERROR: course_date apunta a curso {$prod_course_date->course_id}, no a $prod_course_id\n";
    }
} else {
    echo "No hay booking_users en PROD para verificar course_dates\n";
}

// 3. Verificar la migración del booking específico
echo "\n3. VERIFICACIÓN BOOKING ESPECÍFICO:\n";
$dev_booking = DB::connection('boukii_dev')->table('bookings')->where('school_id', 13)->first();
$prod_booking = DB::connection('boukii_pro')->table('bookings')->where('school_id', 15)->first();

echo "DEV booking {$dev_booking->id}: client_main_id={$dev_booking->client_main_id}\n";
echo "PROD booking {$prod_booking->id}: client_main_id={$prod_booking->client_main_id}\n";

// Verificar si el booking_user apunta al curso correcto
$dev_bu_for_booking = DB::connection('boukii_dev')
    ->table('booking_users')
    ->where('booking_id', $dev_booking->id)
    ->first();

$prod_bu_for_booking = DB::connection('boukii_pro')
    ->table('booking_users')
    ->where('booking_id', $prod_booking->id)
    ->first();

echo "DEV booking_user para booking {$dev_booking->id}: course_id={$dev_bu_for_booking->course_id}\n";
echo "PROD booking_user para booking {$prod_booking->id}: course_id={$prod_bu_for_booking->course_id}\n";

// 4. Verificar plazas totales detalladamente
echo "\n4. VERIFICACIÓN PLAZAS TOTALES:\n";

// Contar subgrupos por course_date para el curso específico
$dev_course_dates = DB::connection('boukii_dev')->table('course_dates')->where('course_id', $dev_course_id)->pluck('id');
$prod_course_dates = DB::connection('boukii_pro')->table('course_dates')->where('course_id', $prod_course_id)->pluck('id');

echo "Course dates: DEV " . $dev_course_dates->count() . " vs PROD " . $prod_course_dates->count() . "\n";

foreach ($dev_course_dates as $i => $dev_date_id) {
    $prod_date_id = $prod_course_dates[$i] ?? null;
    
    if (!$prod_date_id) continue;
    
    // Grupos para esta fecha
    $dev_groups = DB::connection('boukii_dev')
        ->table('course_groups')
        ->where('course_date_id', $dev_date_id)
        ->pluck('id');
    
    $prod_groups = DB::connection('boukii_pro')
        ->table('course_groups')  
        ->where('course_date_id', $prod_date_id)
        ->pluck('id');
    
    // Subgrupos y plazas para estos grupos
    $dev_subgroups_places = DB::connection('boukii_dev')
        ->table('course_subgroups')
        ->whereIn('course_group_id', $dev_groups)
        ->whereNull('deleted_at')
        ->sum('max_participants');
    
    $prod_subgroups_places = DB::connection('boukii_pro')
        ->table('course_subgroups')
        ->whereIn('course_group_id', $prod_groups)
        ->whereNull('deleted_at')
        ->sum('max_participants');
    
    echo "  Fecha $i: DEV date $dev_date_id = $dev_subgroups_places plazas vs PROD date $prod_date_id = $prod_subgroups_places plazas\n";
    
    if ($dev_subgroups_places != $prod_subgroups_places) {
        echo "    ❌ DIFERENCIA EN PLAZAS ENCONTRADA\n";
    }
}

echo "\n=== CONCLUSIÓN ===\n";
echo "Buscando la raíz exacta de por qué la API da resultados diferentes...\n";