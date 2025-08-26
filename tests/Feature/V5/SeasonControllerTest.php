<?php

namespace Tests\Feature\V5;

use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Models\Season;
use App\V5\Models\UserSeasonRole;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class SeasonControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private School $school;
    private Season $season;
    private string $baseUrl = '/api/v5/seasons';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user and school
        $this->user = User::factory()->create([
            'email' => 'test@season.com',
            'name' => 'Season Test User',
        ]);
        
        $this->school = School::factory()->create([
            'name' => 'Test School for Seasons',
            'slug' => 'test-school-seasons',
        ]);
        
        $this->user->schools()->attach($this->school->id);

        $this->season = Season::factory()->create([
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);

        UserSeasonRole::create([
            'user_id' => $this->user->id,
            'season_id' => $this->season->id,
            'role' => 'admin'
        ]);

        Sanctum::actingAs($this->user, ['*'], 'api_v5');

        $this->withHeaders([
            'X-School-ID' => (string) $this->school->id,
            'X-Season-ID' => (string) $this->season->id,
        ]);
    }

    /** @test */
    public function test_case_a_school_with_active_season_returns_automatically()
    {
        // Arrange: Create an active season for the school
        $activeSeason = Season::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Active Season 2024-2025',
            'start_date' => Carbon::now()->subMonth(),
            'end_date' => Carbon::now()->addMonths(6),
            'is_active' => true,
            'is_closed' => false,
            'is_historical' => false,
        ]);

        // Act: Get current season
        $response = $this->getJson("{$this->baseUrl}/current");

        // Assert: Should return the active season
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Current season retrieved successfully',
                ])
                ->assertJsonPath('data.id', $activeSeason->id)
                ->assertJsonPath('data.name', 'Active Season 2024-2025')
                ->assertJsonPath('data.is_active', true);
    }

    /** @test */
    public function test_case_b_school_without_active_season_lists_available_seasons()
    {
        // Arrange: Create multiple seasons, none explicitly active
        $season1 = Season::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Season 1',
            'start_date' => Carbon::now()->subYear(),
            'end_date' => Carbon::now()->subMonths(6),
            'is_active' => false,
        ]);
        
        $season2 = Season::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Season 2',
            'start_date' => Carbon::now()->addMonth(),
            'end_date' => Carbon::now()->addMonths(8),
            'is_active' => false,
        ]);

        // Act: Get all seasons
        $response = $this->getJson($this->baseUrl);

        // Assert: Should return all seasons for the school
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Seasons retrieved successfully',
                ])
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('data.0.school_id', $this->school->id)
                ->assertJsonPath('data.1.school_id', $this->school->id);
    }

    /** @test */
    public function test_case_c_create_season_with_permissions()
    {
        // Arrange: Season creation data
        $seasonData = [
            'name' => 'New Season 2025-2026',
            'description' => 'Test season creation',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_active' => true,
        ];

        // Act: Create new season
        $response = $this->postJson($this->baseUrl, $seasonData);

        // Assert: Should successfully create season
        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Season created successfully',
                ])
                ->assertJsonPath('data.name', 'New Season 2025-2026')
                ->assertJsonPath('data.is_active', true);

        // Verify season was created in database
        $this->assertDatabaseHas('seasons', [
            'name' => 'New Season 2025-2026',
            'school_id' => $this->school->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_case_d_create_season_without_permissions_returns_403()
    {
        // Arrange: Create a different user without permissions
        $unauthorizedUser = User::factory()->create([
            'email' => 'unauthorized@test.com',
            'name' => 'Unauthorized User',
        ]);
        
        // Act as unauthorized user without school association
        Sanctum::actingAs($unauthorizedUser, ['*'], 'api_v5');
        
        $seasonData = [
            'name' => 'Unauthorized Season',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ];

        // Act: Attempt to create season without proper school context
        $response = $this->withHeaders([
            'X-School-ID' => (string) $this->school->id,
        ])->postJson($this->baseUrl, $seasonData);

        // Assert: Should return forbidden (in this case, likely school context error)
        // The actual status might be 403 or 500 depending on middleware implementation
        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                ]);
    }

    /** @test */
    public function test_get_specific_season_by_id()
    {
        // Arrange: Create a season
        $season = Season::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Test Season Details',
            'description' => 'Season for testing details endpoint',
        ]);

        // Act: Get season by ID
        $response = $this->getJson("{$this->baseUrl}/{$season->id}");

        // Assert: Should return season details
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Season retrieved successfully',
                ])
                ->assertJsonPath('data.id', $season->id)
                ->assertJsonPath('data.name', 'Test Season Details')
                ->assertJsonPath('data.description', 'Season for testing details endpoint');
    }

    /** @test */
    public function test_update_season_successfully()
    {
        // Arrange: Create a season to update
        $season = Season::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Original Season Name',
            'is_active' => false,
        ]);

        $updateData = [
            'name' => 'Updated Season Name',
            'description' => 'Updated description',
            'is_active' => true,
        ];

        // Act: Update season
        $response = $this->putJson("{$this->baseUrl}/{$season->id}", $updateData);

        // Assert: Should successfully update season
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Season updated successfully',
                ])
                ->assertJsonPath('data.name', 'Updated Season Name')
                ->assertJsonPath('data.is_active', true);

        // Verify database was updated
        $this->assertDatabaseHas('seasons', [
            'id' => $season->id,
            'name' => 'Updated Season Name',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_delete_season_successfully()
    {
        // Arrange: Create a season to delete
        $season = Season::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Season To Delete',
            'is_closed' => false,
            'is_historical' => false,
        ]);

        // Act: Delete season
        $response = $this->deleteJson("{$this->baseUrl}/{$season->id}");

        // Assert: Should successfully delete season
        $response->assertStatus(204)
                ->assertJson([
                    'success' => true,
                    'message' => 'Season deleted successfully',
                ]);

        // Verify season was soft deleted
        $this->assertSoftDeleted('seasons', [
            'id' => $season->id,
        ]);
    }

    /** @test */
    public function test_close_season_successfully()
    {
        // Arrange: Create an open season
        $season = Season::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Season To Close',
            'is_closed' => false,
            'is_historical' => false,
        ]);

        // Act: Close season
        $response = $this->postJson("{$this->baseUrl}/{$season->id}/close");

        // Assert: Should successfully close season
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Season closed successfully',
                ])
                ->assertJsonPath('data.is_closed', true);

        // Verify database was updated
        $this->assertDatabaseHas('seasons', [
            'id' => $season->id,
            'is_closed' => true,
        ]);
    }

    /** @test */
    public function test_cannot_access_season_from_different_school()
    {
        // Arrange: Create a season for a different school
        $otherSchool = School::factory()->create([
            'name' => 'Other School',
            'slug' => 'other-school',
        ]);
        
        $otherSeason = Season::factory()->create([
            'school_id' => $otherSchool->id,
            'name' => 'Other School Season',
        ]);

        // Act: Try to access season from different school
        $response = $this->getJson("{$this->baseUrl}/{$otherSeason->id}");

        // Assert: Should not find the season (scoped to current school)
        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Season not found or not accessible',
                ]);
    }

    /** @test */
    public function test_context_middleware_applied()
    {
        // Arrange: Remove school and season context headers
        $this->withoutHeaders(['X-School-ID', 'X-Season-ID']);

        // Act: Try to access seasons without required context
        $response = $this->getJson($this->baseUrl);

        // Assert: Should fail due to missing context
        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                ]);
    }

    /** @test */
    public function test_season_validation_on_create()
    {
        // Arrange: Invalid season data
        $invalidSeasonData = [
            // Missing required fields
            'start_date' => 'invalid-date',
            'end_date' => 'also-invalid',
        ];

        // Act: Attempt to create season with invalid data
        $response = $this->postJson($this->baseUrl, $invalidSeasonData);

        // Assert: Should return validation errors
        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Validation failed',
                ])
                ->assertJsonStructure([
                    'success',
                    'message',
                    'error_code',
                    'errors' => [
                        'name',
                        'start_date',
                        'end_date',
                    ],
                ]);
    }

    /** @test */
    public function test_activate_season_and_filter_active()
    {
        $season1 = $this->postJson($this->baseUrl, [
            'name' => 'S1',
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-30',
            'is_active' => true,
        ])->assertStatus(201)->json('data');

        $season2 = $this->postJson($this->baseUrl, [
            'name' => 'S2',
            'start_date' => '2024-07-01',
            'end_date' => '2024-12-31',
            'is_active' => false,
        ])->assertStatus(201)->json('data');

        $this->postJson("{$this->baseUrl}/{$season2['id']}/activate")
            ->assertStatus(200);

        $this->getJson("{$this->baseUrl}/{$season1['id']}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->getJson("{$this->baseUrl}?school_id={$this->school->id}&filterActive=1")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $season2['id']);
    }
}