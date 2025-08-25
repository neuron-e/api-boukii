<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\School;
use App\Models\Client;
use App\Models\ClientsSchool;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['activitylog.enabled' => false]);

        Schema::disableForeignKeyConstraints();
        Schema::dropAllTables();
        Schema::enableForeignKeyConstraints();

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

        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->string('slug');
            $table->boolean('active')->default(1);
            $table->json('settings')->nullable();
            $table->timestamp('deleted_at')->nullable();
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

        Schema::create('clients', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date');
            $table->string('telephone')->default('');
            $table->bigInteger('user_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clients_schools', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->bigInteger('client_id');
            $table->bigInteger('school_id');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('school_users', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->bigInteger('school_id');
            $table->bigInteger('user_id');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('monitors', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->bigInteger('user_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->boolean('active')->default(1);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('clients_schools');
        Schema::dropIfExists('school_users');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('monitors');
        Schema::dropIfExists('users');
        Schema::dropIfExists('schools');
        Schema::dropIfExists('personal_access_tokens');
        parent::tearDown();
    }

    public function test_admin_login()
    {
        $user = User::create([
            'id' => 1,
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'type' => 'admin',
            'active' => 1,
        ]);

        $school = School::create([
            'name' => 'Admin School',
            'description' => 'desc',
            'slug' => 'admin-school',
            'active' => 1,
        ]);

        DB::table('school_users')->insert([
            'id' => 1,
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.user.id', $user->id);
        $response->assertJsonPath('data.user.schools.0.id', $school->id);
    }

    public function test_superadmin_login_returns_all_active_schools_without_associations()
    {
        $user = User::create([
            'id' => 2,
            'email' => 'superadmin@test.com',
            'password' => Hash::make('password'),
            'type' => 'superadmin',
            'active' => 1,
        ]);

        $school1 = School::create([
            'name' => 'Super School',
            'description' => 'desc',
            'slug' => 'super-school',
            'active' => 1,
        ]);

        $school2 = School::create([
            'name' => 'Another School',
            'description' => 'desc',
            'slug' => 'another-school',
            'active' => 1,
        ]);

        School::create([
            'name' => 'Inactive School',
            'description' => 'desc',
            'slug' => 'inactive-school',
            'active' => 0,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'superadmin@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.user.id', $user->id);
        $response->assertJsonCount(2, 'data.user.schools');
        $schoolIds = array_column($response->json('data.user.schools'), 'id');
        $this->assertEqualsCanonicalizing([$school1->id, $school2->id], $schoolIds);
    }

    public function test_teach_login()
    {
        $user = User::create([
            'id' => 3,
            'email' => 'monitor@test.com',
            'password' => Hash::make('password'),
            'type' => 'monitor',
            'active' => 1,
        ]);

        $response = $this->postJson('/api/teach/login', [
            'email' => 'monitor@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.user.id', $user->id);
    }

    public function test_booking_page_login()
    {
        $school = School::create([
            'name' => 'My School',
            'description' => 'desc',
            'slug' => 'myschool',
            'active' => 1,
            'settings' => json_encode([]),
        ]);

        $user = User::create([
            'id' => 4,
            'email' => 'client@test.com',
            'password' => Hash::make('password'),
            'type' => 'client',
            'active' => 1,
        ]);

        $client = Client::withoutEvents(function () use ($user) {
            return Client::create([
                'id' => 5,
                'first_name' => 'Name',
                'last_name' => 'Surname',
                'birth_date' => '1990-01-01',
                'telephone' => '',
                'user_id' => $user->id,
            ]);
        });

        ClientsSchool::create([
            'id' => 6,
            'client_id' => $client->id,
            'school_id' => $school->id,
        ]);

        $response = $this->withHeaders(['slug' => $school->slug])->postJson('/api/slug/login', [
            'email' => 'client@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.user.id', $user->id);
    }
}
