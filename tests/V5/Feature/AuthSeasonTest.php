<?php

namespace Tests\V5\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthSeasonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Disable FKs to allow clean drops across drivers
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('user_season_roles');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('users');
        Schema::dropIfExists('personal_access_tokens');

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('type', 100);
            $table->boolean('active')->default(1);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('school_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_season_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('season_id');
            $table->string('role');
            $table->timestamps();
            $table->unique(['user_id', 'season_id']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });
        Schema::enableForeignKeyConstraints();
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('user_season_roles');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('users');
        Schema::dropIfExists('personal_access_tokens');
        Schema::enableForeignKeyConstraints();
        parent::tearDown();
    }

    public function test_login_with_season_context_returns_token_and_permissions(): void
    {
        $seasonId = DB::table('seasons')->insertGetId([
            'name' => 'S1',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_active' => true,
            'school_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::create([
            'id' => 1,
            'email' => 'user@test.com',
            'password' => Hash::make('pass'),
            'type' => 'admin',
            'active' => 1,
        ]);

        DB::table('user_season_roles')->insert([
            'user_id' => $user->id,
            'season_id' => $seasonId,
            'role' => 'manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'view schools',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_has_permissions')->insert([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);

        $response = $this->postJson('/api/v5/auth/login', [
            'email' => 'user@test.com',
            'password' => 'pass',
            'season_id' => $seasonId,
        ]);

        $response->assertStatus(200);
        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertEquals('manager', $response->json('role'));
        $this->assertEquals($seasonId, $response->json('season_id'));
        $this->assertEquals(['view schools'], $response->json('permissions'));

        $this->actingAs($user);
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v5/auth/permissions?season_id=' . $seasonId)
            ->assertStatus(200)
            ->assertExactJson(['view schools']);
    }
}
