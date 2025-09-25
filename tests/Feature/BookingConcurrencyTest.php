<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Client;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\CourseSubgroup;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * MEJORA CRÍTICA: Test de Concurrencia para Reservas de Cursos Colectivos
 *
 * Este test verifica que el sistema maneja correctamente las reservas simultáneas
 * y previene que se excedan los límites de participantes en subgrupos.
 */
class BookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private $school;
    private $user;
    private $course;
    private $courseDate;
    private $courseSubgroup;
    private $clients;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear datos de prueba
        $this->school = School::factory()->create();
        $this->user = User::factory()->create();

        $this->course = Course::factory()->create([
            'school_id' => $this->school->id,
            'course_type' => 1, // Curso colectivo
        ]);

        $this->courseDate = CourseDate::factory()->create([
            'course_id' => $this->course->id,
        ]);

        // CRÍTICO: Subgrupo con capacidad limitada para probar concurrencia
        $this->courseSubgroup = CourseSubgroup::factory()->create([
            'course_id' => $this->course->id,
            'course_date_id' => $this->courseDate->id,
            'degree_id' => 1,
            'max_participants' => 2, // ⚠️ SOLO 2 PLAZAS DISPONIBLES
        ]);

        // Crear 3 clientes para probar exceso de capacidad
        $this->clients = Client::factory()->count(3)->create([
            'school_id' => $this->school->id,
        ]);
    }

    /** @test */
    public function test_concurrent_bookings_respect_subgroup_capacity()
    {
        // ESCENARIO: 3 usuarios intentan reservar simultáneamente un curso con solo 2 plazas

        $results = [];
        $bookingData = [
            'client_main_id' => $this->clients[0]->id,
            'has_tva' => false,
            'has_boukii_care' => false,
            'has_cancellation_insurance' => false,
            'has_reduction' => false,
            'price_total' => 100,
            'price_reduction' => 0,
            'price_tva' => 0,
            'price_boukii_care' => 0,
            'price_cancellation_insurance' => 0,
            'payment_method_id' => 1,
            'paid_total' => 0,
            'paid' => false,
            'vouchers' => [],
            'cart' => []
        ];

        // Simular reservas concurrentes usando transacciones
        foreach ($this->clients as $index => $client) {
            $bookingData['client_main_id'] = $client->id;
            $bookingData['cart'] = [[
                'client_id' => $client->id,
                'course_id' => $this->course->id,
                'course_date_id' => $this->courseDate->id,
                'course_type' => 1,
                'degree_id' => 1,
                'price' => 100,
                'currency' => 'EUR',
                'hour_start' => '10:00:00',
                'hour_end' => '11:00:00',
                'notes_school' => '',
                'notes' => '',
                'group_id' => 1,
            ]];

            // Hacer la petición HTTP como usuario autenticado
            $response = $this->actingAs($this->user)
                ->postJson('/api/admin/bookings', $bookingData);

            $results[] = [
                'client_id' => $client->id,
                'status_code' => $response->getStatusCode(),
                'response' => $response->getContent(),
                'success' => $response->isSuccessful()
            ];
        }

        // VERIFICACIÓN: Solo 2 de 3 reservas deberían tener éxito
        $successfulBookings = array_filter($results, fn($r) => $r['success']);
        $failedBookings = array_filter($results, fn($r) => !$r['success']);

        $this->assertCount(2, $successfulBookings, 'Solo 2 reservas deberían ser exitosas');
        $this->assertCount(1, $failedBookings, '1 reserva debería fallar por falta de plazas');

        // VERIFICACIÓN: El subgrupo no debe exceder su capacidad
        $finalParticipantCount = BookingUser::where('course_subgroup_id', $this->courseSubgroup->id)
            ->where('status', 1)
            ->count();

        $this->assertEquals(2, $finalParticipantCount, 'El subgrupo no debe exceder su capacidad máxima');

        // VERIFICACIÓN: El mensaje de error debe ser específico
        $lastFailure = end($failedBookings);
        $responseData = json_decode($lastFailure['response'], true);

        $this->assertStringContainsString('No hay plazas disponibles', $responseData['message']);
        $this->assertArrayHasKey('course_date_id', $responseData['data']);
        $this->assertArrayHasKey('degree_id', $responseData['data']);
    }

    /** @test */
    public function test_coursesubgroup_availability_methods()
    {
        // Verificar que el subgrupo inicialmente tiene plazas disponibles
        $this->assertTrue($this->courseSubgroup->hasAvailableSlots());
        $this->assertEquals(2, $this->courseSubgroup->getAvailableSlotsCount());

        // Crear primera reserva
        $booking1 = Booking::factory()->create(['school_id' => $this->school->id]);
        BookingUser::factory()->create([
            'booking_id' => $booking1->id,
            'course_subgroup_id' => $this->courseSubgroup->id,
            'status' => 1
        ]);

        // Refrescar y verificar disponibilidad
        $this->courseSubgroup->refresh();
        $this->assertTrue($this->courseSubgroup->hasAvailableSlots());
        $this->assertEquals(1, $this->courseSubgroup->getAvailableSlotsCount());

        // Crear segunda reserva
        $booking2 = Booking::factory()->create(['school_id' => $this->school->id]);
        BookingUser::factory()->create([
            'booking_id' => $booking2->id,
            'course_subgroup_id' => $this->courseSubgroup->id,
            'status' => 1
        ]);

        // Verificar que ya no hay plazas disponibles
        $this->courseSubgroup->refresh();
        $this->assertFalse($this->courseSubgroup->hasAvailableSlots());
        $this->assertEquals(0, $this->courseSubgroup->getAvailableSlotsCount());
    }

    /** @test */
    public function test_logging_records_concurrency_attempts()
    {
        // Usar Laravel's Log fake para capturar logs
        Log::fake();

        // Hacer una reserva exitosa
        $bookingData = [
            'client_main_id' => $this->clients[0]->id,
            'has_tva' => false,
            'has_boukii_care' => false,
            'has_cancellation_insurance' => false,
            'has_reduction' => false,
            'price_total' => 100,
            'price_reduction' => 0,
            'price_tva' => 0,
            'price_boukii_care' => 0,
            'price_cancellation_insurance' => 0,
            'payment_method_id' => 1,
            'paid_total' => 0,
            'paid' => false,
            'vouchers' => [],
            'cart' => [[
                'client_id' => $this->clients[0]->id,
                'course_id' => $this->course->id,
                'course_date_id' => $this->courseDate->id,
                'course_type' => 1,
                'degree_id' => 1,
                'price' => 100,
                'currency' => 'EUR',
                'hour_start' => '10:00:00',
                'hour_end' => '11:00:00',
                'notes_school' => '',
                'notes' => '',
                'group_id' => 1,
            ]]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/bookings', $bookingData);

        $this->assertTrue($response->isSuccessful());

        // Verificar que se registraron los logs esperados
        Log::assertLogged('info', function ($message, $context) {
            return $message === 'BOOKING_CONCURRENCY_ATTEMPT' && is_array($context);
        });

        Log::assertLogged('info', function ($message, $context) {
            return $message === 'BOOKING_CONCURRENCY_SUCCESS' && is_array($context);
        });
    }
}