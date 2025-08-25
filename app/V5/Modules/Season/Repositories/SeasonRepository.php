<?php

namespace App\V5\Modules\Season\Repositories;

use App\V5\Models\Season;
use App\V5\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class SeasonRepository extends BaseRepository
{
    // Cache TTL in seconds (1 hour)
    private const CACHE_TTL = 3600;

    public function __construct(?Season $model = null)
    {
        parent::__construct($model ?? new Season());
    }

    public function all(): Collection
    {
        return Cache::remember(
            'seasons:all',
            self::CACHE_TTL,
            fn () => $this->model->newQuery()->get()
        );
    }

    public function find(int $id): ?Season
    {
        return Cache::remember(
            "seasons:find:{$id}",
            self::CACHE_TTL,
            fn () => $this->model->newQuery()->find($id)
        );
    }

    public function create(array $data): Season
    {
        $season = $this->model->newQuery()->create($data);

        // Clear relevant caches
        $this->clearCacheForSchool($season->school_id);
        Cache::forget('seasons:all');

        return $season;
    }

    public function update(Season $season, array $data): Season
    {
        $oldSchoolId = $season->school_id;

        $season->fill($data);
        $season->save();

        // Clear caches for both old and new school (if school_id changed)
        $this->clearCacheForSchool($oldSchoolId);
        if (isset($data['school_id']) && $data['school_id'] !== $oldSchoolId) {
            $this->clearCacheForSchool($data['school_id']);
        }

        Cache::forget("seasons:find:{$season->id}");
        Cache::forget('seasons:all');

        return $season;
    }

    public function delete(Season $season): bool
    {
        $result = (bool) $season->delete();

        if ($result) {
            // Clear relevant caches
            $this->clearCacheForSchool($season->school_id);
            Cache::forget("seasons:find:{$season->id}");
            Cache::forget('seasons:all');
        }

        return $result;
    }

    public function unsetActiveForSchool(int $schoolId, ?int $excludeId = null): void
    {
        $query = $this->model->newQuery()
            ->where('school_id', $schoolId)
            ->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->update(['is_active' => false]);

        $this->clearCacheForSchool($schoolId);
        Cache::forget('seasons:all');
    }

    public function findBySeason(int $id): ?Season
    {
        return $this->find($id);
    }

    public function getCurrentSeason(int $schoolId): ?Season
    {
        return Cache::remember(
            "seasons:current:{$schoolId}",
            self::CACHE_TTL,
            fn () => $this->model->newQuery()
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->where('is_closed', false)
                ->orderByDesc('start_date')
                ->first()
        );
    }

    public function getActiveSeasons(int $schoolId): Collection
    {
        return Cache::remember(
            "seasons:active:{$schoolId}",
            self::CACHE_TTL,
            fn () => $this->model->newQuery()
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->where('is_closed', false)
                ->orderByDesc('start_date')
                ->get()
        );
    }

    /**
     * Get seasons by school with relationships
     */
    public function getSeasonsWithRelationships(int $schoolId): Collection
    {
        return Cache::remember(
            "seasons:with_relations:{$schoolId}",
            self::CACHE_TTL,
            fn () => $this->model->newQuery()
                ->where('school_id', $schoolId)
                ->with(['snapshots', 'settings'])
                ->orderByDesc('start_date')
                ->get()
        );
    }

    /**
     * Get seasons count by school
     */
    public function getSeasonsCount(int $schoolId): int
    {
        return Cache::remember(
            "seasons:count:{$schoolId}",
            self::CACHE_TTL,
            fn () => $this->model->newQuery()
                ->where('school_id', $schoolId)
                ->count()
        );
    }

    /**
     * Clear all season caches for a specific school
     */
    private function clearCacheForSchool(int $schoolId): void
    {
        $patterns = [
            "seasons:current:{$schoolId}",
            "seasons:active:{$schoolId}",
            "seasons:with_relations:{$schoolId}",
            "seasons:count:{$schoolId}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Clear all season caches
     */
    public function clearAllCaches(): void
    {
        // This would require a more sophisticated cache tagging system
        // For now, we'll implement basic cache clearing
        Cache::flush(); // Only use in dev/testing environments
    }

    /**
     * Warm up cache for school
     */
    public function warmUpCacheForSchool(int $schoolId): void
    {
        // Pre-load commonly accessed data
        $this->getCurrentSeason($schoolId);
        $this->getActiveSeasons($schoolId);
        $this->getSeasonsCount($schoolId);
    }
}
