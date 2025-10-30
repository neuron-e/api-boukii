<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class GiftVoucherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'amount' => $this->amount,
            'balance' => $this->balance,
            'currency' => $this->currency,
            'personal_message' => $this->personal_message,
            'sender_name' => $this->sender_name,
            'buyer_name' => $this->buyer_name,
            'buyer_email' => $this->buyer_email,
            'buyer_phone' => $this->buyer_phone,
            'buyer_locale' => $this->buyer_locale,
            'recipient_email' => $this->recipient_email,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'recipient_locale' => $this->recipient_locale,
            'template' => $this->template,
            'delivery_date' => $this->delivery_date,
            'expires_at' => $this->expires_at,
            'status' => $this->status,
            'is_paid' => $this->is_paid,
            'is_delivered' => $this->is_delivered,
            'is_redeemed' => $this->is_redeemed,
            'voucher_id' => $this->voucher_id,
            'school_id' => $this->school_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

