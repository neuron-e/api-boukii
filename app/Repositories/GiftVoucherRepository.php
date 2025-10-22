<?php

namespace App\Repositories;

use App\Models\GiftVoucher;
use App\Repositories\BaseRepository;

class GiftVoucherRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'id',
        'voucher_id',
        'amount',
        'sender_name',
        'recipient_email',
        'recipient_name',
        'template',
        'is_delivered',
        'is_redeemed',
        'is_paid',
        'school_id',
        'purchased_by_client_id',
        'redeemed_by_client_id'
    ];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return GiftVoucher::class;
    }
}
