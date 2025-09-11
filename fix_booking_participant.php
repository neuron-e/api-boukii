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

echo "=== CORRIGIENDO PARTICIPANTE EN BOOKING ===\n";

try {
    DB::beginTransaction();
    
    // Obtener booking de school 15
    $booking = DB::table('bookings')->where('school_id', 15)->first();
    if (!$booking) {
        throw new Exception('No se encontró el booking');
    }
    
    echo "Booking ID: {$booking->id}\n";
    
    // Obtener ID de Hitz Lavinia
    $hitz_lavinia = DB::table('clients')
        ->where('email', 'gian.hitz@outlook.com') 
        ->where('first_name', 'Hitz')
        ->where('last_name', 'Lavinia')
        ->first();
        
    if (!$hitz_lavinia) {
        throw new Exception('No se encontró a Hitz Lavinia');
    }
    
    echo "Hitz Lavinia ID: {$hitz_lavinia->id}\n";
    
    // Verificar booking_user actual
    $current_booking_user = DB::table('booking_users')->where('booking_id', $booking->id)->first();
    echo "Participante actual ID: {$current_booking_user->client_id}\n";
    
    // Actualizar a Hitz Lavinia
    $updated = DB::table('booking_users')
        ->where('booking_id', $booking->id)
        ->update([
            'client_id' => $hitz_lavinia->id,
            'updated_at' => now()
        ]);
    
    echo "Booking users actualizados: $updated\n";
    
    DB::commit();
    
    echo "\n✅ PARTICIPANTE CORREGIDO EXITOSAMENTE:\n";
    echo "- Booking {$booking->id} ahora tiene como participante: Hitz Lavinia\n";
    
    // Verificar resultado
    echo "\n=== VERIFICACIÓN FINAL ===\n";
    
    $final_booking_user = DB::table('booking_users')
        ->join('clients', 'booking_users.client_id', '=', 'clients.id')
        ->where('booking_users.booking_id', $booking->id)
        ->select('clients.first_name', 'clients.last_name', 'clients.email')
        ->first();
    
    echo "Participante final: {$final_booking_user->first_name} {$final_booking_user->last_name} ({$final_booking_user->email})\n";

} catch (Exception $e) {
    DB::rollback();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}