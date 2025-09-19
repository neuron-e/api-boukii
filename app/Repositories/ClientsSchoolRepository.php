<?php

namespace App\Repositories;

use App\Models\ClientsSchool;
use App\Repositories\BaseRepository;

class ClientsSchoolRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'id',
        'client_id',
        'school_id',
        'status_updated_at',
        'accepted_at',
        'accepts_newsletter',
        'is_vip',
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return ClientsSchool::class;
    }
}
