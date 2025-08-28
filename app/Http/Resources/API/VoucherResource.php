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
            'school_id' => $this->school_id,
            'payrexx_reference' => $this->payrexx_reference,
            'payrexx_transaction' => $this->payrexx_transaction,
            'payyo_reference' => $this->payyo_reference,
            'payyo_transaction' => $this->payyo_transaction,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
