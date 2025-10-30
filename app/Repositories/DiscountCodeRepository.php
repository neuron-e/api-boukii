<?php

namespace App\Repositories;

use App\Models\DiscountCode;
use App\Repositories\BaseRepository;

class DiscountCodeRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'code',
        'name',
        'description',
        'discount_type',
        'school_id'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return DiscountCode::class;
    }
}
