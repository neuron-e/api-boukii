<?php

namespace Tests\V5\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class RouteNamesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasTable('schools')) {
            Schema::create('schools', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('description');
                $table->string('slug')->nullable();
                $table->boolean('active')->default(1);
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('schools');
        parent::tearDown();
    }
    public function test_seasons_index_route_requires_authentication(): void
    {
        $response = $this->getJson(route('v5.seasons.index'));
        $response->assertStatus(401);
    }

    public function test_login_route_returns_validation_error_without_credentials(): void
    {
        $response = $this->postJson(route('v5.auth.login'));
        $response->assertStatus(422);
    }

    public function test_schools_index_route_requires_authentication(): void
    {
        $response = $this->getJson(route('v5.schools.index'));
        $response->assertStatus(401);
    }

    public function test_schools_show_route_requires_authentication(): void
    {
        $id = \DB::table('schools')->insertGetId([
            'name' => 'Test',
            'description' => 'Test',
            'slug' => 'test',
            'active' => 1,
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson(route('v5.schools.show', $id));
        $response->assertStatus(401);
    }
}
