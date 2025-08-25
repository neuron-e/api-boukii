<?php

namespace App\V5\Modules\Season\Services;

use App\V5\Models\Season;
use App\V5\Modules\Season\Repositories\SeasonRepository;
use App\V5\Services\BaseService;
use Illuminate\Support\Collection;

class SeasonService extends BaseService
{
    public function __construct(SeasonRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * @return Collection<int,Season>
     */
    public function all(): Collection
    {
        /** @var SeasonRepository $repo */
        $repo = $this->repository;

        return $repo->all();
    }

    public function find(int $id): ?Season
    {
        /** @var SeasonRepository $repo */
        $repo = $this->repository;

        return $repo->find($id);
    }

    public function createSeason(array $data): Season
    {
        /** @var SeasonRepository $repo */
        $repo = $this->repository;

        if ($data['is_active'] ?? false) {
            $repo->unsetActiveForSchool($data['school_id']);
        }

        return $repo->create($data);
    }

    public function updateSeason(int $id, array $data): ?Season
    {
        /** @var SeasonRepository $repo */
        $repo = $this->repository;
        $season = $repo->find($id);
        if (! $season) {
            return null;
        }

        return $repo->update($season, $data);
    }

    public function deleteSeason(int $id): bool
    {
        /** @var SeasonRepository $repo */
        $repo = $this->repository;
        $season = $repo->find($id);

        return $season ? $repo->delete($season) : false;
    }

    public function cloneSeason(int $id): ?Season
    {
        /** @var SeasonRepository $repo */
        $repo = $this->repository;
        $season = $repo->find($id);
        if (! $season) {
            return null;
        }
        $data = $season->replicate()->toArray();
        unset($data['id']);
        $data['is_active'] = false;

        return $repo->create($data);
    }

    public function closeSeason(int $id): ?Season
    {
        /** @var SeasonRepository $repo */
        $repo = $this->repository;
        $season = $repo->find($id);
        if (! $season) {
            return null;
        }
        $repo->update($season, [
            'is_active' => false,
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        return $season->fresh();
    }

    public function activateSeason(int $id): ?Season
    {
        /** @var SeasonRepository $repo */
        $repo = $this->repository;
        $season = $repo->find($id);
        if (! $season) {
            return null;
        }
        $repo->unsetActiveForSchool($season->school_id, $season->id);
        $repo->update($season, ['is_active' => true]);

        return $season->fresh();
    }

    public function getCurrentSeason(int $schoolId): ?Season
    {
        /** @var SeasonRepository $repo */
        $repo = $this->repository;
        $season = $repo->getCurrentSeason($schoolId);

        // If no season exists, create a dummy season
        if (! $season) {
            $season = $this->createDummySeasonIfNeeded($schoolId);
        }

        return $season;
    }

    /**
     * Create a dummy season if none exists for the school
     */
    private function createDummySeasonIfNeeded(int $schoolId): ?Season
    {
        try {
            $currentYear = date('Y');
            $nextYear = $currentYear + 1;

            // Create a default season for this school
            return $this->createSeason([
                'name' => "Temporada {$currentYear}-{$nextYear}",
                'school_id' => $schoolId,
                'start_date' => "{$currentYear}-12-01",
                'end_date' => "{$nextYear}-04-30",
                'is_active' => true,
                'settings' => [
                    'default_booking_duration' => 180, // 3 hours
                    'max_advance_booking_days' => 30,
                    'cancellation_policy' => 'flexible',
                    'created_by_system' => true,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create dummy season', [
                'school_id' => $schoolId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return Collection<int,Season>
     */
    public function getActiveSeasons(int $schoolId): Collection
    {
        /** @var SeasonRepository $repo */
        $repo = $this->repository;

        return $repo->getActiveSeasons($schoolId);
    }
}
