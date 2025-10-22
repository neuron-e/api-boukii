<?php

namespace Tests\Unit\Services;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseInterval;
use App\Models\CourseIntervalGroup;
use App\Models\CourseIntervalSubgroup;
use App\Models\CourseSubgroup;
use App\Services\CourseAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private CourseAvailabilityService $service;
    private Course $course;
    private CourseInterval $interval;
    private CourseGroup $group;
    private CourseSubgroup $subgroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CourseAvailabilityService();

        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test 1: Sin intervalo configurado - usar valor base del subgrupo
     */
    public function test_returns_base_max_participants_when_no_interval_exists(): void
    {
        // Arrange
        $course = Course::factory()->create([
            'intervals_config_mode' => 'independent'
        ]);

        $group = CourseGroup::factory()->create([
            'course_id' => $course->id,
            'max_participants' => 10
        ]);

        $subgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 8
        ]);

        $date = '2025-10-15';

        // Act
        $maxParticipants = $this->service->getMaxParticipants($subgroup, $date);

        // Assert
        $this->assertEquals(8, $maxParticipants, 'Debe retornar max_participants base del subgrupo');
    }

    /**
     * Test 2: Curso en modo 'unified' - ignorar configuración de intervalos
     */
    public function test_uses_base_value_when_course_mode_is_unified(): void
    {
        // Arrange
        $course = Course::factory()->create([
            'intervals_config_mode' => 'unified' // Modo unificado
        ]);

        $interval = CourseInterval::factory()->create([
            'course_id' => $course->id,
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31'
        ]);

        $group = CourseGroup::factory()->create([
            'course_id' => $course->id,
            'max_participants' => 10
        ]);

        $intervalGroup = CourseIntervalGroup::create([
            'course_id' => $course->id,
            'course_interval_id' => $interval->id,
            'course_group_id' => $group->id,
            'max_participants' => 5, // Override que NO debe usarse
            'active' => true
        ]);

        $subgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 8
        ]);

        $date = '2025-10-15';

        // Act
        $maxParticipants = $this->service->getMaxParticipants($subgroup, $date);

        // Assert
        $this->assertEquals(8, $maxParticipants, 'En modo unified debe ignorar intervalGroup y usar valor base');
    }

    /**
     * Test 3: Intervalo con override en CourseIntervalGroup
     */
    public function test_uses_interval_group_max_participants_when_configured(): void
    {
        // Arrange
        $course = Course::factory()->create([
            'intervals_config_mode' => 'independent'
        ]);

        $interval = CourseInterval::factory()->create([
            'course_id' => $course->id,
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31'
        ]);

        $group = CourseGroup::factory()->create([
            'course_id' => $course->id,
            'max_participants' => 10
        ]);

        $intervalGroup = CourseIntervalGroup::create([
            'course_id' => $course->id,
            'course_interval_id' => $interval->id,
            'course_group_id' => $group->id,
            'max_participants' => 6, // Override del grupo
            'active' => true
        ]);

        $subgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 8
        ]);

        $date = '2025-10-15';

        // Act
        $maxParticipants = $this->service->getMaxParticipants($subgroup, $date);

        // Assert
        $this->assertEquals(6, $maxParticipants, 'Debe usar max_participants del intervalGroup');
    }

    /**
     * Test 4: Prioridad máxima - CourseIntervalSubgroup override
     */
    public function test_uses_interval_subgroup_max_participants_highest_priority(): void
    {
        // Arrange
        $course = Course::factory()->create([
            'intervals_config_mode' => 'independent'
        ]);

        $interval = CourseInterval::factory()->create([
            'course_id' => $course->id,
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31'
        ]);

        $group = CourseGroup::factory()->create([
            'course_id' => $course->id,
            'max_participants' => 10
        ]);

        $intervalGroup = CourseIntervalGroup::create([
            'course_id' => $course->id,
            'course_interval_id' => $interval->id,
            'course_group_id' => $group->id,
            'max_participants' => 6,
            'active' => true
        ]);

        $subgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 8
        ]);

        $intervalSubgroup = CourseIntervalSubgroup::create([
            'course_interval_group_id' => $intervalGroup->id,
            'course_subgroup_id' => $subgroup->id,
            'max_participants' => 4, // Override específico del subgrupo
            'active' => true
        ]);

        $date = '2025-10-15';

        // Act
        $maxParticipants = $this->service->getMaxParticipants($subgroup, $date);

        // Assert
        $this->assertEquals(4, $maxParticipants, 'Debe usar max_participants del intervalSubgroup (máxima prioridad)');
    }

    /**
     * Test 5: CourseIntervalGroup inactivo - usar valor base
     */
    public function test_ignores_inactive_interval_group(): void
    {
        // Arrange
        $course = Course::factory()->create([
            'intervals_config_mode' => 'independent'
        ]);

        $interval = CourseInterval::factory()->create([
            'course_id' => $course->id,
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31'
        ]);

        $group = CourseGroup::factory()->create([
            'course_id' => $course->id,
            'max_participants' => 10
        ]);

        $intervalGroup = CourseIntervalGroup::create([
            'course_id' => $course->id,
            'course_interval_id' => $interval->id,
            'course_group_id' => $group->id,
            'max_participants' => 5,
            'active' => false // INACTIVO
        ]);

        $subgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 8
        ]);

        $date = '2025-10-15';

        // Act
        $maxParticipants = $this->service->getMaxParticipants($subgroup, $date);

        // Assert
        $this->assertEquals(8, $maxParticipants, 'Debe ignorar intervalGroup inactivo y usar valor base');
    }

    /**
     * Test 6: getAvailableSlots - cálculo correcto de disponibilidad
     */
    public function test_calculates_available_slots_correctly(): void
    {
        // Arrange
        $course = Course::factory()->create();
        $group = CourseGroup::factory()->create(['course_id' => $course->id]);
        $subgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 10
        ]);

        // Simular 3 reservas activas
        DB::table('bookings')->insert([
            ['id' => 1, 'status' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()]
        ]);

        DB::table('booking_users')->insert([
            ['booking_id' => 1, 'course_subgroup_id' => $subgroup->id, 'status' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['booking_id' => 1, 'course_subgroup_id' => $subgroup->id, 'status' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['booking_id' => 1, 'course_subgroup_id' => $subgroup->id, 'status' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $date = '2025-10-15';

        // Act
        $availableSlots = $this->service->getAvailableSlots($subgroup, $date);

        // Assert
        $this->assertEquals(7, $availableSlots, 'max_participants (10) - reservas activas (3) = 7 plazas disponibles');
    }

    /**
     * Test 7: getAvailableSlots - retorna 999 cuando max_participants es null
     */
    public function test_returns_999_when_max_participants_is_unlimited(): void
    {
        // Arrange
        $course = Course::factory()->create();
        $group = CourseGroup::factory()->create(['course_id' => $course->id]);
        $subgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => null // Sin límite
        ]);

        $date = '2025-10-15';

        // Act
        $availableSlots = $this->service->getAvailableSlots($subgroup, $date);

        // Assert
        $this->assertEquals(999, $availableSlots, 'Sin límite debe retornar 999');
    }

    /**
     * Test 8: hasAvailability - verifica disponibilidad suficiente
     */
    public function test_has_availability_returns_true_when_slots_available(): void
    {
        // Arrange
        $course = Course::factory()->create();
        $group = CourseGroup::factory()->create(['course_id' => $course->id]);
        $subgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 5
        ]);

        $date = '2025-10-15';

        // Act
        $hasAvailability = $this->service->hasAvailability($subgroup, $date, 3);

        // Assert
        $this->assertTrue($hasAvailability, 'Con 5 plazas totales y 0 reservas, debe tener disponibilidad para 3');
    }

    /**
     * Test 9: hasAvailability - retorna false cuando no hay suficientes plazas
     */
    public function test_has_availability_returns_false_when_insufficient_slots(): void
    {
        // Arrange
        $course = Course::factory()->create();
        $group = CourseGroup::factory()->create(['course_id' => $course->id]);
        $subgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 2
        ]);

        $date = '2025-10-15';

        // Act
        $hasAvailability = $this->service->hasAvailability($subgroup, $date, 5);

        // Assert
        $this->assertFalse($hasAvailability, 'Con solo 2 plazas totales, no debe tener disponibilidad para 5');
    }

    /**
     * Test 10: Cache funciona correctamente
     */
    public function test_caches_available_slots(): void
    {
        // Arrange
        $course = Course::factory()->create();
        $group = CourseGroup::factory()->create(['course_id' => $course->id]);
        $subgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 10
        ]);

        $date = '2025-10-15';
        $cacheKey = "available_slots_{$subgroup->id}_{$date}";

        // Act - Primera llamada
        $slots1 = $this->service->getAvailableSlots($subgroup, $date);

        // Verificar que se guardó en cache
        $this->assertTrue(Cache::has($cacheKey), 'Debe guardar en cache');

        // Segunda llamada debe venir de cache
        $slots2 = $this->service->getAvailableSlots($subgroup, $date);

        // Assert
        $this->assertEquals($slots1, $slots2, 'Debe retornar mismo valor desde cache');
    }

    /**
     * Test 11: invalidateCache limpia el cache correctamente
     */
    public function test_invalidate_cache_clears_cached_data(): void
    {
        // Arrange
        $course = Course::factory()->create();
        $group = CourseGroup::factory()->create(['course_id' => $course->id]);
        $subgroup = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 10
        ]);

        $date = '2025-10-15';
        $cacheKey = "available_slots_{$subgroup->id}_{$date}";

        // Generar cache
        $this->service->getAvailableSlots($subgroup, $date);
        $this->assertTrue(Cache::has($cacheKey));

        // Act
        $this->service->invalidateCache($subgroup, $date);

        // Assert
        $this->assertFalse(Cache::has($cacheKey), 'Cache debe estar limpio después de invalidar');
    }

    /**
     * Test 12: validateCartAvailability - carrito completo válido
     */
    public function test_validate_cart_availability_all_available(): void
    {
        // Arrange
        $course = Course::factory()->create();
        $group = CourseGroup::factory()->create(['course_id' => $course->id]);
        $subgroup1 = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 10
        ]);
        $subgroup2 = CourseSubgroup::factory()->create([
            'course_id' => $course->id,
            'course_group_id' => $group->id,
            'max_participants' => 5
        ]);

        $cartItems = [
            ['subgroup_id' => $subgroup1->id, 'date' => '2025-10-15'],
            ['subgroup_id' => $subgroup2->id, 'date' => '2025-10-16'],
        ];

        // Act
        $result = $this->service->validateCartAvailability($cartItems);

        // Assert
        $this->assertTrue($result['is_available'], 'Todo el carrito debe estar disponible');
        $this->assertCount(2, $result['details']);
        $this->assertTrue($result['details'][0]['available']);
        $this->assertTrue($result['details'][1]['available']);
    }
}
