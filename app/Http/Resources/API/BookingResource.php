<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'school_id' => $this->school_id,
            'client_main_id' => $this->client_main_id,
            'price_total' => $this->price_total,
            'has_cancellation_insurance' => $this->has_cancellation_insurance,
            'price_cancellation_insurance' => $this->price_cancellation_insurance,
            'currency' => $this->currency,
            'payment_method_id' => $this->payment_method_id,
            'paid_total' => $this->paid_total,
            'paid' => $this->paid,
            'payrexx_reference' => $this->payrexx_reference,
            'payrexx_transaction' => $this->payrexx_transaction,
            'attendance' => $this->attendance,
            'payrexx_refund' => $this->payrexx_refund,
            'notes' => $this->notes,
            'paxes' => $this->paxes,
            'color' => $this->color,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
