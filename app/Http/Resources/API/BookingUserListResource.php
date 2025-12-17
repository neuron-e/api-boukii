<?php

namespace App\Http\Resources\API;

use App\Http\Resources\API\BookingCourseSummaryResource;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingUserListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'course_id' => $this->course_id,
            'group_id' => $this->group_id,
            'course_date_id' => $this->course_date_id,
            'monitor_id' => $this->monitor_id,
            'date' => $this->date,
            'hour_start' => $this->hour_start,
            'hour_end' => $this->hour_end,
            'status' => $this->status,
            'accepted' => $this->accepted,
            'course' => new BookingCourseSummaryResource($this->whenLoaded('course')),
        ];
    }
}
