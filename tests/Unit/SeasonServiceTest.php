<?php

namespace Tests\Unit;

use App\V5\Models\Season;
use App\V5\Modules\Season\Repositories\SeasonRepository;
use App\V5\Modules\Season\Services\SeasonService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeasonServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('seasons');
        Schema::enableForeignKeyConstraints();

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('hour_start')->nullable();
            $table->time('hour_end')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('vacation_days')->nullable();
            $table->unsignedBigInteger('school_id');
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('seasons');
        Schema::enableForeignKeyConstraints();
        parent::tearDown();
    }

    private function getService(): SeasonService
    {
        return new SeasonService(new SeasonRepository());
    }

    public function test_create_and_find_season(): void
    {
        $service = $this->getService();

        $season = $service->createSeason([
            'name' => 'Winter',
            'start_date' => '2024-01-01',
            'end_date' => '2024-02-01',
            'is_active' => false,
            'school_id' => 1,
        ]);

        $found = $service->find($season->id);

        $this->assertEquals('Winter', $found->name);
    }

    public function test_activate_season_sets_active_flag(): void
    {
        $service = $this->getService();
        $season = Season::create([
            'name' => 'Spring',
            'start_date' => '2024-03-01',
            'end_date' => '2024-04-01',
            'is_active' => false,
            'school_id' => 1,
        ]);

        $updated = $service->activateSeason($season->id);

        $this->assertTrue($updated->is_active);
    }

    public function test_create_active_season_unsets_previous_active_seasons(): void
    {
        $service = $this->getService();
        $old = Season::create([
            'name' => 'Old',
            'start_date' => '2024-01-01',
            'end_date' => '2024-02-01',
            'is_active' => true,
            'school_id' => 1,
        ]);

        $season = $service->createSeason([
            'name' => 'New',
            'start_date' => '2024-03-01',
            'end_date' => '2024-04-01',
            'is_active' => true,
            'school_id' => 1,
        ]);

        $old->refresh();

        $this->assertFalse($old->is_active);
        $this->assertTrue($season->is_active);
    }

    public function test_activate_season_unsets_other_active_seasons(): void
    {
        $service = $this->getService();
        $old = Season::create([
            'name' => 'Old',
            'start_date' => '2024-01-01',
            'end_date' => '2024-02-01',
            'is_active' => true,
            'school_id' => 1,
        ]);
        $season = Season::create([
            'name' => 'New',
            'start_date' => '2024-03-01',
            'end_date' => '2024-04-01',
            'is_active' => false,
            'school_id' => 1,
        ]);

        $updated = $service->activateSeason($season->id);

        $old->refresh();

        $this->assertFalse($old->is_active);
        $this->assertTrue($updated->is_active);
    }

    public function test_clone_season_creates_inactive_copy(): void
    {
        $service = $this->getService();
        $season = Season::create([
            'name' => 'Summer',
            'start_date' => '2024-06-01',
            'end_date' => '2024-07-01',
            'is_active' => true,
            'school_id' => 1,
        ]);

        $clone = $service->cloneSeason($season->id);

        $this->assertNotNull($clone);
        $this->assertNotEquals($season->id, $clone->id);
        $this->assertFalse($clone->is_active);
        $this->assertEquals($season->name, $clone->name);
    }

    public function test_close_season_marks_closed(): void
    {
        $service = $this->getService();
        $season = Season::create([
            'name' => 'Autumn',
            'start_date' => '2024-09-01',
            'end_date' => '2024-10-01',
            'is_active' => true,
            'is_closed' => false,
            'school_id' => 1,
        ]);

        $closed = $service->closeSeason($season->id);

        $this->assertTrue($closed->is_closed);
        $this->assertFalse($closed->is_active);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_get_active_seasons_returns_only_active(): void
    {
        $service = $this->getService();
        Season::create([
            'name' => 'Active 1',
            'start_date' => '2024-11-01',
            'end_date' => '2024-12-01',
            'is_active' => true,
            'school_id' => 1,
        ]);
        Season::create([
            'name' => 'Inactive',
            'start_date' => '2025-01-01',
            'end_date' => '2025-02-01',
            'is_active' => false,
            'school_id' => 1,
        ]);

        $active = $service->getActiveSeasons(1);

        $this->assertCount(1, $active);
        $this->assertEquals('Active 1', $active->first()->name);
    }
}
