<?php

namespace App\Http\Resources\API\V5;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentingEquipmentV5Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'equipment_type' => $this->equipment_type,
            'name' => $this->name,
            'brand' => $this->brand,
            'model' => $this->model,
            'size' => $this->size,
            'daily_rate' => (float) $this->daily_rate,
            'rental_days' => (int) $this->rental_days,
            'total_price' => (float) $this->total_price,
            'currency' => $this->currency,
            'deposit' => (float) ($this->deposit ?? 0),
            'condition_out' => $this->condition_out,
            'condition_in' => $this->condition_in,
            'rented_at' => optional($this->rented_at)->toISOString(),
            'returned_at' => optional($this->returned_at)->toISOString(),
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}

