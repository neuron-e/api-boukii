<?php

namespace App\V5\Modules\Activity\Services;

use App\V5\Modules\Activity\Repositories\ActivityRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Course;

class ActivityService
{
    public function __construct(private ActivityRepository $repo)
    {
    }

    public function list(int $schoolId, int $seasonId, array $filters, int $page, int $limit): LengthAwarePaginator
    {
        return $this->repo->paginate($schoolId, $seasonId, $filters, $page, $limit);
    }

    public function find(int $id, int $schoolId, int $seasonId): Course
    {
        return $this->repo->find($id, $schoolId, $seasonId);
    }

    public function create(array $data, int $schoolId, int $seasonId): Course
    {
        return $this->repo->create($data, $schoolId, $seasonId);
    }

    public function update(int $id, array $data, int $schoolId, int $seasonId): Course
    {
        return $this->repo->update($id, $data, $schoolId, $seasonId);
    }

    public function delete(int $id, int $schoolId, int $seasonId): bool
    {
        return $this->repo->delete($id, $schoolId, $seasonId);
    }
}
