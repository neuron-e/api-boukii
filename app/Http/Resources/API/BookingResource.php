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
            'payyo_reference' => $this->payyo_reference,
            'payyo_transaction' => $this->payyo_transaction,
            'attendance' => $this->attendance,
            'payrexx_refund' => $this->payrexx_refund,
            'notes' => $this->notes,
            'notes_school' => $this->notes_school,
            'paxes' => $this->paxes,
            'color' => $this->color,
            'status' => $this->status,
            'has_boukii_care' => $this->has_boukii_care,
            'price_boukii_care' => $this->price_boukii_care,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
