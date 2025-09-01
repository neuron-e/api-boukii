<?php

namespace App\V5\Modules\Renting\Repositories;

use App\V5\Modules\Renting\Models\RentingItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RentingItemRepository
{
    public function paginate(int $schoolId, array $filters, int $page, int $limit): LengthAwarePaginator
    {
        $query = RentingItem::query()->where('school_id', $schoolId);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }
        if (isset($filters['active'])) {
            $query->where('active', (bool) $filters['active']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function (Builder $q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('sku', 'like', "%{$s}%");
            });
        }

        $query->orderBy('name');
        return $query->paginate($limit, ['*'], 'page', $page);
    }

    public function find(int $id, int $schoolId): RentingItem
    {
        return RentingItem::where('school_id', $schoolId)->findOrFail($id);
    }

    public function create(array $data, int $schoolId): RentingItem
    {
        $data['school_id'] = $schoolId;
        return RentingItem::create($data);
    }

    public function update(int $id, array $data, int $schoolId): RentingItem
    {
        $item = $this->find($id, $schoolId);
        $item->fill($data);
        $item->save();
        return $item->fresh();
    }

    public function delete(int $id, int $schoolId): bool
    {
        $item = $this->find($id, $schoolId);
        return (bool) $item->delete();
    }
}

