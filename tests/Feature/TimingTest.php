<?php

namespace Tests\Feature;

use App\Models\BookingUser;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingest_requires_api_key(): void
    {
        $payload = [
            'items' => [[
                'booking_user_id' => 1,
                'date' => now()->toDateTimeString(),
                'time_ms' => 12345,
            ]]
        ];

        $this->postJson('/api/v4/timing/ingest', $payload)
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'invalid_api_key'
            ]);
    }

    public function test_course_summary_returns_success(): void
    {
        $course = Course::factory()->create();
        // Create a couple of booking users
        BookingUser::factory()->count(2)->create([
            'course_id' => $course->id,
            'date' => now()->subDay()->toDateString(),
            'attended' => false,
        ]);

        $this->getJson("/api/v4/courses/{$course->id}/timing/summary")
            ->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message', 'data' => [
                    'total_records', 'avg_time_ms', 'unique_booking_users', 'attendance_pct'
                ]
            ]);
    }
}

