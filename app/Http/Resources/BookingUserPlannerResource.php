<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingUserPlannerResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
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
        ];

        // Agregar user_id si existe (se agrega dinámicamente en el controller)
        if (isset($this->user_id)) {
            $data['user_id'] = $this->user_id;
        }

        // Booking minimal
        if ($this->relationLoaded('booking') && $this->booking) {
            $data['booking'] = [
                'id' => $this->booking->id,
                'user_id' => $this->booking->user_id,
                'paid' => $this->booking->paid ?? false,
            ];

            if ($this->booking->relationLoaded('user') && $this->booking->user) {
                $data['booking']['user'] = [
                    'id' => $this->booking->user->id,
                    'first_name' => $this->booking->user->first_name,
                    'last_name' => $this->booking->user->last_name,
                ];
            }
        }

        // Client minimal
        if ($this->relationLoaded('client') && $this->client) {
            $data['client'] = [
                'id' => $this->client->id,
                'first_name' => $this->client->first_name,
                'last_name' => $this->client->last_name,
                'birth_date' => $this->client->birth_date,
                'language1_id' => $this->client->language1_id,
            ];

            if ($this->client->relationLoaded('sports')) {
                $data['client']['sports'] = $this->client->sports->map(function ($sport) {
                    return [
                        'id' => $sport->id,
                        'name' => $sport->name,
                    ];
                })->toArray();
            }

            if ($this->client->relationLoaded('evaluations')) {
                $data['client']['evaluations'] = $this->client->evaluations->map(function ($evaluation) {
                    $evalData = [
                        'id' => $evaluation->id,
                        'degree_id' => $evaluation->degree_id,
                    ];

                    if ($evaluation->relationLoaded('degree') && $evaluation->degree) {
                        $evalData['degree'] = [
                            'id' => $evaluation->degree->id,
                            'name' => $evaluation->degree->name,
                            'annotation' => $evaluation->degree->annotation,
                            'color' => $evaluation->degree->color,
                        ];
                    }

                    if ($evaluation->relationLoaded('evaluationFulfilledGoals')) {
                        $evalData['evaluationFulfilledGoals'] = $evaluation->evaluationFulfilledGoals;
                    }

                    return $evalData;
                })->toArray();
            }
        }

        // Course minimal
        if ($this->relationLoaded('course') && $this->course) {
            $data['course'] = [
                'id' => $this->course->id,
                'name' => $this->course->name,
                'sport_id' => $this->course->sport_id,
                'course_type' => $this->course->course_type,
                'max_participants' => $this->course->max_participants,
                'date_start' => $this->course->date_start,
                'date_end' => $this->course->date_end,
            ];

            if ($this->course->relationLoaded('courseDates')) {
                $data['course']['courseDates'] = $this->course->courseDates->map(function ($date) {
                    return [
                        'id' => $date->id,
                        'date' => $date->date,
                        'hour_start' => $date->hour_start,
                        'hour_end' => $date->hour_end,
                    ];
                })->toArray();
            }
        }

        return $data;
    }
}
