<?php

namespace App\V5\Modules\Renting\Services;

use App\V5\Modules\Renting\Repositories\RentingCategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\V5\Modules\Renting\Models\RentingCategory;

class RentingCategoryService
{
    public function __construct(private RentingCategoryRepository $repo)
    {
    }

    public function list(int $schoolId, array $filters, int $page, int $limit): LengthAwarePaginator
    {
        return $this->repo->paginate($schoolId, $filters, $page, $limit);
    }

    public function tree(int $schoolId): array
    {
        return $this->repo->tree($schoolId);
    }

    public function find(int $id, int $schoolId): RentingCategory
    {
        return $this->repo->find($id, $schoolId);
    }

    public function create(array $data, int $schoolId): RentingCategory
    {
        return $this->repo->create($data, $schoolId);
    }

    public function update(int $id, array $data, int $schoolId): RentingCategory
    {
        return $this->repo->update($id, $data, $schoolId);
    }

    public function delete(int $id, int $schoolId): bool
    {
        return $this->repo->delete($id, $schoolId);
    }
}

