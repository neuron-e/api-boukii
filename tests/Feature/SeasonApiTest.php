<?php

namespace Tests\Feature;

use App\Models\School;
use App\V5\Models\Season;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeasonApiTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        DB::unprepared('DROP TRIGGER IF EXISTS seasons_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS seasons_before_update');
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

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('CREATE TRIGGER seasons_before_insert
                BEFORE INSERT ON seasons
                FOR EACH ROW
                BEGIN
                    IF NEW.is_active = 1 THEN
                        UPDATE seasons SET is_active = 0 WHERE school_id = NEW.school_id AND is_active = 1;
                    END IF;
                END');

            DB::unprepared('CREATE TRIGGER seasons_before_update
                BEFORE UPDATE ON seasons
                FOR EACH ROW
                BEGIN
                    IF NEW.is_active = 1 THEN
                        UPDATE seasons SET is_active = 0 WHERE school_id = NEW.school_id AND is_active = 1 AND id != NEW.id;
                    END IF;
                END');
        } else {
            DB::unprepared('CREATE TRIGGER seasons_before_insert
                BEFORE INSERT ON seasons
                WHEN NEW.is_active = 1
                BEGIN
                    UPDATE seasons SET is_active = 0 WHERE school_id = NEW.school_id AND is_active = 1;
                END;');

            DB::unprepared('CREATE TRIGGER seasons_before_update
                BEFORE UPDATE ON seasons
                WHEN NEW.is_active = 1
                BEGIN
                    UPDATE seasons SET is_active = 0 WHERE school_id = NEW.school_id AND is_active = 1 AND id <> NEW.id;
                END;');
        }
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::unprepared('DROP TRIGGER IF EXISTS seasons_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS seasons_before_update');
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

        $this->getJson('/api/seasons/'.$season1['id'])
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/seasons?school_id='.$school->id.'&filterActive=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $season2['id']);
    }

    public function test_database_prevents_multiple_active_seasons(): void
    {
        $school = School::create([
            'name' => 'Another School',
            'slug' => 'another-school',
        ]);

        $first = Season::create([
            'name' => 'Primera',
            'start_date' => '2024-01-01',
            'end_date' => '2024-02-01',
            'is_active' => true,
            'school_id' => $school->id,
        ]);

        $second = Season::create([
            'name' => 'Segunda',
            'start_date' => '2024-03-01',
            'end_date' => '2024-04-01',
            'is_active' => true,
            'school_id' => $school->id,
        ]);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertEquals(1, Season::where('school_id', $school->id)->where('is_active', true)->count());
    }
}
