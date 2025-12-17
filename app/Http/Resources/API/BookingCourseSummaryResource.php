<?php

namespace App\Http\Resources\API;

use App\Http\Resources\API\BookingSportSummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingCourseSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'translations' => $this->translations,
            'course_type' => $this->course_type,
            'is_flexible' => $this->is_flexible,
            'sport_id' => $this->sport_id,
            'sport' => new BookingSportSummaryResource($this->whenLoaded('sport')),
        ];
    }
}
