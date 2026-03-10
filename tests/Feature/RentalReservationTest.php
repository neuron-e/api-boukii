<?php

namespace Tests\Feature;

use App\Models\RentalPickupPoint;
use App\Models\RentalReservation;
use App\Models\RentalReservationLine;
use App\Models\RentalUnit;
use App\Models\RentalVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PHPUnit feature tests for the Rental Reservation API.
 *
 * Covers:
 *  - Requires pickup_point_id (422 without it)
 *  - Creates reservation (status=pending)
 *  - Cancel: only allowed from pending
 *  - Payment registration
 *  - Deposit management (hold / release)
 *  - Overdue detection command
 *  - Reminder command (dry-run)
 *
 * Uses DatabaseTransactions to roll back after each test.
 * Skips gracefully when rental tables don't exist (fresh installs).
 */
class RentalReservationTest extends TestCase
{
    use DatabaseTransactions;

    private ?object $school  = null;
    private ?User   $user    = null;
    private ?object $client  = null;
    private ?object $pickup  = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('rental_reservations')) {
            $this->markTestSkipped('rental_reservations table does not exist.');
        }

        $this->setUpFixtures();
    }

    // ─── Fixtures ────────────────────────────────────────────────────────────

    private function setUpFixtures(): void
    {
        // School
        if (Schema::hasTable('schools')) {
            $this->school = DB::table('schools')->first();
        }

        // Admin user
        if (Schema::hasTable('users')) {
            $this->user = User::first();
        }

        // Client
        if (Schema::hasTable('clients') && $this->school) {
            $this->client = DB::table('clients')
                ->where('school_id', $this->school->id)
                ->first();
        }

        // Pickup point
        if (Schema::hasTable('rental_pickup_points') && $this->school) {
            $this->pickup = RentalPickupPoint::where('school_id', $this->school->id)->first();
        }
    }

    private function authHeaders(): array
    {
        if (!$this->user) return [];
        $token = $this->user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}"];
    }

    private function schoolId(): int
    {
        return $this->school?->id ?? 1;
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    /** @test */
    public function create_reservation_requires_pickup_point(): void
    {
        if (!$this->user || !$this->client) {
            $this->markTestSkipped('No user or client fixture available.');
        }

        $payload = [
            'school_id'  => $this->schoolId(),
            'client_id'  => $this->client->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date'   => now()->addDays(5)->toDateString(),
            // pickup_point_id intentionally omitted
        ];

        $response = $this->postJson('/api/admin/rentals/reservations', $payload, $this->authHeaders());

        $response->assertStatus(422);
        $this->assertStringContainsString('pickup', strtolower(json_encode($response->json())));
    }

    /** @test */
    public function create_reservation_with_valid_data_returns_pending(): void
    {
        if (!$this->user || !$this->client || !$this->pickup) {
            $this->markTestSkipped('Missing fixture: user, client or pickup point.');
        }

        $payload = [
            'school_id'       => $this->schoolId(),
            'client_id'       => $this->client->id,
            'pickup_point_id' => $this->pickup->id,
            'start_date'      => now()->addDays(3)->toDateString(),
            'end_date'        => now()->addDays(5)->toDateString(),
            'lines'           => [
                ['quantity' => 1, 'unit_price' => 30.00, 'line_total' => 30.00, 'period_type' => 'full_day'],
            ],
        ];

        $response = $this->postJson('/api/admin/rentals/reservations', $payload, $this->authHeaders());

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('pending', $data['status'] ?? null);
    }

    /** @test */
    public function daily_pricing_quote_multiplies_price_by_rental_days(): void
    {
        if (!$this->user || !$this->client || !$this->pickup) {
            $this->markTestSkipped('Missing fixture: user, client or pickup point.');
        }
        if (!Schema::hasTable('rental_pricing_rules')) {
            $this->markTestSkipped('rental_pricing_rules table missing.');
        }

        $variant = RentalVariant::where('school_id', $this->schoolId())->first();
        if (!$variant) {
            $this->markTestSkipped('No rental variant available for this school.');
        }

        DB::table('rental_pricing_rules')->where('variant_id', $variant->id)->delete();
        DB::table('rental_pricing_rules')->where('item_id', $variant->item_id)->delete();

        DB::table('rental_pricing_rules')->insert([
            'school_id' => $this->schoolId(),
            'item_id' => $variant->item_id,
            'variant_id' => $variant->id,
            'period_type' => 'full_day',
            'pricing_mode' => 'per_day',
            'min_days' => null,
            'max_days' => null,
            'priority' => 100,
            'price' => 30.00,
            'currency' => 'CHF',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/rentals/reservations/quote', [
            'school_id' => $this->schoolId(),
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-05',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'period_type' => 'full_day',
            'lines' => [[
                'item_id' => $variant->item_id,
                'variant_id' => $variant->id,
                'quantity' => 1,
                'period_type' => 'full_day',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-05',
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]]
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals(150.0, (float) $response->json('data.subtotal'));
        $this->assertEquals(150.0, (float) $response->json('data.lines.0.line_total'));
        $this->assertEquals(5, (int) $response->json('data.lines.0.rental_days'));
    }

    /** @test */
    public function week_pricing_quote_is_flat_package_not_multiplied_by_days(): void
    {
        if (!$this->user || !$this->client || !$this->pickup) {
            $this->markTestSkipped('Missing fixture: user, client or pickup point.');
        }
        if (!Schema::hasTable('rental_pricing_rules')) {
            $this->markTestSkipped('rental_pricing_rules table missing.');
        }

        $variant = RentalVariant::where('school_id', $this->schoolId())->first();
        if (!$variant) {
            $this->markTestSkipped('No rental variant available for this school.');
        }

        DB::table('rental_pricing_rules')->where('variant_id', $variant->id)->delete();
        DB::table('rental_pricing_rules')->where('item_id', $variant->item_id)->delete();

        DB::table('rental_pricing_rules')->insert([
            'school_id' => $this->schoolId(),
            'item_id' => $variant->item_id,
            'variant_id' => $variant->id,
            'period_type' => 'week',
            'pricing_mode' => 'flat',
            'min_days' => null,
            'max_days' => null,
            'priority' => 100,
            'price' => 120.00,
            'currency' => 'CHF',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/rentals/reservations/quote', [
            'school_id' => $this->schoolId(),
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-05',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'period_type' => 'week',
            'lines' => [[
                'item_id' => $variant->item_id,
                'variant_id' => $variant->id,
                'quantity' => 1,
                'period_type' => 'week',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-05',
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]]
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals(120.0, (float) $response->json('data.subtotal'));
        $this->assertEquals(120.0, (float) $response->json('data.lines.0.line_total'));
        $this->assertEquals('flat', $response->json('data.lines.0.pricing_mode'));
    }

    /** @test */
    public function cancel_reservation_requires_pending_status(): void
    {
        if (!$this->user) {
            $this->markTestSkipped('No user fixture.');
        }

        // Create an active (non-pending) reservation directly in DB
        $reservationId = DB::table('rental_reservations')->insertGetId([
            'school_id'              => $this->schoolId(),
            'client_id'              => $this->client?->id ?? 1,
            'pickup_point_id' => $this->pickup?->id ?? 1,
            'status'                 => 'active',
            'start_date'             => now()->addDays(1)->toDateString(),
            'end_date'               => now()->addDays(3)->toDateString(),
            'total'                  => 0,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $response = $this->postJson(
            "/api/admin/rentals/reservations/{$reservationId}/cancel",
            ['cancellation_reason' => 'Test cancel'],
            $this->authHeaders()
        );

        // Should fail — can only cancel pending
        $response->assertStatus(422);
    }

    /** @test */
    public function cancel_pending_reservation_sets_cancelled_status(): void
    {
        if (!$this->user) {
            $this->markTestSkipped('No user fixture.');
        }

        $reservationId = DB::table('rental_reservations')->insertGetId([
            'school_id'              => $this->schoolId(),
            'client_id'              => $this->client?->id ?? 1,
            'pickup_point_id' => $this->pickup?->id ?? 1,
            'status'                 => 'pending',
            'start_date'             => now()->addDays(2)->toDateString(),
            'end_date'               => now()->addDays(4)->toDateString(),
            'total'                  => 50.00,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $response = $this->postJson(
            "/api/admin/rentals/reservations/{$reservationId}/cancel",
            ['cancellation_reason' => 'Client changed plans'],
            $this->authHeaders()
        );

        $response->assertStatus(200);

        $updated = DB::table('rental_reservations')->where('id', $reservationId)->first();
        $this->assertEquals('cancelled', $updated->status);

        if (Schema::hasColumn('rental_reservations', 'cancellation_reason')) {
            $this->assertEquals('Client changed plans', $updated->cancellation_reason);
        }
    }

    /** @test */
    public function register_payment_for_reservation(): void
    {
        if (!$this->user) {
            $this->markTestSkipped('No user fixture.');
        }

        $reservationId = DB::table('rental_reservations')->insertGetId([
            'school_id'              => $this->schoolId(),
            'client_id'              => $this->client?->id ?? 1,
            'pickup_point_id' => $this->pickup?->id ?? 1,
            'status'                 => 'pending',
            'start_date'             => now()->addDays(2)->toDateString(),
            'end_date'               => now()->addDays(4)->toDateString(),
            'total'                  => 120.00,
            'currency'               => 'CHF',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $response = $this->postJson(
            "/api/admin/rentals/reservations/{$reservationId}/payment",
            [
                'amount'         => 120.00,
                'payment_method' => 'cash',
                'notes'          => 'Paid at desk',
            ],
            $this->authHeaders()
        );

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('paid', $data['status'] ?? null);
        $this->assertEquals(120.00, (float) ($data['amount'] ?? 0));

        // Verify payment_id is linked on reservation
        $updated = DB::table('rental_reservations')->where('id', $reservationId)->first();
        $this->assertNotNull($updated->payment_id);
    }

    /** @test */
    public function deposit_hold_updates_deposit_status(): void
    {
        if (!$this->user || !Schema::hasColumn('rental_reservations', 'deposit_status')) {
            $this->markTestSkipped('deposit_status column not available.');
        }

        $reservationId = DB::table('rental_reservations')->insertGetId([
            'school_id'              => $this->schoolId(),
            'client_id'              => $this->client?->id ?? 1,
            'pickup_point_id' => $this->pickup?->id ?? 1,
            'status'                 => 'active',
            'start_date'             => now()->toDateString(),
            'end_date'               => now()->addDays(2)->toDateString(),
            'total'                  => 200.00,
            'deposit_status'         => 'none',
            'deposit_amount'         => 0,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $response = $this->postJson(
            "/api/admin/rentals/reservations/{$reservationId}/deposit",
            ['action' => 'hold', 'amount' => 50.00, 'payment_method' => 'cash'],
            $this->authHeaders()
        );

        $response->assertStatus(200);
        $this->assertEquals('held', $response->json('data.deposit_status'));

        $updated = DB::table('rental_reservations')->where('id', $reservationId)->first();
        $this->assertEquals('held', $updated->deposit_status);
    }

    /** @test */
    public function detect_overdue_command_marks_expired_reservations(): void
    {
        if (!Schema::hasTable('rental_reservations')) {
            $this->markTestSkipped('rental_reservations table missing.');
        }

        // Insert a reservation with end_date in the past and active status
        $reservationId = DB::table('rental_reservations')->insertGetId([
            'school_id'              => $this->schoolId(),
            'client_id'              => $this->client?->id ?? 1,
            'pickup_point_id' => $this->pickup?->id ?? 1,
            'status'                 => 'active',
            'start_date'             => now()->subDays(5)->toDateString(),
            'end_date'               => now()->subDays(1)->toDateString(), // expired
            'total'                  => 80.00,
            'created_at'             => now()->subDays(5),
            'updated_at'             => now()->subDays(5),
        ]);

        $this->artisan('rentals:detect-overdue')->assertExitCode(0);

        $updated = DB::table('rental_reservations')->where('id', $reservationId)->first();
        $this->assertEquals('overdue', $updated->status);
    }

    /** @test */
    public function send_reminders_command_dry_run_exits_successfully(): void
    {
        $this->artisan('rentals:send-reminders --dry-run')->assertExitCode(0);
    }

    /** @test */
    public function full_lifecycle_create_autoassign_return_completed(): void
    {
        if (!$this->user || !$this->client || !$this->pickup) {
            $this->markTestSkipped('Missing fixture: user, client or pickup point.');
        }
        if (!Schema::hasTable('rental_variants') || !Schema::hasTable('rental_units')) {
            $this->markTestSkipped('rental_variants / rental_units tables missing.');
        }

        // Need an available unit with a variant
        $variant = RentalVariant::where('school_id', $this->schoolId())->first();
        if (!$variant) {
            $this->markTestSkipped('No rental variant available for this school.');
        }
        $unit = RentalUnit::where('variant_id', $variant->id)->where('status', 'available')->first();
        if (!$unit) {
            $this->markTestSkipped('No available rental unit for this school.');
        }

        // 1. Create reservation
        $createResp = $this->postJson('/api/admin/rentals/reservations', [
            'school_id'       => $this->schoolId(),
            'client_id'       => $this->client->id,
            'pickup_point_id' => $this->pickup->id,
            'start_date'      => now()->toDateString(),
            'end_date'        => now()->addDay()->toDateString(),
            'lines'           => [
                [
                    'variant_id'  => $variant->id,
                    'quantity'    => 1,
                    'unit_price'  => 40.00,
                    'line_total'  => 40.00,
                    'period_type' => 'full_day',
                ],
            ],
        ], $this->authHeaders());

        $createResp->assertStatus(200);
        $reservationId = $createResp->json('data.id');
        $this->assertNotNull($reservationId);
        $this->assertEquals('pending', $createResp->json('data.status'));

        // 2. Auto-assign units
        $assignResp = $this->postJson(
            "/api/admin/rentals/reservations/{$reservationId}/auto-assign",
            [],
            $this->authHeaders()
        );
        $assignResp->assertStatus(200);
        $this->assertGreaterThan(0, $assignResp->json('data.assigned'));

        // 3. Return all units
        $returnResp = $this->postJson(
            "/api/admin/rentals/reservations/{$reservationId}/return-units",
            [], // no explicit assignments → returns all
            $this->authHeaders()
        );
        $returnResp->assertStatus(200);

        // 4. Verify reservation is completed/returned
        $reservation = RentalReservation::find($reservationId);
        $this->assertNotNull($reservation);
        $this->assertContains($reservation->status, ['returned', 'completed']);

        // 5. Verify unit is back to available
        $unit->refresh();
        $this->assertEquals('available', $unit->status);
    }

    /** @test */
    public function analytics_summary_endpoint_returns_expected_structure(): void
    {
        if (!$this->user) {
            $this->markTestSkipped('No user fixture.');
        }

        $response = $this->getJson(
            '/api/admin/rentals/analytics?school_id=' . $this->schoolId(),
            $this->authHeaders()
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'kpis' => [
                        'total_reservations',
                        'active_reservations',
                        'completed_reservations',
                        'overdue_reservations',
                        'cancelled_reservations',
                        'revenue_completed',
                        'revenue_expected',
                        'total_damage_cost',
                    ],
                    'revenue_by_month',
                    'top_items',
                    'top_clients',
                    'status_breakdown',
                ],
            ]);
    }

    /** @test */
    public function analytics_summary_respects_date_from_and_date_to_filters(): void
    {
        if (!$this->user) {
            $this->markTestSkipped('No user fixture.');
        }

        DB::table('rental_reservations')->insert([
            [
                'school_id' => $this->schoolId(),
                'client_id' => $this->client?->id ?? 1,
                'pickup_point_id' => $this->pickup?->id ?? 1,
                'status' => 'completed',
                'start_date' => '2026-01-10',
                'end_date' => '2026-01-12',
                'total' => 50.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'school_id' => $this->schoolId(),
                'client_id' => $this->client?->id ?? 1,
                'pickup_point_id' => $this->pickup?->id ?? 1,
                'status' => 'completed',
                'start_date' => '2026-02-10',
                'end_date' => '2026-02-12',
                'total' => 75.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson(
            '/api/admin/rentals/analytics?school_id=' . $this->schoolId() . '&date_from=2026-01-01&date_to=2026-01-31',
            $this->authHeaders()
        );

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.kpis.total_reservations'));
        $this->assertEquals(50.00, (float) $response->json('data.kpis.revenue_completed'));
    }

    /** @test */
    public function send_reminders_skips_disabled_rental_policies(): void
    {
        if (!Schema::hasTable('rental_policies') || !Schema::hasColumn('rental_policies', 'enabled')) {
            $this->markTestSkipped('rental_policies.enabled not available.');
        }

        DB::table('rental_policies')->update(['enabled' => 0]);

        $this->artisan('rentals:send-reminders --dry-run')
            ->expectsOutput('No schools with rental policies found.')
            ->assertExitCode(0);
    }
}
