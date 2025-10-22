<?php

namespace App\Repositories;

use App\Models\Client;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

class ClientRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'id',
        'email',
        'first_name',
        'last_name',
        'birth_date',
        'phone',
        'telephone',
        'address',
        'cp',
        'city',
        'province',
        'country',
        'user_id'
    ];

    /**
     * Map frontend column names to actual database columns for sorting
     * Columns that require derived ordering are handled separately
     */
    protected array $sortableColumnsMap = [
        'id' => 'clients.id',
        'email' => 'clients.email',
        'first_name' => 'clients.first_name',
        'last_name' => 'clients.last_name',
        'created_at' => 'clients.created_at',
        'birth_date' => 'clients.birth_date',
    ];

    protected ?int $contextSchoolId = null;

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Client::class;
    }

    public function setContextSchoolId(?int $schoolId): void
    {
        $this->contextSchoolId = $schoolId;
    }

    /**
     * Override allQuery to handle sorting on relation columns and computed values
     */
    public function allQuery(array $searchArray = [], string $search = null, int $skip = null, int $limit = null,
                             string $order = 'desc', string $orderColumn = 'id', array $with = [],
                             $additionalConditions = null, $onlyTrashed = false): Builder
    {
        $orderDirection = strtolower($order) === 'asc' ? 'asc' : 'desc';
        $requestedColumn = $orderColumn ?: 'id';

        $query = parent::allQuery(
            $searchArray,
            $search,
            $skip,
            $limit,
            $orderDirection,
            'clients.id',
            $with,
            $additionalConditions,
            $onlyTrashed
        );

        $needsTieBreaker = true;

        switch ($requestedColumn) {
            case 'utilizers_count':
            case 'client_type':
                $query->withCount('utilizers');
                $query->reorder();

                if ($requestedColumn === 'client_type') {
                    $query->orderByRaw('(CASE WHEN utilizers_count > 0 THEN 1 ELSE 0 END) ' . $orderDirection);
                } else {
                    $query->orderBy('utilizers_count', $orderDirection);
                }

                $query->orderBy('clients.id', 'asc');
                $needsTieBreaker = false;
                break;
            case 'primary_degree_order':
                $this->applyPrimaryDegreeOrdering($query, $orderDirection);
                $needsTieBreaker = false;
                break;
            case 'primary_sport_name':
                $this->applyPrimarySportOrdering($query, $orderDirection);
                $needsTieBreaker = false;
                break;
            case 'status_order':
                $this->applyStatusOrdering($query, $orderDirection);
                $needsTieBreaker = false;
                break;
            default:
                $mappedOrderColumn = $this->sortableColumnsMap[$requestedColumn] ?? 'clients.id';
                $query->reorder()->orderBy($mappedOrderColumn, $orderDirection);
                $needsTieBreaker = $mappedOrderColumn !== 'clients.id';
                break;
        }

        if ($needsTieBreaker) {
            $query->orderBy('clients.id', 'asc');
        }

        return $query;
    }

    protected function applyPrimaryDegreeOrdering(Builder $query, string $direction): void
    {
        $schoolId = $this->contextSchoolId;

        $query->selectSub(function ($subQuery) use ($schoolId) {
            $subQuery->from('clients_sports as cs')
                ->leftJoin('degrees as d', 'd.id', '=', 'cs.degree_id')
                ->whereColumn('cs.client_id', 'clients.id')
                ->whereNull('cs.deleted_at')
                ->whereNull('d.deleted_at');

            if ($schoolId) {
                $subQuery->where('cs.school_id', $schoolId);
            }

            $subQuery->selectRaw('MAX(d.degree_order)');
        }, 'primary_degree_order');

        $query->reorder()
            ->orderByRaw('(CASE WHEN primary_degree_order IS NULL THEN 1 ELSE 0 END) ASC');

        if ($direction === 'asc') {
            $query->orderBy('primary_degree_order', 'desc');
        } else {
            $query->orderBy('primary_degree_order', 'asc');
        }

        $query->orderBy('clients.id', 'asc');
    }

    protected function applyPrimarySportOrdering(Builder $query, string $direction): void
    {
        $schoolId = $this->contextSchoolId;

        $query->withCount(['clientSports as sports_count' => function ($subQuery) use ($schoolId) {
            if ($schoolId) {
                $subQuery->where('school_id', $schoolId);
            }
        }]);

        $query->selectSub(function ($subQuery) use ($schoolId) {
            $subQuery->from('clients_sports as cs')
                ->leftJoin('sports as s', 's.id', '=', 'cs.sport_id')
                ->whereColumn('cs.client_id', 'clients.id')
                ->whereNull('cs.deleted_at')
                ->whereNull('s.deleted_at');

            if ($schoolId) {
                $subQuery->where('cs.school_id', $schoolId);
            }

            $subQuery->selectRaw('MIN(s.name)');
        }, 'primary_sport_name');

        $query->reorder();

        if ($direction === 'asc') {
            $query->orderByRaw('(CASE WHEN sports_count = 0 THEN 1 ELSE 0 END) ASC')
                ->orderBy('sports_count', 'desc')
                ->orderByRaw("(CASE WHEN primary_sport_name IS NULL OR primary_sport_name = '' THEN 1 ELSE 0 END) ASC")
                ->orderBy('primary_sport_name', 'asc');
        } else {
            $query->orderByRaw('(CASE WHEN sports_count = 0 THEN 1 ELSE 0 END) ASC')
                ->orderBy('sports_count', 'asc')
                ->orderByRaw("(CASE WHEN primary_sport_name IS NULL OR primary_sport_name = '' THEN 1 ELSE 0 END) ASC")
                ->orderBy('primary_sport_name', 'desc');
        }

        $query->orderBy('clients.id', 'asc');
    }

    protected function applyStatusOrdering(Builder $query, string $direction): void
    {
        $schoolId = $this->contextSchoolId;

        $query->selectSub(function ($subQuery) use ($schoolId) {
            $subQuery->from('clients_schools as cs')
                ->whereColumn('cs.client_id', 'clients.id')
                ->whereNull('cs.deleted_at');

            if ($schoolId) {
                $subQuery->where('cs.school_id', $schoolId);
            }

            $subQuery->selectRaw('MAX(CASE WHEN cs.accepted_at IS NOT NULL THEN 2 ELSE 1 END)');
        }, 'status_order');

        $query->reorder()
            ->orderByRaw('(CASE WHEN status_order IS NULL THEN 1 ELSE 0 END) ASC');

        if ($direction === 'asc') {
            $query->orderBy('status_order', 'desc');
        } else {
            $query->orderBy('status_order', 'asc');
        }

        $query->orderBy('clients.id', 'asc');
    }


}
