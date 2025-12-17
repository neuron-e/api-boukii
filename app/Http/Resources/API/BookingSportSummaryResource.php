<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingSportSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon_collective' => $this->icon_collective,
            'icon_prive' => $this->icon_prive,
            'icon_activity' => $this->icon_activity,
        ];
    }
}
