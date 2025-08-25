<?php

namespace Tests\Feature\V5\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DevUsersLoginTest extends TestCase
{
    // Note: NOT using RefreshDatabase to preserve real data
    // use RefreshDatabase;

    protected $adminUser;
    protected $singleUser;
    protected $school2;
    protected $additionalSchool;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Get existing development users
        $this->adminUser = User::where('email', 'admin@boukii-v5.com')->first();
        $this->singleUser = User::where('email', 'multi@boukii-v5.com')->first();
        $this->school2 = School::find(2);
        $this->additionalSchool = School::find(1);
        
        // Verify test data exists
        $this->assertNotNull($this->adminUser, 'Admin user should exist');
        $this->assertNotNull($this->singleUser, 'Single school user should exist');
        $this->assertNotNull($this->school2, 'School ID 2 should exist');
    }

    /** @test */
    public function multi_school_user_check_user_returns_multiple_schools()
    {
        $response = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'admin@boukii-v5.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => ['id', 'name', 'email', 'role'],
                        'schools' => [
                            '*' => ['id', 'name', 'slug', 'logo', 'user_role', 'can_administer']
                        ],
                        'requires_school_selection',
                        'temp_token'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'user' => [
                            'email' => 'admin@boukii-v5.com',
                            'role' => 'admin'
                        ],
                        'requires_school_selection' => true
                    ]
                ]);

        // Should have exactly 2 schools
        $schools = $response->json('data.schools');
        $this->assertCount(2, $schools);
        
        // Should include school ID 2
        $schoolIds = collect($schools)->pluck('id')->toArray();
        $this->assertContains(2, $schoolIds);
        
        // Should have temp token for multi-school flow
        $this->assertNotEmpty($response->json('data.temp_token'));
    }

    /** @test */
    public function single_school_user_check_user_returns_single_school()
    {
        $response = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'multi@boukii-v5.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'user' => [
                            'email' => 'multi@boukii-v5.com',
                            'role' => 'admin'
                        ],
                        'requires_school_selection' => false // Single school = no selection needed
                    ]
                ]);

        // Should have exactly 1 school
        $schools = $response->json('data.schools');
        $this->assertCount(1, $schools);
        
        // Should be school ID 2
        $this->assertEquals(2, $schools[0]['id']);
        
        // Should still have temp token (backend always provides it now)
        $this->assertNotEmpty($response->json('data.temp_token'));
    }

    /** @test */
    public function multi_school_user_can_select_school_and_complete_login()
    {
        // First get temp token
        $checkUserResponse = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'admin@boukii-v5.com',
            'password' => 'password123'
        ]);

        $tempToken = $checkUserResponse->json('data.temp_token');
        $this->assertNotEmpty($tempToken);

        // Then select school
        $response = $this->postJson('/api/v5/auth/select-school', [
            'school_id' => 2,
            'remember_me' => true
        ], [
            'Authorization' => "Bearer {$tempToken}"
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => ['id', 'name', 'email'],
                        'school' => ['id', 'name', 'slug', 'logo'],
                        'season' => ['id', 'name', 'start_date', 'end_date'],
                        'access_token',
                        'token_type',
                        'expires_at',
                        'has_multiple_seasons',
                        'available_seasons'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'user' => [
                            'email' => 'admin@boukii-v5.com'
                        ],
                        'school' => [
                            'id' => 2
                        ],
                        'token_type' => 'Bearer'
                    ]
                ]);

        // Should have valid access token
        $this->assertNotEmpty($response->json('data.access_token'));
        
        // Should have season data (with correct fields, no 'year' field)
        $season = $response->json('data.season');
        $this->assertNotNull($season);
        $this->assertArrayHasKey('start_date', $season);
        $this->assertArrayHasKey('end_date', $season);
        $this->assertArrayNotHasKey('year', $season); // Ensure no 'year' field causing SQL error
    }

    /** @test */
    public function single_school_user_can_auto_select_school()
    {
        // Get temp token for single school user
        $checkUserResponse = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'multi@boukii-v5.com',
            'password' => 'password123'
        ]);

        $tempToken = $checkUserResponse->json('data.temp_token');
        
        // Single school user should still be able to use selectSchool
        $response = $this->postJson('/api/v5/auth/select-school', [
            'school_id' => 2,
            'remember_me' => false
        ], [
            'Authorization' => "Bearer {$tempToken}"
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'user' => [
                            'email' => 'multi@boukii-v5.com'
                        ],
                        'school' => [
                            'id' => 2
                        ]
                    ]
                ]);

        $this->assertNotEmpty($response->json('data.access_token'));
    }

    /** @test */
    public function invalid_credentials_are_rejected()
    {
        $response = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ]);
    }

    /** @test */
    public function select_school_requires_valid_temp_token()
    {
        $response = $this->postJson('/api/v5/auth/select-school', [
            'school_id' => 2,
            'remember_me' => false
        ]);

        $response->assertStatus(401); // Unauthorized without token
    }

    /** @test */
    public function select_school_rejects_invalid_school_access()
    {
        // Get temp token for single school user
        $checkUserResponse = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'multi@boukii-v5.com',
            'password' => 'password123'
        ]);

        $tempToken = $checkUserResponse->json('data.temp_token');

        // Try to select a school the user doesn't have access to
        $nonAccessibleSchool = School::where('id', '!=', 2)
            ->whereNotIn('id', function($query) {
                $query->select('school_id')
                      ->from('school_users')
                      ->where('user_id', $this->singleUser->id);
            })
            ->first();

        if ($nonAccessibleSchool) {
            $response = $this->postJson('/api/v5/auth/select-school', [
                'school_id' => $nonAccessibleSchool->id,
                'remember_me' => false
            ], [
                'Authorization' => "Bearer {$tempToken}"
            ]);

            $response->assertStatus(403)
                    ->assertJson([
                        'success' => false,
                        'message' => 'Acceso denegado a esta escuela'
                    ]);
        } else {
            $this->markTestSkipped('No non-accessible school found for test');
        }
    }

    /** @test */
    public function seasons_data_uses_correct_fields()
    {
        // Test that season data doesn't include problematic fields
        $checkUserResponse = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'admin@boukii-v5.com',
            'password' => 'password123'
        ]);

        $tempToken = $checkUserResponse->json('data.temp_token');

        $response = $this->postJson('/api/v5/auth/select-school', [
            'school_id' => 2,
            'remember_me' => true
        ], [
            'Authorization' => "Bearer {$tempToken}"
        ]);

        $response->assertStatus(200);
        
        $season = $response->json('data.season');
        
        // Verify correct fields are present
        $this->assertArrayHasKey('id', $season);
        $this->assertArrayHasKey('name', $season);
        $this->assertArrayHasKey('start_date', $season);
        $this->assertArrayHasKey('end_date', $season);
        
        // Verify problematic fields are NOT present
        $this->assertArrayNotHasKey('year', $season);
        $this->assertArrayNotHasKey('is_current', $season);
        
        // Available seasons should also use correct fields
        $availableSeasons = $response->json('data.available_seasons');
        if (!empty($availableSeasons)) {
            foreach ($availableSeasons as $availableSeason) {
                $this->assertArrayHasKey('start_date', $availableSeason);
                $this->assertArrayHasKey('end_date', $availableSeason);
                $this->assertArrayNotHasKey('year', $availableSeason);
            }
        }
    }

    /** @test */
    public function school_users_relationships_are_correct()
    {
        // Verify admin user has 2 schools
        $adminSchools = DB::table('school_users')
            ->where('user_id', $this->adminUser->id)
            ->count();
        $this->assertEquals(2, $adminSchools);

        // Verify single user has 1 school
        $singleSchools = DB::table('school_users')
            ->where('user_id', $this->singleUser->id)
            ->count();
        $this->assertEquals(1, $singleSchools);

        // Verify both users have access to school ID 2
        $adminHasSchool2 = DB::table('school_users')
            ->where('user_id', $this->adminUser->id)
            ->where('school_id', 2)
            ->exists();
        $this->assertTrue($adminHasSchool2);

        $singleHasSchool2 = DB::table('school_users')
            ->where('user_id', $this->singleUser->id)
            ->where('school_id', 2)
            ->exists();
        $this->assertTrue($singleHasSchool2);
    }
}