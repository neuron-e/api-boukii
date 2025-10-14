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
     * Columns that don't exist or are relations will fallback to 'id'
     */
    protected $sortableColumnsMap = [
        // Direct columns - these work fine
        'id' => 'id',
        'email' => 'email',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'created_at' => 'created_at',
        'birth_date' => 'birth_date',

        // Columns that don't exist - fallback to id
        'a' => 'id',  // type/coronita column
        'test' => 'id',  // level column

        // Relation columns - fallback to id (can't sort by these directly)
        'utilizers' => 'id',  // users count
        'client_sports' => 'id',  // sports relation
        'clients_schools' => 'id',  // status relation
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Client::class;
    }

    /**
     * Override allQuery to handle sorting on relation columns
     */
    public function allQuery(array  $searchArray = [], string $search = null, int $skip = null, int $limit = null,
                             string $order = 'desc', string $orderColumn = 'id', array $with = [],
                             $additionalConditions = null, $onlyTrashed = false): Builder
    {
        // Map the orderColumn to actual database column
        $mappedOrderColumn = $this->sortableColumnsMap[$orderColumn] ?? 'id';

        // Call parent with mapped column
        return parent::allQuery($searchArray, $search, $skip, $limit, $order, $mappedOrderColumn, $with, $additionalConditions, $onlyTrashed);
    }
}
