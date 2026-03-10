<?php

namespace Tests\Unit\Services;

use App\Services\RentalNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Unit tests for RentalNotificationService.
 *
 * Verifies idempotency logic and graceful failure behaviour
 * without requiring full DB fixtures (skips when tables absent).
 */
class RentalNotificationServiceTest extends TestCase
{
    private RentalNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RentalNotificationService::class);
        Mail::fake();
    }

    /** @test */
    public function send_confirmation_is_idempotent(): void
    {
        if (!Schema::hasTable('rental_reservations') || !Schema::hasTable('rental_events')) {
            $this->markTestSkipped('Rental tables not available.');
        }

        // Insert minimal reservation + event marking it already sent
        $reservationId = DB::table('rental_reservations')->insertGetId([
            'school_id'  => 1,
            'client_id'  => 1,
            'status'     => 'pending',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date'   => now()->addDays(4)->toDateString(),
            'total'      => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('rental_events')->insert([
            'school_id'             => 1,
            'rental_reservation_id' => $reservationId,
            'event_type'            => 'confirmation_sent',
            'payload'               => json_encode(['sent_at' => now()->toIso8601String()]),
            'user_id'               => null,
            'created_at'            => now(),
        ]);

        // Call sendConfirmation — should be no-op due to idempotency check
        $this->service->sendConfirmation($reservationId);

        // Mail::fake() means no actual mail sent, and no exception thrown = idempotency works
        Mail::assertNothingSent();

        // Cleanup
        DB::table('rental_events')->where('rental_reservation_id', $reservationId)->delete();
        DB::table('rental_reservations')->where('id', $reservationId)->delete();
    }

    /** @test */
    public function send_overdue_skips_gracefully_when_reservation_missing(): void
    {
        // Should not throw — reservation doesn't exist
        $this->service->sendOverdue(999999);
        Mail::assertNothingSent();
        $this->assertTrue(true); // No exception = pass
    }

    /** @test */
    public function send_reminder_skips_non_pending_statuses(): void
    {
        if (!Schema::hasTable('rental_reservations')) {
            $this->markTestSkipped('rental_reservations table not available.');
        }

        $reservationId = DB::table('rental_reservations')->insertGetId([
            'school_id'  => 1,
            'client_id'  => 1,
            'status'     => 'completed', // not pending/assigned
            'start_date' => now()->addDay()->toDateString(),
            'end_date'   => now()->addDays(3)->toDateString(),
            'total'      => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->sendReminder($reservationId);
        Mail::assertNothingSent();

        DB::table('rental_reservations')->where('id', $reservationId)->delete();
    }

    /** @test */
    public function send_damage_skips_when_school_has_no_email(): void
    {
        if (!Schema::hasTable('rental_reservations') || !Schema::hasTable('schools')) {
            $this->markTestSkipped('Required tables not available.');
        }

        // Insert school without email
        $schoolId = DB::table('schools')->insertGetId([
            'name'       => 'Test School No Email',
            'email'      => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reservationId = DB::table('rental_reservations')->insertGetId([
            'school_id'  => $schoolId,
            'client_id'  => 1,
            'status'     => 'active',
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addDays(2)->toDateString(),
            'total'      => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->sendDamage($reservationId, 25.00, 'Scratch on ski');
        Mail::assertNothingSent(); // No school email = skip

        DB::table('rental_reservations')->where('id', $reservationId)->delete();
        DB::table('schools')->where('id', $schoolId)->delete();
    }

    /** @test */
    public function send_damage_skips_gracefully_when_reservation_missing(): void
    {
        $this->service->sendDamage(999999, 50.00, 'Test damage');
        Mail::assertNothingSent();
        $this->assertTrue(true);
    }
}
