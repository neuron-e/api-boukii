<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Repositories\BaseRepository;

class PaymentRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'booking_id',
        'school_id',
        'amount',
        'status',
        'notes',
        'payrexx_reference',
        'payrexx_transaction',
        'payyo_reference',
        'payyo_transaction'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Payment::class;
    }
}
