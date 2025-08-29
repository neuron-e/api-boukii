<?php

namespace App\V5\Modules\Renting\Repositories;

use App\V5\Modules\Booking\Models\BookingEquipment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RentingRepository
{
    public function paginate(int $schoolId, int $seasonId, array $filters, int $page, int $limit): LengthAwarePaginator
    {
        $query = BookingEquipment::query()
            ->whereHas('booking', function (Builder $q) use ($schoolId, $seasonId) {
                $q->where('school_id', $schoolId)
                  ->where('season_id', $seasonId);
            });

        if (!empty($filters['type'])) {
            $query->where('equipment_type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'rented') $query->rented();
            if ($filters['status'] === 'returned') $query->returned();
            if ($filters['status'] === 'outstanding') $query->outstanding();
        }

        $query->orderBy('created_at', 'desc');
        return $query->paginate($limit, ['*'], 'page', $page);
    }

    public function find(int $id, int $schoolId, int $seasonId): BookingEquipment
    {
        return BookingEquipment::where('id', $id)
            ->whereHas('booking', function (Builder $q) use ($schoolId, $seasonId) {
                $q->where('school_id', $schoolId)
                  ->where('season_id', $seasonId);
            })
            ->firstOrFail();
    }

    public function create(array $data, int $schoolId, int $seasonId): BookingEquipment
    {
        // Requires a valid booking_id that belongs to the same school/season
        return BookingEquipment::create($data);
    }

    public function update(int $id, array $data, int $schoolId, int $seasonId): BookingEquipment
    {
        $item = $this->find($id, $schoolId, $seasonId);
        $item->fill($data);
        $item->save();
        return $item->fresh();
    }

    public function delete(int $id, int $schoolId, int $seasonId): bool
    {
        $item = $this->find($id, $schoolId, $seasonId);
        return (bool) $item->delete();
    }
}
