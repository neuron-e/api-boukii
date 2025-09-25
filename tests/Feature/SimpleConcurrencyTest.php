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
use Tests\TestCase;

/**
 * Test básico para verificar los métodos de disponibilidad de CourseSubgroup
 */
class SimpleConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_coursesubgroup_availability_methods_work()
    {
        $school = School::factory()->create();
        $course = Course::factory()->create(['school_id' => $school->id, 'course_type' => 1]);
        $courseDate = CourseDate::factory()->create(['course_id' => $course->id]);

        // Subgrupo con capacidad para 2 participantes
        $courseSubgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_date_id' => $courseDate->id,
            'degree_id' => 1,
            'max_participants' => 2
        ]);

        // Inicialmente debe tener plazas disponibles
        $this->assertTrue($courseSubgroup->hasAvailableSlots());
        $this->assertEquals(2, $courseSubgroup->getAvailableSlotsCount());

        // Crear primera reserva
        $booking1 = Booking::factory()->create(['school_id' => $school->id]);
        BookingUser::factory()->create([
            'booking_id' => $booking1->id,
            'course_subgroup_id' => $courseSubgroup->id,
            'status' => 1
        ]);

        // Refrescar el modelo para obtener datos actualizados
        $courseSubgroup->refresh();

        // Debe quedar 1 plaza
        $this->assertTrue($courseSubgroup->hasAvailableSlots());
        $this->assertEquals(1, $courseSubgroup->getAvailableSlotsCount());

        // Crear segunda reserva
        $booking2 = Booking::factory()->create(['school_id' => $school->id]);
        BookingUser::factory()->create([
            'booking_id' => $booking2->id,
            'course_subgroup_id' => $courseSubgroup->id,
            'status' => 1
        ]);

        // Refrescar y verificar que no hay plazas
        $courseSubgroup->refresh();
        $this->assertFalse($courseSubgroup->hasAvailableSlots());
        $this->assertEquals(0, $courseSubgroup->getAvailableSlotsCount());
    }

    /** @test */
    public function test_coursesubgroup_unlimited_capacity()
    {
        $school = School::factory()->create();
        $course = Course::factory()->create(['school_id' => $school->id, 'course_type' => 1]);
        $courseDate = CourseDate::factory()->create(['course_id' => $course->id]);

        // Subgrupo SIN límite de participantes (max_participants = null)
        $courseSubgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_date_id' => $courseDate->id,
            'degree_id' => 1,
            'max_participants' => null
        ]);

        // Siempre debe tener plazas disponibles
        $this->assertTrue($courseSubgroup->hasAvailableSlots());
        $this->assertEquals(999, $courseSubgroup->getAvailableSlotsCount());

        // Crear varias reservas
        for ($i = 0; $i < 5; $i++) {
            $booking = Booking::factory()->create(['school_id' => $school->id]);
            BookingUser::factory()->create([
                'booking_id' => $booking->id,
                'course_subgroup_id' => $courseSubgroup->id,
                'status' => 1
            ]);
        }

        // Debe seguir teniendo plazas disponibles
        $this->assertTrue($courseSubgroup->hasAvailableSlots());
        $this->assertEquals(999, $courseSubgroup->getAvailableSlotsCount());
    }
}