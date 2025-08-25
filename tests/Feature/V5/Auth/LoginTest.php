<?php

namespace Tests\Feature\V5\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $singleSchool;
    protected $multipleSchools;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@boukii.com',
            'password' => Hash::make('password123'),
            'active' => 1,
            'type' => 'admin'
        ]);

        // Create schools
        $this->singleSchool = School::create([
            'name' => 'Single School',
            'slug' => 'single-school',
            'active' => 1,
            'logo' => 'logo1.png'
        ]);

        $this->multipleSchools = collect([
            School::create([
                'name' => 'Multi School 1',
                'slug' => 'multi-school-1',
                'active' => 1,
                'logo' => 'logo2.png'
            ]),
            School::create([
                'name' => 'Multi School 2',
                'slug' => 'multi-school-2',
                'active' => 1,
                'logo' => 'logo3.png'
            ])
        ]);

        // Create seasons for schools
        Season::create([
            'school_id' => $this->singleSchool->id,
            'name' => 'Season 2024-25',
            'year' => '2024-25',
            'is_active' => true,
            'is_current' => true,
            'start_date' => now()->subMonths(3),
            'end_date' => now()->addMonths(9)
        ]);

        foreach ($this->multipleSchools as $school) {
            Season::create([
                'school_id' => $school->id,
                'name' => 'Season 2024-25',
                'year' => '2024-25',
                'is_active' => true,
                'is_current' => true,
                'start_date' => now()->subMonths(3),
                'end_date' => now()->addMonths(9)
            ]);
        }
    }

    /** @test */
    public function test_check_user_with_single_school_returns_correct_data()
    {
        // Attach user to single school
        $this->user->schools()->attach($this->singleSchool->id);

        $response = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'test@boukii.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => ['id', 'name', 'email', 'role'],
                        'schools' => [
                            '*' => ['id', 'name', 'slug', 'logo', 'user_role', 'can_administer']
                        ],
                        'requires_school_selection'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'user' => [
                            'email' => 'test@boukii.com',
                            'role' => 'admin'
                        ],
                        'requires_school_selection' => false // Single school = no selection needed
                    ]
                ]);

        // Should not include temp_token for single school
        $this->assertArrayNotHasKey('temp_token', $response->json('data'));
        $this->assertCount(1, $response->json('data.schools'));
    }

    /** @test */
    public function test_check_user_with_multiple_schools_returns_temp_token()
    {
        // Attach user to multiple schools
        foreach ($this->multipleSchools as $school) {
            $this->user->schools()->attach($school->id);
        }

        $response = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'test@boukii.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
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
                            'email' => 'test@boukii.com',
                            'role' => 'admin'
                        ],
                        'requires_school_selection' => true // Multiple schools = selection needed
                    ]
                ]);

        // Should include temp_token for multi-school
        $this->assertArrayHasKey('temp_token', $response->json('data'));
        $this->assertNotEmpty($response->json('data.temp_token'));
        $this->assertCount(2, $response->json('data.schools'));
    }

    /** @test */
    public function test_check_user_with_invalid_credentials_fails()
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
    public function test_check_user_with_inactive_user_fails()
    {
        // Make user inactive
        $this->user->update(['active' => 0]);
        $this->user->schools()->attach($this->singleSchool->id);

        $response = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'test@boukii.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Cuenta inactiva. Contacte al administrador.'
                ]);
    }

    /** @test */
    public function test_check_user_with_no_schools_fails()
    {
        // Don't attach any schools to user
        $response = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'test@boukii.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Usuario sin escuelas asignadas.'
                ]);
    }

    /** @test */
    public function test_superadmin_without_school_assignments_gets_all_active_schools()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::create(['name' => 'superadmin']);

        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@boukii.com',
            'password' => Hash::make('password123'),
            'active' => 1,
            'type' => 'admin'
        ]);

        $superAdmin->assignRole($role);

        $response = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'super@boukii.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.role', 'superadmin');

        $this->assertCount(3, $response->json('data.schools'));
    }

    /** @test */
    public function test_select_school_with_valid_temp_token_completes_login()
    {
        // Attach user to multiple schools to generate temp token
        foreach ($this->multipleSchools as $school) {
            $this->user->schools()->attach($school->id);
        }

        // First, get temp token
        $checkUserResponse = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'test@boukii.com',
            'password' => 'password123'
        ]);

        $tempToken = $checkUserResponse->json('data.temp_token');
        $schoolId = $this->multipleSchools->first()->id;

        // Then select school with temp token
        $response = $this->postJson('/api/v5/auth/select-school', [
            'school_id' => $schoolId,
            'remember_me' => true
        ], [
            'Authorization' => "Bearer {$tempToken}"
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => ['id', 'name', 'email'],
                        'school' => ['id', 'name', 'slug', 'logo'],
                        'season' => ['id', 'name', 'year', 'is_current'],
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
                            'email' => 'test@boukii.com'
                        ],
                        'school' => [
                            'id' => $schoolId
                        ],
                        'token_type' => 'Bearer'
                    ]
                ]);

        // Should return a full access token
        $this->assertNotEmpty($response->json('data.access_token'));
    }

    /** @test */
    public function test_select_school_without_token_fails()
    {
        $response = $this->postJson('/api/v5/auth/select-school', [
            'school_id' => $this->singleSchool->id,
            'remember_me' => true
        ]);

        $response->assertStatus(401); // Unauthorized
    }

    /** @test */
    public function test_select_school_with_invalid_school_id_fails()
    {
        // Attach user to multiple schools to generate temp token
        foreach ($this->multipleSchools as $school) {
            $this->user->schools()->attach($school->id);
        }

        // Get temp token
        $checkUserResponse = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'test@boukii.com',
            'password' => 'password123'
        ]);

        $tempToken = $checkUserResponse->json('data.temp_token');

        // Try to select non-existent school
        $response = $this->postJson('/api/v5/auth/select-school', [
            'school_id' => 99999, // Non-existent school
            'remember_me' => true
        ], [
            'Authorization' => "Bearer {$tempToken}"
        ]);

        $response->assertStatus(422) // Validation error
                ->assertJsonValidationErrors(['school_id']);
    }

    /** @test */
    public function test_select_school_user_has_no_access_to_school_fails()
    {
        // Create another school that user has no access to
        $forbiddenSchool = School::create([
            'name' => 'Forbidden School',
            'slug' => 'forbidden-school',
            'active' => 1,
            'logo' => 'forbidden.png'
        ]);

        // Attach user to multiple schools to generate temp token
        foreach ($this->multipleSchools as $school) {
            $this->user->schools()->attach($school->id);
        }

        // Get temp token
        $checkUserResponse = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'test@boukii.com',
            'password' => 'password123'
        ]);

        $tempToken = $checkUserResponse->json('data.temp_token');

        // Try to select school user has no access to
        $response = $this->postJson('/api/v5/auth/select-school', [
            'school_id' => $forbiddenSchool->id,
            'remember_me' => true
        ], [
            'Authorization' => "Bearer {$tempToken}"
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Acceso denegado a esta escuela'
                ]);
    }

    /** @test */
    public function test_only_active_schools_are_returned()
    {
        // Create an inactive school
        $inactiveSchool = School::create([
            'name' => 'Inactive School',
            'slug' => 'inactive-school',
            'active' => 0, // Inactive
            'logo' => 'inactive.png'
        ]);

        // Attach user to both active and inactive schools
        $this->user->schools()->attach($this->singleSchool->id);
        $this->user->schools()->attach($inactiveSchool->id);

        $response = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'test@boukii.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200);
        
        // Should only return the active school
        $schools = $response->json('data.schools');
        $this->assertCount(1, $schools);
        $this->assertEquals($this->singleSchool->id, $schools[0]['id']);
    }

    /** @test */
    public function test_soft_deleted_schools_are_not_returned()
    {
        // Create a soft-deleted school
        $deletedSchool = School::create([
            'name' => 'Deleted School',
            'slug' => 'deleted-school',
            'active' => 1,
            'logo' => 'deleted.png',
            'deleted_at' => now() // Soft deleted
        ]);

        // Attach user to both normal and soft-deleted schools
        $this->user->schools()->attach($this->singleSchool->id);
        $this->user->schools()->attach($deletedSchool->id);

        $response = $this->postJson('/api/v5/auth/check-user', [
            'email' => 'test@boukii.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200);
        
        // Should only return the active, non-deleted school
        $schools = $response->json('data.schools');
        $this->assertCount(1, $schools);
        $this->assertEquals($this->singleSchool->id, $schools[0]['id']);
    }
}