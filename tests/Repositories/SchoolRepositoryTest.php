<?php

namespace Tests\Repositories;

use App\Models\School;
use App\Repositories\SchoolRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\ApiTestTrait;

class SchoolRepositoryTest extends TestCase
{
    use ApiTestTrait, DatabaseTransactions;

    protected SchoolRepository $schoolRepo;

    public function setUp() : void
    {
        parent::setUp();
        $this->schoolRepo = app(SchoolRepository::class);
    }

    /**
     * @test create
     */
    public function test_create_school()
    {
        $school = School::factory()->make()->toArray();

        $createdSchool = $this->schoolRepo->create($school);

        $createdSchool = $createdSchool->toArray();
        $this->assertArrayHasKey('id', $createdSchool);
        $this->assertNotNull($createdSchool['id'], 'Created School must have id specified');
        $this->assertNotNull(School::find($createdSchool['id']), 'School with given id must be in DB');
        $this->assertModelData($school, $createdSchool);
    }

    /**
     * @test read
     */
    public function test_read_school()
    {
        $school = School::factory()->create();

        $dbSchool = $this->schoolRepo->find($school->id);

        $dbSchool = $dbSchool->toArray();
        $this->assertModelData($school->toArray(), $dbSchool);
    }

    /**
     * @test update
     */
    public function test_update_school()
    {
        $school = School::factory()->create();
        $fakeSchool = School::factory()->make()->toArray();

        $updatedSchool = $this->schoolRepo->update($fakeSchool, $school->id);

        $this->assertModelData($fakeSchool, $updatedSchool->toArray());
        $dbSchool = $this->schoolRepo->find($school->id);
        $this->assertModelData($fakeSchool, $dbSchool->toArray());
    }

    /**
     * @test delete
     */
    public function test_delete_school()
    {
        $school = School::factory()->create();

        $resp = $this->schoolRepo->delete($school->id);

        $this->assertTrue($resp);
        $this->assertNull(School::find($school->id), 'School should not exist in DB');
    }
}
