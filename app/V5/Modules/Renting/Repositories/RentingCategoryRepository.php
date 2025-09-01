<?php

namespace App\V5\Modules\Renting\Repositories;

use App\V5\Modules\Renting\Models\RentingCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RentingCategoryRepository
{
    public function paginate(int $schoolId, array $filters, int $page, int $limit): LengthAwarePaginator
    {
        $query = RentingCategory::query()
            ->where('school_id', $schoolId)
            ->orderBy('position')
            ->orderBy('name');

        if (isset($filters['active'])) {
            $query->where('active', (bool) $filters['active']);
        }
        if (isset($filters['parent_id'])) {
            $query->where('parent_id', (int) $filters['parent_id']);
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    public function tree(int $schoolId): array
    {
        $roots = RentingCategory::where('school_id', $schoolId)
            ->whereNull('parent_id')
            ->orderBy('position')
            ->get();

        return $roots->map(fn ($cat) => $this->serializeNode($cat))->toArray();
    }

    private function serializeNode(RentingCategory $cat): array
    {
        return [
            'id' => $cat->id,
            'name' => $cat->name,
            'slug' => $cat->slug,
            'active' => (bool) $cat->active,
            'children' => $cat->children->map(fn ($c) => $this->serializeNode($c))->toArray(),
        ];
    }

    public function find(int $id, int $schoolId): RentingCategory
    {
        return RentingCategory::where('school_id', $schoolId)->findOrFail($id);
    }

    public function create(array $data, int $schoolId): RentingCategory
    {
        $data['school_id'] = $schoolId;
        return RentingCategory::create($data);
    }

    public function update(int $id, array $data, int $schoolId): RentingCategory
    {
        $cat = $this->find($id, $schoolId);
        $cat->fill($data);
        $cat->save();
        return $cat->fresh();
    }

    public function delete(int $id, int $schoolId): bool
    {
        $cat = $this->find($id, $schoolId);
        return (bool) $cat->delete();
    }
}

