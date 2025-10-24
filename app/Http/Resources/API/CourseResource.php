<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'course_type' => $this->course_type,
            'is_flexible' => $this->is_flexible,
            'sport_id' => $this->sport_id,
            'school_id' => $this->school_id,
            'station_id' => $this->station_id,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'max_participants' => $this->max_participants,
            'duration' => $this->duration,
            'duration_flexible' => $this->duration_flexible,
            'date_start' => $this->date_start,
            'date_end' => $this->date_end,
            'date_start_res' => $this->date_start_res,
            'date_end_res' => $this->date_end_res,
            'hour_min' => $this->hour_min,
            'hour_max' => $this->hour_max,
            'confirm_attendance' => $this->confirm_attendance,
            'active' => $this->active,
            'online' => $this->online,
            'image' => $this->image,
            'translations' => $this->translations,
            'price_range' => $this->price_range,
            'discounts' => $this->discounts,
            'settings' => $this->settings,
            'course_intervals' => $this->whenLoaded('courseIntervals', function () {
                return $this->courseIntervals->map(function ($interval) {
                    return [
                        'id' => $interval->id,
                        'course_id' => $interval->course_id,
                        'name' => $interval->name,
                        'start_date' => optional($interval->start_date)->format('Y-m-d'),
                        'end_date' => optional($interval->end_date)->format('Y-m-d'),
                        'display_order' => $interval->display_order,
                        'config_mode' => $interval->config_mode,
                        'date_generation_method' => $interval->date_generation_method,
                        'consecutive_days_count' => $interval->consecutive_days_count,
                        'weekly_pattern' => $interval->weekly_pattern,
                        'booking_mode' => $interval->booking_mode,
                        'discounts' => $interval->relationLoaded('discounts')
                            ? $interval->discounts->map(function ($discount) {
                                return [
                                    'id' => $discount->id,
                                    'days' => (int) ($discount->min_days ?? 0),
                                    'type' => $discount->discount_type === 'fixed_amount' ? 'fixed' : 'percentage',
                                    'value' => (float) $discount->discount_value,
                                    'priority' => $discount->priority,
                                    'active' => (bool) $discount->active,
                                ];
                            })
                            : [],
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
