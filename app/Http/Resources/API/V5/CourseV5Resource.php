<?php

namespace App\Http\Resources\API\V5;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseV5Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'sport_id' => $this->sport_id,
            'school_id' => $this->school_id,
            'price' => $this->price,
            'currency' => $this->currency,
            'max_participants' => $this->max_participants,
            'duration' => $this->duration,
            'date_start' => optional($this->date_start)->format('Y-m-d'),
            'date_end' => optional($this->date_end)->format('Y-m-d'),
            'confirm_attendance' => (bool) $this->confirm_attendance,
            'active' => (bool) $this->active,
            'online' => (bool) $this->online,
            'is_flexible' => (bool) $this->is_flexible,
            'course_type' => (int) $this->course_type,
            'highlighted' => (bool) $this->highlighted,
            'image' => $this->image,
            'start_date' => $this->start_date, // accessor from model when available
            'end_date' => $this->end_date,     // accessor from model when available
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}

