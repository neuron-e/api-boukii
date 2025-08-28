<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Booking;
use App\Models\Client;
use Mockery;
use Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PaymentProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable activity logging to simplify testing
        config(['activitylog.enabled' => false]);

        // Run only the migrations required for these tests
        $this->artisan('migrate', ['--path' => 'tests/database/migrations/2023_11_08_110720_create_schools_table.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_08_28_082703_add_payment_provider_columns_to_schools_table.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'tests/database/migrations/2023_11_08_110721_create_languages_table.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'tests/database/migrations/2023_11_08_110722_create_users_table.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'tests/database/migrations/2023_11_08_110723_create_clients_table.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'tests/database/migrations/2023_11_08_110724_create_bookings_table.php', '--force' => true]);
    }

    protected function tearDown(): void
    {
        // Roll back migrations
        $this->artisan('migrate:reset', ['--path' => 'tests/database/migrations/2023_11_08_110724_create_bookings_table.php', '--force' => true]);
        $this->artisan('migrate:reset', ['--path' => 'tests/database/migrations/2023_11_08_110723_create_clients_table.php', '--force' => true]);
        $this->artisan('migrate:reset', ['--path' => 'tests/database/migrations/2023_11_08_110722_create_users_table.php', '--force' => true]);
        $this->artisan('migrate:reset', ['--path' => 'tests/database/migrations/2023_11_08_110721_create_languages_table.php', '--force' => true]);
        $this->artisan('migrate:reset', ['--path' => 'database/migrations/2025_08_28_082703_add_payment_provider_columns_to_schools_table.php', '--force' => true]);
        $this->artisan('migrate:reset', ['--path' => 'tests/database/migrations/2023_11_08_110720_create_schools_table.php', '--force' => true]);
        Mockery::close();
        parent::tearDown();
    }

    public function test_pay_booking_uses_payyo_when_configured()
    {
        $school = School::withoutEvents(function () {
            $school = School::factory()->create([
                'payment_provider' => 'payyo',
                'slug' => 'payyo-school',
                'active' => 1,
                'settings' => '{}',
            ]);
            $school->setPayyoInstance('merchant');
            $school->setPayyoKey('secret');
            $school->save();
            return $school;
        });

        $client = new Client([
            'email' => 'client@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'birth_date' => '2000-01-01',
        ]);
        $client->id = 1;
        $client->save();

        $booking = new Booking([
            'school_id' => $school->id,
            'client_main_id' => $client->id,
            'price_total' => 100,
            'currency' => 'CHF',
            'payment_method_id' => 1,
            'paid' => false,
        ]);
        $booking->id = 1;
        $booking->save();

        Mockery::mock('overload:App\\Http\\Controllers\\PayyoHelpers')
            ->shouldReceive('createPayLink')
            ->once()
            ->andReturn('https://payyo.test/link');

        Mockery::mock('overload:App\\Http\\Controllers\\PayrexxHelpers')
            ->shouldReceive('createGatewayLink')
            ->never();

        $response = $this->postJson('/api/slug/bookings/payments/' . $booking->id, [], ['slug' => $school->slug]);

        $response->assertStatus(200)
            ->assertJson(['data' => 'https://payyo.test/link']);
    }

    public function test_pay_booking_falls_back_to_payrexx_for_other_schools()
    {
        $school = School::withoutEvents(function () {
            return School::factory()->create([
                'payment_provider' => 'payrexx',
                'slug' => 'payrexx-school',
                'active' => 1,
                'settings' => '{}',
            ]);
        });

        $client = new Client([
            'email' => 'client@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'birth_date' => '2000-01-01',
        ]);
        $client->id = 1;
        $client->save();

        $booking = new Booking([
            'school_id' => $school->id,
            'client_main_id' => $client->id,
            'price_total' => 100,
            'currency' => 'CHF',
            'payment_method_id' => 1,
            'paid' => false,
        ]);
        $booking->id = 1;
        $booking->save();

        Mockery::mock('overload:App\\Http\\Controllers\\PayyoHelpers')
            ->shouldReceive('createPayLink')
            ->never();

        Mockery::mock('overload:App\\Http\\Controllers\\PayrexxHelpers')
            ->shouldReceive('createGatewayLink')
            ->once()
            ->andReturn('https://payrexx.test/link');

        $response = $this->postJson('/api/slug/bookings/payments/' . $booking->id, [], ['slug' => $school->slug]);

        $response->assertStatus(200)
            ->assertJson(['data' => 'https://payrexx.test/link']);
    }
}

