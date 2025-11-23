<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class CourseSubgroupResource extends JsonResource
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
            'course_id' => $this->course_id,
            'course_date_id' => $this->course_date_id,
            'degree_id' => $this->degree_id,
            'course_group_id' => $this->course_group_id,
            'monitor_id' => $this->monitor_id,
            'max_participants' => $this->max_participants,
            'subgroup_dates_id' => $this->subgroup_dates_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
