<?php

namespace App\V5\Modules\Activity\Repositories;

use App\Models\Course;
use App\Models\Season;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ActivityRepository
{
    private const ACTIVITY_TYPE = 3; // course_type=3 represents activities

    public function paginate(int $schoolId, int $seasonId, array $filters, int $page, int $limit): LengthAwarePaginator
    {
        $season = Season::findOrFail($seasonId);

        $query = $this->baseScopedQuery($schoolId, $season)
            ->where('course_type', self::ACTIVITY_TYPE);

        if (!empty($filters['active'])) {
            $query->where('active', (bool) $filters['active']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    public function find(int $id, int $schoolId, int $seasonId): Course
    {
        $season = Season::findOrFail($seasonId);
        return $this->baseScopedQuery($schoolId, $season)
            ->where('course_type', self::ACTIVITY_TYPE)
            ->findOrFail($id);
    }

    public function create(array $data, int $schoolId, int $seasonId): Course
    {
        $data['school_id'] = $schoolId;
        $data['course_type'] = self::ACTIVITY_TYPE;
        $activity = new Course($data);
        $activity->save();
        return $activity->fresh();
    }

    public function update(int $id, array $data, int $schoolId, int $seasonId): Course
    {
        $season = Season::findOrFail($seasonId);
        $activity = $this->baseScopedQuery($schoolId, $season)
            ->where('course_type', self::ACTIVITY_TYPE)
            ->findOrFail($id);
        $activity->fill($data);
        $activity->save();
        return $activity->fresh();
    }

    public function delete(int $id, int $schoolId, int $seasonId): bool
    {
        $season = Season::findOrFail($seasonId);
        $activity = $this->baseScopedQuery($schoolId, $season)
            ->where('course_type', self::ACTIVITY_TYPE)
            ->findOrFail($id);
        return (bool) $activity->delete();
    }

    private function baseScopedQuery(int $schoolId, Season $season): Builder
    {
        return Course::query()
            ->where('school_id', $schoolId)
            ->where(function (Builder $q) use ($season) {
                $q->whereHas('courseDates', function (Builder $d) use ($season) {
                    $d->whereBetween('date', [$season->start_date, $season->end_date]);
                })
                ->orWhere(function (Builder $d) use ($season) {
                    $d->whereNotNull('date_start')
                      ->whereNotNull('date_end')
                      ->where(function (Builder $ov) use ($season) {
                          $ov->whereBetween('date_start', [$season->start_date, $season->end_date])
                             ->orWhereBetween('date_end', [$season->start_date, $season->end_date]);
                      });
                });
            });
    }
}
