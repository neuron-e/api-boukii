<?php

namespace App\V5\Modules\Monitor\Repositories;

use App\Models\Monitor;
use App\Models\MonitorsSchool;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MonitorRepository
{
    public function paginate(int $schoolId, int $seasonId, array $filters, int $page, int $limit): LengthAwarePaginator
    {
        $query = $this->baseScopedQuery($schoolId);

        if (isset($filters['active'])) {
            $query->where('active', (bool) $filters['active']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function (Builder $q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $query->orderBy('last_name')->orderBy('first_name');
        return $query->paginate($limit, ['*'], 'page', $page);
    }

    public function find(int $id, int $schoolId, int $seasonId): Monitor
    {
        return $this->baseScopedQuery($schoolId)->findOrFail($id);
    }

    public function create(array $data, int $schoolId, int $seasonId): Monitor
    {
        $monitor = new Monitor($data);
        $monitor->save();
        MonitorsSchool::firstOrCreate([
            'monitor_id' => $monitor->id,
            'school_id' => $schoolId,
        ], [
            'active_school' => true,
            'status_updated_at' => now(),
            'accepted_at' => now(),
        ]);
        return $monitor->fresh();
    }

    public function update(int $id, array $data, int $schoolId, int $seasonId): Monitor
    {
        $monitor = $this->baseScopedQuery($schoolId)->findOrFail($id);
        $monitor->fill($data);
        $monitor->save();
        return $monitor->fresh();
    }

    public function delete(int $id, int $schoolId, int $seasonId): bool
    {
        $monitor = $this->baseScopedQuery($schoolId)->findOrFail($id);
        return (bool) $monitor->delete();
    }

    private function baseScopedQuery(int $schoolId): Builder
    {
        return Monitor::query()
            ->whereHas('monitorsSchools', function (Builder $q) use ($schoolId) {
                $q->where('school_id', $schoolId)
                  ->where('active_school', 1);
            });
    }
}
