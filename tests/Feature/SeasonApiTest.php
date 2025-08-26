<?php

namespace Tests\Feature;

use App\Models\School;
use App\V5\Models\Season;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class SeasonApiTest extends TestCase
{
    use WithoutMiddleware;
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('schools');
        Schema::enableForeignKeyConstraints();

        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('school_id');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('schools');
        Schema::enableForeignKeyConstraints();
        parent::tearDown();
    }

    public function test_filter_active_season_by_school(): void
    {
        $school = School::create([
            'name' => 'Test School',
            'slug' => 'test-school',
        ]);

        $season1 = $this->postJson('/api/seasons', [
            'name' => 'S1',
            'start_date' => '2024-01-01',
            'end_date' => '2024-02-01',
            'is_active' => true,
            'school_id' => $school->id,
        ])->assertStatus(200)->json('data');

        $season2 = $this->postJson('/api/seasons', [
            'name' => 'S2',
            'start_date' => '2024-03-01',
            'end_date' => '2024-04-01',
            'is_active' => true,
            'school_id' => $school->id,
        ])->assertStatus(200)->json('data');

        $this->getJson('/api/seasons/' . $season1['id'])
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/seasons?school_id=' . $school->id . '&filterActive=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $season2['id']);
    }
}
