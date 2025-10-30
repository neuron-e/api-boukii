<?php

namespace App\Repositories;

use App\Models\Voucher;
use App\Repositories\BaseRepository;

class VoucherRepository extends BaseRepository
{
    protected $fieldSearchable = [
'id',
        'code',
        'quantity',
        'remaining_balance',
        'payed',
        'is_gift',
        'client_id',
        'buyer_name',
        'buyer_email',
        'recipient_name',
        'recipient_email',
        'school_id',
        'payrexx_reference',
        'payrexx_transaction'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Voucher::class;
    }
}
