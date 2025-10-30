<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DiscountCodeResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'quantity' => $this->quantity,
            'percentage' => $this->percentage,
            'school_id' => $this->school_id,
            'total' => $this->total,
            'remaining' => $this->remaining,
            'max_uses_per_user' => $this->max_uses_per_user,
            'valid_from' => $this->valid_from,
            'valid_to' => $this->valid_to,
            'sport_ids' => $this->sport_ids,
            'course_ids' => $this->course_ids,
            'client_ids' => $this->client_ids,
            'degree_ids' => $this->degree_ids,
            'min_purchase_amount' => $this->min_purchase_amount,
            'max_discount_amount' => $this->max_discount_amount,
            'applicable_to' => $this->applicable_to,
            'active' => $this->active,
            'created_by' => $this->created_by,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
