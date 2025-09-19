<?php

namespace Tests\Feature;

use App\Models\School;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class SocialSettingsTest extends TestCase
{
    use WithoutMiddleware, DatabaseTransactions;

    /** @test */
    public function saves_handles_and_returns_normalized_urls()
    {
        $school = School::factory()->create([
            'cancellation_insurance_percent' => 0,
            'bookings_comission_cash' => 0,
            'bookings_comission_boukii_pay' => 0,
            'bookings_comission_other' => 0,
            'school_rate' => 0,
            'deleted_at' => null,
        ]);

        $settings = [
            'booking' => [
                'social' => [
                    'facebook' => '@miusuario',
                    'instagram' => 'miusuario',
                    'x' => '@miusuario',
                    'youtube' => 'miusuario',
                    'tiktok' => '@miusuario',
                    'linkedin' => 'miempresa',
                ]
            ]
        ];

        $this->json('PUT', "/api/schools/{$school->id}", [
            'settings' => json_encode($settings)
        ])->assertStatus(200);

        $response = $this->json('GET', "/api/schools/{$school->id}")->assertStatus(200)->json('data');
        $saved = json_decode($response['settings'] ?? '{}', true);
        $social = $saved['booking']['social'] ?? [];

        $this->assertEquals('https://www.facebook.com/miusuario', $social['facebook']);
        $this->assertEquals('https://www.instagram.com/miusuario', $social['instagram']);
        $this->assertEquals('https://twitter.com/miusuario', $social['x']);
        $this->assertEquals('https://www.youtube.com/@miusuario', $social['youtube']);
        $this->assertEquals('https://www.tiktok.com/@miusuario', $social['tiktok']);
        $this->assertEquals('https://www.linkedin.com/company/miempresa', $social['linkedin']);
    }

    /** @test */
    public function keeps_https_urls_as_is()
    {
        $school = School::factory()->create([
            'cancellation_insurance_percent' => 0,
            'bookings_comission_cash' => 0,
            'bookings_comission_boukii_pay' => 0,
            'bookings_comission_other' => 0,
            'school_rate' => 0,
            'deleted_at' => null,
        ]);
        $settings = [
            'booking' => [
                'social' => [
                    'instagram' => 'https://www.instagram.com/existing',
                ]
            ]
        ];

        $this->json('PUT', "/api/schools/{$school->id}", [
            'settings' => json_encode($settings)
        ])->assertStatus(200);

        $response = $this->json('GET', "/api/schools/{$school->id}")->assertStatus(200)->json('data');
        $saved = json_decode($response['settings'] ?? '{}', true);
        $this->assertEquals('https://www.instagram.com/existing', $saved['booking']['social']['instagram']);
    }

    /** @test */
    public function rejects_unsafe_protocols()
    {
        $school = School::factory()->create([
            'cancellation_insurance_percent' => 0,
            'bookings_comission_cash' => 0,
            'bookings_comission_boukii_pay' => 0,
            'bookings_comission_other' => 0,
            'school_rate' => 0,
            'deleted_at' => null,
        ]);
        $settings = [
            'booking' => [
                'social' => [
                    'facebook' => 'javascript:alert(1)'
                ]
            ]
        ];

        $this->json('PUT', "/api/schools/{$school->id}", [
            'settings' => json_encode($settings)
        ])->assertStatus(422);
    }
}
