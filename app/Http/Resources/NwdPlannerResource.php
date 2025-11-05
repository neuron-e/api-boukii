<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NwdPlannerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'monitor_id' => $this->monitor_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'start_time' => $this->start_time,
            'hour_start' => $this->hour_start,
            'hour_end' => $this->hour_end,
            'full_day' => $this->full_day,
            'user_nwd_subtype_id' => $this->user_nwd_subtype_id,
            'notes' => $this->notes,
        ];
    }
}
