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
    'password'  => 'Manolo11.',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== CORRIGIENDO NIVELES DE CLIENTES ===\n";

try {
    DB::beginTransaction();
    
    // Obtener IDs de los clientes
    $gian_hitz = DB::table('clients')
        ->where('email', 'hitz.gian@outlook.com')
        ->where('first_name', 'Gian')
        ->first();
    
    $hitz_lavinia = DB::table('clients')
        ->where('email', 'gian.hitz@outlook.com') 
        ->where('first_name', 'Hitz')
        ->where('last_name', 'Lavinia')
        ->first();

    if (!$gian_hitz || !$hitz_lavinia) {
        throw new Exception('No se encontraron los clientes correctos');
    }

    echo "Cliente Gian Hitz ID: {$gian_hitz->id}\n";
    echo "Cliente Hitz Lavinia ID: {$hitz_lavinia->id}\n";

    // Obtener IDs necesarios
    $ski_id = DB::table('sports')->where('name', 'Ski')->value('id');
    $red_prince_id = DB::table('degrees')->where('name', 'Red Prince / Princess')->value('id');
    $blue_king_id = DB::table('degrees')->where('name', 'Blue King / Queen')->value('id');

    echo "Ski ID: $ski_id, Red Prince ID: $red_prince_id, Blue King ID: $blue_king_id\n";

    if (!$ski_id || !$red_prince_id || !$blue_king_id) {
        throw new Exception('No se encontraron los IDs necesarios');
    }

    // Limpiar niveles existentes de estos clientes
    $deleted_gian = DB::table('clients_sports')->where('client_id', $gian_hitz->id)->delete();
    $deleted_lavinia = DB::table('clients_sports')->where('client_id', $hitz_lavinia->id)->delete();
    
    echo "Eliminados niveles anteriores - Gian: $deleted_gian, Lavinia: $deleted_lavinia\n";

    // Asignar niveles correctos
    // Gian Hitz → Red Prince
    DB::table('clients_sports')->insert([
        'client_id' => $gian_hitz->id,
        'sport_id' => $ski_id,
        'degree_id' => $red_prince_id,
        'created_at' => now(),
        'updated_at' => now()
    ]);

    // Hitz Lavinia → Blue King
    DB::table('clients_sports')->insert([
        'client_id' => $hitz_lavinia->id,
        'sport_id' => $ski_id,
        'degree_id' => $blue_king_id,
        'created_at' => now(),
        'updated_at' => now()
    ]);

    DB::commit();

    echo "\n✅ NIVELES CORREGIDOS EXITOSAMENTE:\n";
    echo "- Gian Hitz (hitz.gian@outlook.com) → Red Prince / Princess\n";
    echo "- Hitz Lavinia (gian.hitz@outlook.com) → Blue King / Queen\n";

    // Verificar resultados
    echo "\n=== VERIFICACIÓN ===\n";
    
    $gian_levels = DB::table('clients_sports')
        ->join('degrees', 'clients_sports.degree_id', '=', 'degrees.id')
        ->where('client_id', $gian_hitz->id)
        ->pluck('degrees.name');
    
    $lavinia_levels = DB::table('clients_sports')
        ->join('degrees', 'clients_sports.degree_id', '=', 'degrees.id')
        ->where('client_id', $hitz_lavinia->id)
        ->pluck('degrees.name');

    echo "Gian Hitz niveles: " . $gian_levels->implode(', ') . "\n";
    echo "Hitz Lavinia niveles: " . $lavinia_levels->implode(', ') . "\n";

} catch (Exception $e) {
    DB::rollback();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}