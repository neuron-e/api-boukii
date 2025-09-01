<?php

namespace App\Http\Resources\API\V5;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentingItemV5Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'base_daily_rate' => (float) $this->base_daily_rate,
            'deposit' => (float) ($this->deposit ?? 0),
            'currency' => $this->currency,
            'inventory_count' => (int) $this->inventory_count,
            'attributes' => $this->attributes,
            'active' => (bool) $this->active,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}

