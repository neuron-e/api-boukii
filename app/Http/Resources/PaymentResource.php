<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'booking_id' => $this->booking_id,
            'school_id' => $this->school_id,
            'amount' => $this->amount,
            'status' => $this->status,
            'notes' => $this->notes,
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
