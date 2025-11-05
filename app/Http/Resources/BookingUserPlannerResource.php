<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingUserPlannerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'client_id' => $this->client_id,
            'course_id' => $this->course_id,
            'course_date_id' => $this->course_date_id,
            'course_subgroup_id' => $this->course_subgroup_id,
            'monitor_id' => $this->monitor_id,
            'group_id' => $this->group_id,
            'date' => $this->date,
            'hour_start' => $this->hour_start,
            'hour_end' => $this->hour_end,
            'status' => $this->status,
            'accepted' => $this->accepted,
            'degree_id' => $this->degree_id,
            'color' => $this->color,
            'user_id' => $this->user_id ?? null,

            // Booking minimal
            'booking' => $this->whenLoaded('booking', function () {
                return [
                    'id' => $this->booking->id,
                    'user_id' => $this->booking->user_id,
                    'paid' => $this->booking->paid,
                    'user' => $this->when($this->booking->relationLoaded('user'), function () {
                        return [
                            'id' => $this->booking->user->id,
                            'first_name' => $this->booking->user->first_name,
                            'last_name' => $this->booking->user->last_name,
                        ];
                    }),
                ];
            }),

            // Client minimal
            'client' => $this->whenLoaded('client', function () {
                return [
                    'id' => $this->client->id,
                    'first_name' => $this->client->first_name,
                    'last_name' => $this->client->last_name,
                    'birth_date' => $this->client->birth_date,
                    'language1_id' => $this->client->language1_id,
                    'sports' => $this->when($this->client->relationLoaded('sports'), function () {
                        return $this->client->sports->map(function ($sport) {
                            return [
                                'id' => $sport->id,
                                'name' => $sport->name,
                            ];
                        });
                    }),
                    'evaluations' => $this->when($this->client->relationLoaded('evaluations'), function () {
                        return $this->client->evaluations->map(function ($evaluation) {
                            return [
                                'id' => $evaluation->id,
                                'degree_id' => $evaluation->degree_id,
                                'degree' => $this->when($evaluation->relationLoaded('degree'), function () use ($evaluation) {
                                    return [
                                        'id' => $evaluation->degree->id,
                                        'name' => $evaluation->degree->name,
                                        'annotation' => $evaluation->degree->annotation,
                                        'color' => $evaluation->degree->color,
                                    ];
                                }),
                                'evaluationFulfilledGoals' => $this->when($evaluation->relationLoaded('evaluationFulfilledGoals'), $evaluation->evaluationFulfilledGoals),
                            ];
                        });
                    }),
                ];
            }),

            // Course minimal
            'course' => $this->whenLoaded('course', function () {
                return [
                    'id' => $this->course->id,
                    'name' => $this->course->name,
                    'sport_id' => $this->course->sport_id,
                    'course_type' => $this->course->course_type,
                    'max_participants' => $this->course->max_participants,
                    'date_start' => $this->course->date_start,
                    'date_end' => $this->course->date_end,
                    'courseDates' => $this->when($this->course->relationLoaded('courseDates'), function () {
                        return $this->course->courseDates->map(function ($date) {
                            return [
                                'id' => $date->id,
                                'date' => $date->date,
                                'hour_start' => $date->hour_start,
                                'hour_end' => $date->hour_end,
                            ];
                        });
                    }),
                ];
            }),
        ];
    }
}
