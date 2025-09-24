<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\Client;

class AnalyzeBookingInconsistencies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:analyze-inconsistencies {--suggest-fixes : Sugerir correcciones para las inconsistencias}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analiza en detalle las inconsistencias cliente-participantes y sugiere correcciones';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔬 Iniciando análisis detallado de inconsistencias...');

        $suggestFixes = $this->option('suggest-fixes');
        $inconsistentBookings = $this->getInconsistentBookings();

        if (empty($inconsistentBookings)) {
            $this->info("✅ No se encontraron inconsistencias.");
            return Command::SUCCESS;
        }

        $this->warn("📊 Encontradas " . count($inconsistentBookings) . " reservas inconsistentes");
        $this->line("");

        // Analizar patrones
        $patterns = $this->analyzePatterns($inconsistentBookings);
        $this->displayPatternAnalysis($patterns);

        if ($suggestFixes) {
            $this->line("");
            $this->info("💡 SUGERENCIAS DE CORRECCIÓN:");
            $this->suggestFixes($inconsistentBookings, $patterns);
        }

        return Command::SUCCESS;
    }

    private function getInconsistentBookings()
    {
        $inconsistentBookings = [];

        $bookings = Booking::with(['clientMain', 'clientMain.utilizers', 'bookingUsers.client'])
            ->whereNotNull('client_main_id')
            ->get();

        foreach ($bookings as $booking) {
            $clientMainId = $booking->client_main_id;
            $mainClient = $booking->clientMain;

            if (!$mainClient) continue;

            $validClientIds = [$clientMainId];
            foreach ($mainClient->utilizers as $utilizer) {
                $validClientIds[] = $utilizer->id;
            }

            $invalidParticipants = [];
            foreach ($booking->bookingUsers as $bookingUser) {
                if (!in_array($bookingUser->client_id, $validClientIds)) {
                    $invalidParticipants[] = [
                        'booking_user_id' => $bookingUser->id,
                        'client_id' => $bookingUser->client_id,
                        'client' => $bookingUser->client
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
            }
        }

        return $inconsistentBookings;
    }

    private function analyzePatterns($inconsistentBookings)
    {
        $patterns = [
            'family_relations' => [],
            'same_surnames' => [],
            'potential_corrections' => [],
            'booking_frequency' => []
        ];

        foreach ($inconsistentBookings as $inconsistent) {
            $mainClientName = $inconsistent['main_client']->last_name;
            $bookingId = $inconsistent['booking']->id;

            foreach ($inconsistent['invalid_participants'] as $participant) {
                $participantClient = $participant['client'];
                if (!$participantClient) continue;

                $participantName = $participantClient->last_name;

                // Detectar apellidos similares (posible relación familiar)
                if ($this->areSimilarSurnames($mainClientName, $participantName)) {
                    $patterns['same_surnames'][] = [
                        'booking_id' => $bookingId,
                        'main_client' => $inconsistent['main_client']->first_name . ' ' . $mainClientName,
                        'participant' => $participantClient->first_name . ' ' . $participantName
                    ];
                }

                // Sugerir si el participante debería ser el cliente principal
                $participantBookingsAsMain = Booking::where('client_main_id', $participantClient->id)->count();
                if ($participantBookingsAsMain > 0) {
                    $patterns['potential_corrections'][] = [
                        'booking_id' => $bookingId,
                        'suggestion' => 'Cambiar cliente principal',
                        'current_main' => $inconsistent['main_client']->first_name . ' ' . $mainClientName,
                        'suggested_main' => $participantClient->first_name . ' ' . $participantName,
                        'participant_bookings_as_main' => $participantBookingsAsMain
                    ];
                }
            }
        }

        return $patterns;
    }

    private function areSimilarSurnames($surname1, $surname2)
    {
        // Verificar si los apellidos son exactamente iguales o muy similares
        if (strtolower($surname1) === strtolower($surname2)) {
            return true;
        }

        // Verificar si uno contiene al otro (ej: "Garcia" vs "Garcia de Ceballos")
        $lower1 = strtolower($surname1);
        $lower2 = strtolower($surname2);

        return strpos($lower1, $lower2) !== false || strpos($lower2, $lower1) !== false;
    }

    private function displayPatternAnalysis($patterns)
    {
        $this->warn("🔍 ANÁLISIS DE PATRONES:");

        if (!empty($patterns['same_surnames'])) {
            $this->line("  📚 Apellidos similares (posible relación familiar):");
            foreach ($patterns['same_surnames'] as $similar) {
                $this->line("    - Reserva #{$similar['booking_id']}: {$similar['main_client']} ↔ {$similar['participant']}");
            }
            $this->line("");
        }

        if (!empty($patterns['potential_corrections'])) {
            $this->line("  🔄 Posibles correcciones (participante debería ser cliente principal):");
            foreach ($patterns['potential_corrections'] as $correction) {
                $this->line("    - Reserva #{$correction['booking_id']}: {$correction['suggestion']}");
                $this->line("      Actual: {$correction['current_main']} → Sugerido: {$correction['suggested_main']}");
                $this->line("      ({$correction['suggested_main']} tiene {$correction['participant_bookings_as_main']} reservas como principal)");
            }
            $this->line("");
        }
    }

    private function suggestFixes($inconsistentBookings, $patterns)
    {
        $this->line("1. 🔗 CREAR RELACIONES FALTANTES:");
        $this->line("   Para casos donde hay relación familiar clara, ejecutar:");
        foreach ($patterns['same_surnames'] as $similar) {
            $this->line("   INSERT INTO clients_utilizers (main_id, client_id) VALUES (?, ?);");
        }

        $this->line("");
        $this->line("2. 🔄 CAMBIAR CLIENTE PRINCIPAL:");
        $this->line("   Para casos donde el participante debería ser el principal:");
        foreach ($patterns['potential_corrections'] as $correction) {
            $this->line("   UPDATE bookings SET client_main_id = ? WHERE id = {$correction['booking_id']};");
        }

        $this->line("");
        $this->line("3. ⚠️  VALIDACIÓN MANUAL:");
        $this->line("   Algunas inconsistencias requieren revisión manual para determinar la acción correcta.");

        $this->line("");
        $this->warn("🚨 IMPORTANTE: Realizar backup antes de ejecutar cualquier corrección!");
    }
}
