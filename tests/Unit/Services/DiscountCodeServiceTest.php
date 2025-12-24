<?php

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Models\Course;
use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use App\Models\School;
use App\Models\Sport;
use App\Models\Degree;
use App\Services\DiscountCodeService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiscountCodeServiceTest extends TestCase
{
    protected DiscountCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar SQLite en memoria
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createTestTables();
        $this->service = app(DiscountCodeService::class);
    }

    protected function createTestTables(): void
    {
        $schema = Schema::connection('sqlite');
        $schema->dropAllTables();

        // Tabla discounts_codes
        $schema->create('discounts_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_limit_per_user')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('school_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('sport_id')->nullable();
            $table->unsignedBigInteger('degree_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla discount_code_usages
        $schema->create('discount_code_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discount_code_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('booking_id');
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla schools
        $schema->create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla courses
        $schema->create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('school_id')->nullable();
            $table->unsignedBigInteger('sport_id')->nullable();
            $table->unsignedBigInteger('degree_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla clients
        $schema->create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla sports
        $schema->create('sports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla degrees
        $schema->create('degrees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * =============================================================================
     * TESTS DE VALIDACIÓN DE CÓDIGOS
     * =============================================================================
     */

    /** @test */
    public function it_validates_active_discount_code_successfully()
    {
        // Arrange
        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'SAVE20',
            'name' => 'Summer Discount',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'is_active' => true,
            'valid_from' => Carbon::now()->subDays(5),
            'valid_until' => Carbon::now()->addDays(10),
        ]));

        $bookingData = [
            'total_amount' => 100.00,
            'course_id' => null,
            'school_id' => null,
            'user_id' => null,
        ];

        // Act
        $result = $this->service->validateCode('SAVE20', $bookingData);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEquals($code->id, $result['discount_code_id']);
        $this->assertEquals(20.00, $result['discount_amount']);
    }

    /** @test */
    public function it_rejects_inactive_discount_code()
    {
        // Arrange
        DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'INACTIVE',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'is_active' => false,
        ]));

        $bookingData = ['total_amount' => 100.00];

        // Act
        $result = $this->service->validateCode('INACTIVE', $bookingData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('inactive', strtolower($result['error']));
    }

    /** @test */
    public function it_rejects_expired_discount_code()
    {
        // Arrange
        DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'EXPIRED',
            'discount_type' => 'percentage',
            'discount_value' => 15.00,
            'is_active' => true,
            'valid_from' => Carbon::now()->subDays(30),
            'valid_until' => Carbon::now()->subDays(1), // Expiró ayer
        ]));

        $bookingData = ['total_amount' => 100.00];

        // Act
        $result = $this->service->validateCode('EXPIRED', $bookingData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('expired', strtolower($result['error']));
    }

    /** @test */
    public function it_rejects_not_yet_valid_discount_code()
    {
        // Arrange
        DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'FUTURE',
            'discount_type' => 'percentage',
            'discount_value' => 15.00,
            'is_active' => true,
            'valid_from' => Carbon::now()->addDays(5), // Inicia en 5 días
            'valid_until' => Carbon::now()->addDays(30),
        ]));

        $bookingData = ['total_amount' => 100.00];

        // Act
        $result = $this->service->validateCode('FUTURE', $bookingData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not yet valid', strtolower($result['error']));
    }

    /** @test */
    public function it_rejects_nonexistent_discount_code()
    {
        // Act
        $result = $this->service->validateCode('NONEXISTENT', ['total_amount' => 100.00]);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not found', strtolower($result['error']));
    }

    /**
     * =============================================================================
     * TESTS DE LÍMITES DE USO
     * =============================================================================
     */

    /** @test */
    public function it_rejects_code_that_exceeded_total_usage_limit()
    {
        // Arrange
        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'LIMITED',
            'discount_type' => 'fixed',
            'discount_value' => 10.00,
            'is_active' => true,
            'usage_limit' => 2, // Solo 2 usos permitidos
        ]));

        // Simular que ya se usó 2 veces
        DiscountCodeUsage::withoutEvents(fn() => DiscountCodeUsage::create([
            'discount_code_id' => $code->id,
            'user_id' => 1,
            'booking_id' => 100,
            'discount_amount' => 10.00,
        ]));

        DiscountCodeUsage::withoutEvents(fn() => DiscountCodeUsage::create([
            'discount_code_id' => $code->id,
            'user_id' => 2,
            'booking_id' => 101,
            'discount_amount' => 10.00,
        ]));

        $bookingData = ['total_amount' => 100.00, 'user_id' => 3];

        // Act
        $result = $this->service->validateCode('LIMITED', $bookingData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('usage limit reached', strtolower($result['error']));
    }

    /** @test */
    public function it_rejects_code_that_exceeded_per_user_usage_limit()
    {
        // Arrange
        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'ONEPERUSER',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'is_active' => true,
            'usage_limit_per_user' => 1, // Solo 1 uso por usuario
        ]));

        // Usuario 5 ya usó este código
        DiscountCodeUsage::withoutEvents(fn() => DiscountCodeUsage::create([
            'discount_code_id' => $code->id,
            'user_id' => 5,
            'booking_id' => 200,
            'discount_amount' => 10.00,
        ]));

        $bookingData = ['total_amount' => 100.00, 'user_id' => 5];

        // Act
        $result = $this->service->validateCode('ONEPERUSER', $bookingData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('user limit reached', strtolower($result['error']));
    }

    /** @test */
    public function it_allows_code_within_usage_limits()
    {
        // Arrange
        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'THREELEFT',
            'discount_type' => 'fixed',
            'discount_value' => 15.00,
            'is_active' => true,
            'usage_limit' => 5,
            'usage_limit_per_user' => 2,
        ]));

        // Usuario 10 usó el código 1 vez (puede usarlo 1 vez más)
        DiscountCodeUsage::withoutEvents(fn() => DiscountCodeUsage::create([
            'discount_code_id' => $code->id,
            'user_id' => 10,
            'booking_id' => 300,
            'discount_amount' => 15.00,
        ]));

        $bookingData = ['total_amount' => 100.00, 'user_id' => 10];

        // Act
        $result = $this->service->validateCode('THREELEFT', $bookingData);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * =============================================================================
     * TESTS DE RESTRICCIONES (SCHOOL, COURSE, CLIENT, ETC.)
     * =============================================================================
     */

    /** @test */
    public function it_validates_school_restriction()
    {
        // Arrange
        $school = School::withoutEvents(fn() => School::create(['name' => 'Allowed School']));

        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'SCHOOLONLY',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'is_active' => true,
            'school_id' => $school->id,
        ]));

        // Caso 1: Booking de la escuela correcta
        $validBooking = ['total_amount' => 100.00, 'school_id' => $school->id];
        $result1 = $this->service->validateCode('SCHOOLONLY', $validBooking);
        $this->assertTrue($result1['valid']);

        // Caso 2: Booking de otra escuela
        $invalidBooking = ['total_amount' => 100.00, 'school_id' => 999];
        $result2 = $this->service->validateCode('SCHOOLONLY', $invalidBooking);
        $this->assertFalse($result2['valid']);
        $this->assertStringContainsString('school', strtolower($result2['error']));
    }

    /** @test */
    public function it_validates_course_restriction()
    {
        // Arrange
        $course = Course::withoutEvents(fn() => Course::create(['name' => 'Ski Advanced']));

        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'COURSESPECIFIC',
            'discount_type' => 'fixed',
            'discount_value' => 20.00,
            'is_active' => true,
            'course_id' => $course->id,
        ]));

        // Caso válido
        $validBooking = ['total_amount' => 100.00, 'course_id' => $course->id];
        $result1 = $this->service->validateCode('COURSESPECIFIC', $validBooking);
        $this->assertTrue($result1['valid']);

        // Caso inválido
        $invalidBooking = ['total_amount' => 100.00, 'course_id' => 888];
        $result2 = $this->service->validateCode('COURSESPECIFIC', $invalidBooking);
        $this->assertFalse($result2['valid']);
    }

    /** @test */
    public function it_validates_client_restriction()
    {
        // Arrange
        $client = Client::withoutEvents(fn() => Client::create([
            'first_name' => 'VIP',
            'last_name' => 'Customer'
        ]));

        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'VIPONLY',
            'discount_type' => 'percentage',
            'discount_value' => 25.00,
            'is_active' => true,
            'client_id' => $client->id,
        ]));

        // Caso válido
        $validBooking = ['total_amount' => 100.00, 'client_id' => $client->id];
        $result1 = $this->service->validateCode('VIPONLY', $validBooking);
        $this->assertTrue($result1['valid']);

        // Caso inválido
        $invalidBooking = ['total_amount' => 100.00, 'client_id' => 777];
        $result2 = $this->service->validateCode('VIPONLY', $invalidBooking);
        $this->assertFalse($result2['valid']);
    }

    /** @test */
    public function it_validates_sport_restriction()
    {
        // Arrange
        $sport = Sport::withoutEvents(fn() => Sport::create(['name' => 'Snowboard']));
        $course = Course::withoutEvents(fn() => Course::create([
            'name' => 'Snowboard Beginner',
            'sport_id' => $sport->id
        ]));

        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'SNOWBOARDDEAL',
            'discount_type' => 'fixed',
            'discount_value' => 30.00,
            'is_active' => true,
            'sport_id' => $sport->id,
        ]));

        // Caso válido
        $validBooking = ['total_amount' => 100.00, 'course_id' => $course->id];
        $result1 = $this->service->validateCode('SNOWBOARDDEAL', $validBooking);
        $this->assertTrue($result1['valid']);
    }

    /** @test */
    public function it_validates_degree_restriction()
    {
        // Arrange
        $degree = Degree::withoutEvents(fn() => Degree::create(['name' => 'Advanced']));
        $course = Course::withoutEvents(fn() => Course::create([
            'name' => 'Advanced Ski',
            'degree_id' => $degree->id
        ]));

        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'ADVANCEDPROMO',
            'discount_type' => 'percentage',
            'discount_value' => 15.00,
            'is_active' => true,
            'degree_id' => $degree->id,
        ]));

        // Caso válido
        $validBooking = ['total_amount' => 200.00, 'course_id' => $course->id];
        $result1 = $this->service->validateCode('ADVANCEDPROMO', $validBooking);
        $this->assertTrue($result1['valid']);
    }

    /**
     * =============================================================================
     * TESTS DE MONTO MÍNIMO DE COMPRA
     * =============================================================================
     */

    /** @test */
    public function it_validates_minimum_purchase_amount()
    {
        // Arrange
        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'BIGSPENDER',
            'discount_type' => 'fixed',
            'discount_value' => 50.00,
            'is_active' => true,
            'min_purchase_amount' => 200.00,
        ]));

        // Caso válido: compra de 250€
        $validBooking = ['total_amount' => 250.00];
        $result1 = $this->service->validateCode('BIGSPENDER', $validBooking);
        $this->assertTrue($result1['valid']);

        // Caso inválido: compra de 150€
        $invalidBooking = ['total_amount' => 150.00];
        $result2 = $this->service->validateCode('BIGSPENDER', $invalidBooking);
        $this->assertFalse($result2['valid']);
        $this->assertStringContainsString('minimum', strtolower($result2['error']));
    }

    /**
     * =============================================================================
     * TESTS DE CÁLCULO DE DESCUENTO
     * =============================================================================
     */

    /** @test */
    public function it_calculates_percentage_discount_correctly()
    {
        // Arrange
        DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'PERCENT30',
            'discount_type' => 'percentage',
            'discount_value' => 30.00, // 30%
            'is_active' => true,
        ]));

        $bookingData = ['total_amount' => 200.00];

        // Act
        $result = $this->service->validateCode('PERCENT30', $bookingData);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEquals(60.00, $result['discount_amount']); // 30% de 200€ = 60€
    }

    /** @test */
    public function it_calculates_fixed_discount_correctly()
    {
        // Arrange
        DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'FIXED25',
            'discount_type' => 'fixed',
            'discount_value' => 25.00,
            'is_active' => true,
        ]));

        $bookingData = ['total_amount' => 100.00];

        // Act
        $result = $this->service->validateCode('FIXED25', $bookingData);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEquals(25.00, $result['discount_amount']);
    }

    /** @test */
    public function it_applies_max_discount_amount_cap()
    {
        // Arrange
        DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'CAPPED',
            'discount_type' => 'percentage',
            'discount_value' => 50.00, // 50%
            'max_discount_amount' => 30.00, // Máximo 30€
            'is_active' => true,
        ]));

        // 50% de 100€ = 50€, pero está limitado a 30€
        $bookingData = ['total_amount' => 100.00];

        // Act
        $result = $this->service->validateCode('CAPPED', $bookingData);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEquals(30.00, $result['discount_amount']);
    }

    /** @test */
    public function it_prevents_fixed_discount_from_exceeding_total()
    {
        // Arrange
        DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'HUGE100',
            'discount_type' => 'fixed',
            'discount_value' => 100.00,
            'is_active' => true,
        ]));

        // Total de solo 60€
        $bookingData = ['total_amount' => 60.00];

        // Act
        $result = $this->service->validateCode('HUGE100', $bookingData);

        // Assert
        $this->assertTrue($result['valid']);
        // El descuento no debe exceder el total
        $this->assertEquals(60.00, $result['discount_amount']);
    }

    /**
     * =============================================================================
     * TESTS DE REGISTRO Y REVERSIÓN DE USO
     * =============================================================================
     */

    /** @test */
    public function it_records_code_usage_successfully()
    {
        // Arrange
        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'TRACK',
            'discount_type' => 'fixed',
            'discount_value' => 20.00,
            'is_active' => true,
        ]));

        // Act
        $result = $this->service->recordCodeUsage($code->id, 123, 456, 20.00);

        // Assert
        $this->assertTrue($result);

        $usage = DiscountCodeUsage::where('discount_code_id', $code->id)
            ->where('user_id', 123)
            ->where('booking_id', 456)
            ->first();

        $this->assertNotNull($usage);
        $this->assertEquals(20.00, $usage->discount_amount);
    }

    /** @test */
    public function it_reverts_code_usage_successfully()
    {
        // Arrange
        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'REVERT',
            'discount_type' => 'fixed',
            'discount_value' => 15.00,
            'is_active' => true,
        ]));

        // Crear un uso
        $usage = DiscountCodeUsage::withoutEvents(fn() => DiscountCodeUsage::create([
            'discount_code_id' => $code->id,
            'user_id' => 789,
            'booking_id' => 999,
            'discount_amount' => 15.00,
        ]));

        // Act
        $result = $this->service->revertCodeUsage($code->id, 789, 999);

        // Assert
        $this->assertTrue($result);

        // Verificar que se eliminó (soft delete)
        $this->assertNull(
            DiscountCodeUsage::where('id', $usage->id)->first()
        );
    }

    /**
     * =============================================================================
     * TESTS DE ESTADÍSTICAS
     * =============================================================================
     */

    /** @test */
    public function it_returns_accurate_code_statistics()
    {
        // Arrange
        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'STATS',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'is_active' => true,
            'usage_limit' => 10,
        ]));

        // Crear 3 usos
        DiscountCodeUsage::withoutEvents(fn() => DiscountCodeUsage::create([
            'discount_code_id' => $code->id,
            'user_id' => 1,
            'booking_id' => 1,
            'discount_amount' => 10.00,
        ]));

        DiscountCodeUsage::withoutEvents(fn() => DiscountCodeUsage::create([
            'discount_code_id' => $code->id,
            'user_id' => 2,
            'booking_id' => 2,
            'discount_amount' => 15.00,
        ]));

        DiscountCodeUsage::withoutEvents(fn() => DiscountCodeUsage::create([
            'discount_code_id' => $code->id,
            'user_id' => 3,
            'booking_id' => 3,
            'discount_amount' => 20.00,
        ]));

        // Act
        $stats = $this->service->getCodeStats($code->id);

        // Assert
        $this->assertEquals(3, $stats['total_uses']);
        $this->assertEquals(45.00, $stats['total_discount_given']); // 10 + 15 + 20
        $this->assertEquals(7, $stats['remaining_uses']); // 10 - 3
    }

    /** @test */
    public function it_handles_unlimited_usage_in_statistics()
    {
        // Arrange
        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'UNLIMITED',
            'discount_type' => 'fixed',
            'discount_value' => 5.00,
            'is_active' => true,
            'usage_limit' => null, // Sin límite
        ]));

        // Act
        $stats = $this->service->getCodeStats($code->id);

        // Assert
        $this->assertEquals(0, $stats['total_uses']);
        $this->assertNull($stats['remaining_uses']);
        $this->assertEquals('unlimited', $stats['usage_status']);
    }

    /**
     * =============================================================================
     * TESTS DE CASOS EDGE
     * =============================================================================
     */

    /** @test */
    public function it_handles_case_insensitive_code_lookup()
    {
        // Arrange
        DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'CaseSensitive',
            'discount_type' => 'fixed',
            'discount_value' => 10.00,
            'is_active' => true,
        ]));

        $bookingData = ['total_amount' => 100.00];

        // Act - Probar varias variaciones
        $result1 = $this->service->validateCode('casesensitive', $bookingData);
        $result2 = $this->service->validateCode('CASESENSITIVE', $bookingData);
        $result3 = $this->service->validateCode('CaseSensitive', $bookingData);

        // Assert - Todas deberían funcionar
        $this->assertTrue($result1['valid']);
        $this->assertTrue($result2['valid']);
        $this->assertTrue($result3['valid']);
    }

    /** @test */
    public function it_handles_zero_discount_value_gracefully()
    {
        // Arrange
        DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'ZERO',
            'discount_type' => 'fixed',
            'discount_value' => 0.00,
            'is_active' => true,
        ]));

        $bookingData = ['total_amount' => 100.00];

        // Act
        $result = $this->service->validateCode('ZERO', $bookingData);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEquals(0.00, $result['discount_amount']);
    }

    /** @test */
    public function it_validates_multiple_restrictions_simultaneously()
    {
        // Arrange
        $school = School::withoutEvents(fn() => School::create(['name' => 'Premium School']));
        $course = Course::withoutEvents(fn() => Course::create([
            'name' => 'VIP Course',
            'school_id' => $school->id
        ]));

        $code = DiscountCode::withoutEvents(fn() => DiscountCode::create([
            'code' => 'MULTIRESTRICT',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'is_active' => true,
            'school_id' => $school->id,
            'course_id' => $course->id,
            'min_purchase_amount' => 150.00,
        ]));

        // Caso 1: Todo correcto
        $validBooking = [
            'total_amount' => 200.00,
            'school_id' => $school->id,
            'course_id' => $course->id,
        ];
        $result1 = $this->service->validateCode('MULTIRESTRICT', $validBooking);
        $this->assertTrue($result1['valid']);

        // Caso 2: School correcto pero curso incorrecto
        $invalidBooking = [
            'total_amount' => 200.00,
            'school_id' => $school->id,
            'course_id' => 999,
        ];
        $result2 = $this->service->validateCode('MULTIRESTRICT', $invalidBooking);
        $this->assertFalse($result2['valid']);

        // Caso 3: Todo correcto pero monto insuficiente
        $insufficientBooking = [
            'total_amount' => 100.00,
            'school_id' => $school->id,
            'course_id' => $course->id,
        ];
        $result3 = $this->service->validateCode('MULTIRESTRICT', $insufficientBooking);
        $this->assertFalse($result3['valid']);
    }
}
