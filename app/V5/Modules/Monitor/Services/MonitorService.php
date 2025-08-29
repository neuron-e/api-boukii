<?php

namespace App\V5\Modules\Monitor\Services;

use App\V5\Modules\Monitor\Repositories\MonitorRepository;

class MonitorService
{
    public function __construct(private MonitorRepository $repo)
    {
    }

    public function list(int $schoolId, int $seasonId, array $filters, int $page, int $limit): array
    {
        return $this->repo->paginate($schoolId, $seasonId, $filters, $page, $limit);
    }

    public function find(int $id, int $schoolId, int $seasonId): array
    {
        return $this->repo->find($id, $schoolId, $seasonId);
    }

    public function create(array $data, int $schoolId, int $seasonId): array
    {
        return $this->repo->create($data, $schoolId, $seasonId);
    }

    public function update(int $id, array $data, int $schoolId, int $seasonId): array
    {
        return $this->repo->update($id, $data, $schoolId, $seasonId);
    }

    public function delete(int $id, int $schoolId, int $seasonId): bool
    {
        return $this->repo->delete($id, $schoolId, $seasonId);
    }
}

