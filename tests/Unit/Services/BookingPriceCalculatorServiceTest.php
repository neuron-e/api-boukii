<?php

namespace Tests\Unit\Services;

use App\Http\Services\BookingPriceCalculatorService;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Course;
use App\Models\School;
use App\Models\Client;
use App\Models\Payment;
use App\Models\VouchersLog;
use App\Models\Voucher;
use App\Models\BookingUserExtra;
use App\Models\CourseExtra;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingPriceCalculatorServiceTest extends TestCase
{
    protected BookingPriceCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar SQLite en memoria
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createTestTables();
        $this->service = app(BookingPriceCalculatorService::class);
    }

    protected function createTestTables(): void
    {
        $schema = Schema::connection('sqlite');
        $schema->dropAllTables();

        // Tabla schools
        $schema->create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('settings')->nullable();
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

        // Tabla courses
        $schema->create('courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('name')->nullable();
            $table->integer('course_type')->default(1);
            $table->boolean('is_flexible')->default(false);
            $table->decimal('price', 10, 2)->default(0);
            $table->text('price_range')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla bookings
        $schema->create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('client_main_id')->nullable();
            $table->decimal('price_total', 10, 2)->default(0);
            $table->boolean('has_cancellation_insurance')->default(false);
            $table->decimal('price_cancellation_insurance', 10, 2)->default(0);
            $table->boolean('has_tva')->default(false);
            $table->decimal('price_tva', 10, 2)->default(0);
            $table->boolean('has_reduction')->default(false);
            $table->decimal('price_reduction', 10, 2)->default(0);
            $table->unsignedBigInteger('discount_code_id')->nullable();
            $table->decimal('discount_code_value', 10, 2)->nullable();
            $table->string('currency')->default('EUR');
            $table->decimal('paid_total', 10, 2)->default(0);
            $table->boolean('paid')->default(false);
            $table->integer('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla booking_users
        $schema->create('booking_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('school_id');
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('status')->default(1);
            $table->date('date')->nullable();
            $table->time('hour_start')->nullable();
            $table->time('hour_end')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('monitor_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla payments
        $schema->create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('notes')->nullable();
            $table->string('payrexx_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla vouchers
        $schema->create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('remaining_balance', 10, 2)->default(0);
            $table->boolean('payed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla vouchers_log
        $schema->create('vouchers_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('voucher_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla course_extras
        $schema->create('course_extras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->string('name')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabla booking_user_extras
        $schema->create('booking_user_extras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_user_id');
            $table->unsignedBigInteger('course_extra_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * =============================================================================
     * TESTS DE PRECIO DE ACTIVIDADES - CURSOS COLECTIVOS
     * =============================================================================
     */

    /** @test */
    public function it_calculates_collective_course_non_flexible_single_client()
    {
        // Arrange: Curso colectivo NO flexible, 1 cliente
        $school = School::withoutEvents(fn() => School::create(['name' => 'Test School']));
        $course = Course::withoutEvents(fn() => Course::create([
            'course_type' => 1, // Colectivo
            'is_flexible' => false,
            'price' => 100.00,
            'school_id' => $school->id,
            'name' => 'Test Course'
        ]));

        $client = Client::withoutEvents(fn() => Client::create(['first_name' => 'John', 'last_name' => 'Doe']));
        $booking = Booking::withoutEvents(fn() => Booking::create([
            'school_id' => $school->id,
            'client_main_id' => $client->id
        ]));

        // 3 booking_users para el mismo cliente (3 sesiones)
        for ($i = 0; $i < 3; $i++) {
            BookingUser::withoutEvents(fn() => BookingUser::create([
                'booking_id' => $booking->id,
                'client_id' => $client->id,
                'course_id' => $course->id,
                'status' => 1,
                'school_id' => $school->id,
                'price' => 100.00
            ]));
        }

        // Act
        $result = $this->service->calculateBookingTotal($booking->fresh(['bookingUsers']));

        // Assert: Precio base = 100€ (1 cliente x 100€, no importa cuántas sesiones en NO flexible)
        $this->assertEquals(100.00, $result['activities_price']);
        $this->assertEquals(100.00, $result['total_final']);
    }

    /** @test */
    public function it_calculates_collective_course_non_flexible_multiple_clients()
    {
        // Arrange: Curso colectivo NO flexible, 3 clientes
        $school = School::withoutEvents(fn() => School::create(['name' => 'Test School']));
        $course = Course::withoutEvents(fn() => Course::create([
            'course_type' => 1,
            'is_flexible' => false,
            'price' => 150.00,
            'school_id' => $school->id,
            'name' => 'Test Course'
        ]));

        $booking = Booking::withoutEvents(fn() => Booking::create(['school_id' => $school->id]));

        // Crear 3 clientes, cada uno con 2 sesiones
        for ($c = 0; $c < 3; $c++) {
            $client = Client::withoutEvents(fn() => Client::create(['first_name' => "Client$c"]));

            for ($s = 0; $s < 2; $s++) {
                BookingUser::withoutEvents(fn() => BookingUser::create([
                    'booking_id' => $booking->id,
                    'client_id' => $client->id,
                    'course_id' => $course->id,
                    'status' => 1,
                    'school_id' => $school->id,
                    'price' => 150.00
                ]));
            }
        }

        // Act
        $result = $this->service->calculateBookingTotal($booking->fresh(['bookingUsers']));

        // Assert: 3 clientes x 150€ = 450€
        $this->assertEquals(450.00, $result['activities_price']);
    }

    /** @test */
    public function it_calculates_private_course_non_flexible_single_participant()
    {
        // Arrange: Curso privado NO flexible
        $school = School::withoutEvents(fn() => School::create(['name' => 'Test School']));
        $course = Course::withoutEvents(fn() => Course::create([
            'course_type' => 2, // Privado
            'is_flexible' => false,
            'price' => 120.00,
            'school_id' => $school->id,
            'name' => 'Private Course'
        ]));

        $booking = Booking::withoutEvents(fn() => Booking::create(['school_id' => $school->id]));
        $client = Client::withoutEvents(fn() => Client::create(['first_name' => 'John']));

        // 2 BookingUsers (2 sesiones)
        for ($i = 0; $i < 2; $i++) {
            BookingUser::withoutEvents(fn() => BookingUser::create([
                'booking_id' => $booking->id,
                'client_id' => $client->id,
                'course_id' => $course->id,
                'status' => 1,
                'school_id' => $school->id,
                'price' => 120.00
            ]));
        }

        // Act
        $result = $this->service->calculateBookingTotal($booking->fresh(['bookingUsers']));

        // Assert: Privado NO flexible usa el precio directo de cada booking_user
        // 2 booking_users x 120€ = 240€
        $this->assertEquals(240.00, $result['activities_price']);
    }

    /** @test */
    public function it_includes_extras_in_activity_price()
    {
        // Arrange
        $school = School::withoutEvents(fn() => School::create(['name' => 'Test School']));
        $course = Course::withoutEvents(fn() => Course::create([
            'course_type' => 1,
            'is_flexible' => false,
            'price' => 100.00,
            'school_id' => $school->id,
            'name' => 'Course with extras'
        ]));

        $extra = CourseExtra::withoutEvents(fn() => CourseExtra::create([
            'course_id' => $course->id,
            'price' => 15.00,
            'name' => 'Material rental'
        ]));

        $booking = Booking::withoutEvents(fn() => Booking::create(['school_id' => $school->id]));
        $client = Client::withoutEvents(fn() => Client::create(['first_name' => 'John']));

        $bookingUser = BookingUser::withoutEvents(fn() => BookingUser::create([
            'booking_id' => $booking->id,
            'client_id' => $client->id,
            'course_id' => $course->id,
            'status' => 1,
            'school_id' => $school->id
        ]));

        // Añadir extra al booking_user
        BookingUserExtra::withoutEvents(fn() => BookingUserExtra::create([
            'booking_user_id' => $bookingUser->id,
            'course_extra_id' => $extra->id
        ]));

        // Act
        $result = $this->service->calculateBookingTotal($booking->fresh(['bookingUsers.bookingUserExtras.courseExtra']));

        // Assert: 100€ (curso) + 15€ (extra) = 115€
        $this->assertEquals(115.00, $result['activities_price']);
    }

    /** @test */
    public function it_calculates_cancellation_insurance()
    {
        // Arrange
        $school = School::withoutEvents(fn() => School::create([
            'name' => 'Test School',
            'settings' => json_encode([
                'taxes' => [
                    'cancellation_insurance_percent' => 0.10 // 10%
                ]
            ])
        ]));

        $course = Course::withoutEvents(fn() => Course::create([
            'course_type' => 1,
            'is_flexible' => false,
            'price' => 200.00,
            'school_id' => $school->id,
            'name' => 'Course'
        ]));

        $booking = Booking::withoutEvents(fn() => Booking::create([
            'school_id' => $school->id,
            'has_cancellation_insurance' => true,
            'price_cancellation_insurance' => 0 // Se calculará automáticamente
        ]));

        BookingUser::withoutEvents(fn() => BookingUser::create([
            'booking_id' => $booking->id,
            'client_id' => Client::withoutEvents(fn() => Client::create(['first_name' => 'John']))->id,
            'course_id' => $course->id,
            'status' => 1,
            'school_id' => $school->id
        ]));

        // Act
        $result = $this->service->calculateBookingTotal($booking->fresh(['bookingUsers', 'school']));

        // Assert: 200€ + 20€ (10% seguro) = 220€
        $this->assertEquals(200.00, $result['activities_price']);
        $this->assertEquals(20.00, $result['additional_concepts']['cancellation_insurance']);
        $this->assertEquals(220.00, $result['total_final']);
    }

    /** @test */
    public function it_applies_manual_reduction()
    {
        // Arrange
        $school = School::withoutEvents(fn() => School::create(['name' => 'Test School']));
        $course = Course::withoutEvents(fn() => Course::create([
            'course_type' => 1,
            'price' => 200.00,
            'school_id' => $school->id,
            'name' => 'Course'
        ]));

        $booking = Booking::withoutEvents(fn() => Booking::create([
            'school_id' => $school->id,
            'has_reduction' => true,
            'price_reduction' => 30.00
        ]));

        BookingUser::withoutEvents(fn() => BookingUser::create([
            'booking_id' => $booking->id,
            'client_id' => Client::withoutEvents(fn() => Client::create(['first_name' => 'John']))->id,
            'course_id' => $course->id,
            'status' => 1,
            'school_id' => $school->id
        ]));

        // Act
        $result = $this->service->calculateBookingTotal($booking->fresh(['bookingUsers']));

        // Assert: 200€ - 30€ = 170€
        $this->assertEquals(200.00, $result['activities_price']);
        $this->assertEquals(30.00, $result['discounts']['manual_reduction']);
        $this->assertEquals(170.00, $result['total_final']);
    }

    /** @test */
    public function it_applies_discount_code()
    {
        // Arrange
        $school = School::withoutEvents(fn() => School::create(['name' => 'Test School']));
        $course = Course::withoutEvents(fn() => Course::create([
            'course_type' => 1,
            'price' => 150.00,
            'school_id' => $school->id,
            'name' => 'Course'
        ]));

        $booking = Booking::withoutEvents(fn() => Booking::create([
            'school_id' => $school->id,
            'discount_code_id' => 123,
            'discount_code_value' => 20.00
        ]));

        BookingUser::withoutEvents(fn() => BookingUser::create([
            'booking_id' => $booking->id,
            'client_id' => Client::withoutEvents(fn() => Client::create(['first_name' => 'John']))->id,
            'course_id' => $course->id,
            'status' => 1,
            'school_id' => $school->id
        ]));

        // Act
        $result = $this->service->calculateBookingTotal($booking->fresh(['bookingUsers']));

        // Assert: 150€ - 20€ = 130€
        $this->assertEquals(20.00, $result['discounts']['discount_code']);
        $this->assertEquals(130.00, $result['total_final']);
    }

    /** @test */
    public function it_excludes_cancelled_booking_users()
    {
        // Arrange
        $school = School::withoutEvents(fn() => School::create(['name' => 'Test School']));
        $course = Course::withoutEvents(fn() => Course::create([
            'course_type' => 1,
            'is_flexible' => false,
            'price' => 100.00,
            'school_id' => $school->id,
            'name' => 'Course'
        ]));

        $booking = Booking::withoutEvents(fn() => Booking::create(['school_id' => $school->id]));

        // 2 clientes activos
        $client1 = Client::withoutEvents(fn() => Client::create(['first_name' => 'John']));
        BookingUser::withoutEvents(fn() => BookingUser::create([
            'booking_id' => $booking->id,
            'client_id' => $client1->id,
            'course_id' => $course->id,
            'status' => 1, // Activo
            'school_id' => $school->id
        ]));

        $client2 = Client::withoutEvents(fn() => Client::create(['first_name' => 'Jane']));
        BookingUser::withoutEvents(fn() => BookingUser::create([
            'booking_id' => $booking->id,
            'client_id' => $client2->id,
            'course_id' => $course->id,
            'status' => 1, // Activo
            'school_id' => $school->id
        ]));

        // 1 cliente cancelado
        $client3 = Client::withoutEvents(fn() => Client::create(['first_name' => 'Bob']));
        BookingUser::withoutEvents(fn() => BookingUser::create([
            'booking_id' => $booking->id,
            'client_id' => $client3->id,
            'course_id' => $course->id,
            'status' => 2, // Cancelado
            'school_id' => $school->id
        ]));

        // Act
        $result = $this->service->calculateBookingTotal($booking->fresh(['bookingUsers']));

        // Assert: Solo cuenta los 2 clientes activos (200€ NO 300€)
        $this->assertEquals(200.00, $result['activities_price']);
    }

    /** @test */
    public function it_handles_booking_with_no_booking_users()
    {
        // Arrange: Booking sin usuarios
        $school = School::withoutEvents(fn() => School::create(['name' => 'Test School']));
        $booking = Booking::withoutEvents(fn() => Booking::create(['school_id' => $school->id]));

        // Act
        $result = $this->service->calculateBookingTotal($booking->fresh(['bookingUsers']));

        // Assert
        $this->assertEquals(0.00, $result['activities_price']);
        $this->assertEquals(0.00, $result['total_final']);
    }

    /** @test */
    public function it_analyzes_vouchers_as_payment_not_discount()
    {
        // Arrange
        $school = School::withoutEvents(fn() => School::create(['name' => 'Test School']));
        $course = Course::withoutEvents(fn() => Course::create([
            'course_type' => 1,
            'price' => 200.00,
            'school_id' => $school->id,
            'name' => 'Course'
        ]));

        $booking = Booking::withoutEvents(fn() => Booking::create(['school_id' => $school->id]));

        BookingUser::withoutEvents(fn() => BookingUser::create([
            'booking_id' => $booking->id,
            'client_id' => Client::withoutEvents(fn() => Client::create(['first_name' => 'John']))->id,
            'course_id' => $course->id,
            'status' => 1,
            'school_id' => $school->id
        ]));

        $voucher = Voucher::withoutEvents(fn() => Voucher::create([
            'code' => 'VOUCHER50',
            'quantity' => 50.00,
            'remaining_balance' => 0,
            'payed' => true
        ]));

        VouchersLog::withoutEvents(fn() => VouchersLog::create([
            'booking_id' => $booking->id,
            'voucher_id' => $voucher->id,
            'amount' => 50.00 // Usado 50€
        ]));

        // Act
        $result = $this->service->calculateBookingTotal($booking->fresh(['bookingUsers', 'vouchersLogs.voucher']));

        // Assert: El total_final NO se reduce por vouchers (son forma de pago)
        $this->assertEquals(200.00, $result['total_final']);
        $this->assertEquals(50.00, $result['vouchers_info']['total_used']);
        $this->assertEquals(50.00, $result['vouchers_info']['net_voucher_payment']);
    }

    /** @test */
    public function it_analyzes_financial_reality_for_active_booking()
    {
        // Arrange: Booking activa con pago completo
        $school = School::withoutEvents(fn() => School::create(['name' => 'Test School']));
        $course = Course::withoutEvents(fn() => Course::create([
            'course_type' => 1,
            'price' => 200.00,
            'school_id' => $school->id,
            'name' => 'Course'
        ]));

        $booking = Booking::withoutEvents(fn() => Booking::create([
            'school_id' => $school->id,
            'price_total' => 200.00,
            'status' => 1,
            'paid' => true
        ]));

        BookingUser::withoutEvents(fn() => BookingUser::create([
            'booking_id' => $booking->id,
            'client_id' => Client::withoutEvents(fn() => Client::create(['first_name' => 'John']))->id,
            'course_id' => $course->id,
            'status' => 1,
            'school_id' => $school->id
        ]));

        Payment::withoutEvents(fn() => Payment::create([
            'booking_id' => $booking->id,
            'amount' => 200.00,
            'status' => 'paid'
        ]));

        // Act
        $result = $this->service->analyzeFinancialReality($booking->fresh(['bookingUsers', 'payments']));

        // Assert
        $this->assertEquals(200.00, $result['calculated_total']);
        $this->assertEquals(200.00, $result['financial_reality']['net_balance']);
        $this->assertTrue($result['reality_check']['is_consistent']);
    }

    /** @test */
    public function it_detects_underpayment_discrepancy()
    {
        // Arrange: Booking con pago insuficiente
        $school = School::withoutEvents(fn() => School::create(['name' => 'Test School']));
        $course = Course::withoutEvents(fn() => Course::create([
            'course_type' => 1,
            'price' => 300.00,
            'school_id' => $school->id,
            'name' => 'Course'
        ]));

        $booking = Booking::withoutEvents(fn() => Booking::create([
            'school_id' => $school->id,
            'status' => 1,
            'paid' => false
        ]));

        BookingUser::withoutEvents(fn() => BookingUser::create([
            'booking_id' => $booking->id,
            'client_id' => Client::withoutEvents(fn() => Client::create(['first_name' => 'John']))->id,
            'course_id' => $course->id,
            'status' => 1,
            'school_id' => $school->id
        ]));

        // Solo pagó 250€ de 300€
        Payment::withoutEvents(fn() => Payment::create([
            'booking_id' => $booking->id,
            'amount' => 250.00,
            'status' => 'paid'
        ]));

        // Act
        $result = $this->service->analyzeFinancialReality($booking->fresh(['bookingUsers', 'payments']));

        // Assert
        $this->assertFalse($result['reality_check']['is_consistent']);
        $this->assertEquals(50.00, $result['reality_check']['main_discrepancy']);
        // Verificar que hay una discrepancia positiva (falta dinero)
        $this->assertGreaterThan(0, $result['reality_check']['main_discrepancy']);
    }
}
