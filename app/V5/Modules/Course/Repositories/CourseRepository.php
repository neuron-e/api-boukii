<?php

namespace App\V5\Modules\Course\Repositories;

use App\Models\Course;
use App\Models\Season;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class CourseRepository
{
    public function paginate(int $schoolId, int $seasonId, array $filters, int $page, int $limit): LengthAwarePaginator
    {
        $season = Season::findOrFail($seasonId);

        $query = $this->baseScopedQuery($schoolId, $season)
            ->with(['courseDates' => function ($q) use ($season) {
                $q->whereBetween('date', [$season->start_date, $season->end_date]);
            }]);

        // Simple filters (extend as needed)
        if (!empty($filters['active'])) {
            $query->where('active', (bool) $filters['active']);
        }
        if (!empty($filters['sport_id'])) {
            $query->where('sport_id', (int) $filters['sport_id']);
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
        return $this->baseScopedQuery($schoolId, $season)->findOrFail($id);
    }

    public function create(array $data, int $schoolId, int $seasonId): Course
    {
        // Ensure school scoping
        $data['school_id'] = $schoolId;
        $course = new Course($data);
        $course->save();
        return $course->fresh();
    }

    public function update(int $id, array $data, int $schoolId, int $seasonId): Course
    {
        $season = Season::findOrFail($seasonId);
        $course = $this->baseScopedQuery($schoolId, $season)->findOrFail($id);
        $course->fill($data);
        $course->save();
        return $course->fresh();
    }

    public function delete(int $id, int $schoolId, int $seasonId): bool
    {
        $season = Season::findOrFail($seasonId);
        $course = $this->baseScopedQuery($schoolId, $season)->findOrFail($id);
        return (bool) $course->delete();
    }

    private function baseScopedQuery(int $schoolId, Season $season): Builder
    {
        // Courses for school where any course_date falls inside season, or simple fallback on course date range
        $query = Course::query()
            ->where('school_id', $schoolId)
            ->where(function (Builder $q) use ($season) {
                $q->whereHas('courseDates', function (Builder $d) use ($season) {
                    $d->whereBetween('date', [$season->start_date, $season->end_date]);
                })
                ->orWhere(function (Builder $d) use ($season) {
                    // fallback overlap by course date range if present
                    $d->whereNotNull('date_start')
                      ->whereNotNull('date_end')
                      ->where(function (Builder $ov) use ($season) {
                          $ov->whereBetween('date_start', [$season->start_date, $season->end_date])
                             ->orWhereBetween('date_end', [$season->start_date, $season->end_date]);
                      });
                });
            });

        return $query;
    }
}
