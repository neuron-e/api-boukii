<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'quantity' => $this->quantity,
            'remaining_balance' => $this->remaining_balance,
            'payed' => $this->payed,
            'is_gift' => $this->is_gift,
            'client_id' => $this->client_id,
            'buyer_name' => $this->buyer_name,
            'buyer_email' => $this->buyer_email,
            'buyer_phone' => $this->buyer_phone,
            'recipient_name' => $this->recipient_name,
            'recipient_email' => $this->recipient_email,
            'recipient_phone' => $this->recipient_phone,
            'school_id' => $this->school_id,
            'payrexx_reference' => $this->payrexx_reference,
            'payrexx_transaction' => $this->payrexx_transaction,
            'origin_type' => $this->origin_type,
            'origin_booking_id' => $this->origin_booking_id,
            'origin_booking_log_id' => $this->origin_booking_log_id,
            'origin_gift_voucher_id' => $this->origin_gift_voucher_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
