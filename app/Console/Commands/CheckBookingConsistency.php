<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\Client;

class CheckBookingConsistency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:check-consistency {--fix : Fix encontradas inconsistencias automáticamente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica la coherencia entre clientes principales y participantes en las reservas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Iniciando verificación de coherencia cliente-participantes...');

        $inconsistentBookings = [];
        $totalBookings = 0;
        $fixMode = $this->option('fix');

        // Obtener todas las reservas con sus relaciones
        $bookings = Booking::with(['clientMain', 'clientMain.utilizers', 'bookingUsers.client'])
            ->whereNotNull('client_main_id')
            ->get();

        $totalBookings = $bookings->count();
        $this->info("📊 Analizando {$totalBookings} reservas...");

        foreach ($bookings as $booking) {
            $clientMainId = $booking->client_main_id;
            $mainClient = $booking->clientMain;

            if (!$mainClient) {
                $this->error("❌ Reserva #{$booking->id}: Cliente principal no encontrado (ID: {$clientMainId})");
                continue;
            }

            // Obtener IDs válidos: cliente principal + sus utilizers
            $validClientIds = [$clientMainId];
            foreach ($mainClient->utilizers as $utilizer) {
                $validClientIds[] = $utilizer->id;
            }

            // Verificar cada BookingUser
            $invalidParticipants = [];
            foreach ($booking->bookingUsers as $bookingUser) {
                if (!in_array($bookingUser->client_id, $validClientIds)) {
                    $invalidParticipants[] = [
                        'booking_user_id' => $bookingUser->id,
                        'client_id' => $bookingUser->client_id,
                        'client_name' => $bookingUser->client ?
                            $bookingUser->client->first_name . ' ' . $bookingUser->client->last_name :
                            'Cliente no encontrado'
                    ];
                }
            }

            if (count($invalidParticipants) > 0) {
                $inconsistentBookings[] = [
                    'booking' => $booking,
                    'main_client' => $mainClient,
                    'invalid_participants' => $invalidParticipants,
                    'valid_client_ids' => $validClientIds
                ];

                $this->error("❌ INCONSISTENCIA ENCONTRADA:");
                $this->line("   Reserva: #{$booking->id}");
                $this->line("   Cliente principal: {$mainClient->first_name} {$mainClient->last_name} (ID: {$clientMainId})");
                $this->line("   Utilizers válidos: " . implode(', ', $validClientIds));
                $this->line("   Participantes inválidos:");

                foreach ($invalidParticipants as $invalid) {
                    $this->line("     - {$invalid['client_name']} (ID: {$invalid['client_id']})");
                }
                $this->line("");
            }
        }

        // Resumen final
        $inconsistentCount = count($inconsistentBookings);
        if ($inconsistentCount === 0) {
            $this->info("✅ ¡Perfecto! No se encontraron inconsistencias en {$totalBookings} reservas.");
        } else {
            $this->warn("⚠️  RESUMEN:");
            $this->warn("   Total reservas analizadas: {$totalBookings}");
            $this->warn("   Reservas con problemas: {$inconsistentCount}");
            $this->warn("   Reservas correctas: " . ($totalBookings - $inconsistentCount));

            if ($fixMode) {
                $this->line("");
                $this->warn("🔧 Modo de corrección activado (--fix)");
                $this->warn("   NOTA: La corrección automática es compleja y requiere decisiones manuales.");
                $this->warn("   Por seguridad, este comando solo identifica problemas.");
                $this->warn("   Para corregir, contacta con el administrador del sistema.");
            } else {
                $this->line("");
                $this->info("💡 Para más detalles, ejecuta: php artisan booking:check-consistency --fix");
            }
        }

        return $inconsistentCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
