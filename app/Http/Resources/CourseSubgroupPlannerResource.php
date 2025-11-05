<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CourseSubgroupPlannerResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'course_group_id' => $this->course_group_id,
            'course_date_id' => $this->course_date_id,
            'course_id' => $this->course_id,
            'monitor_id' => $this->monitor_id,
        ];

        // Agregar subgroup_number y total_subgroups si existen
        if (isset($this->subgroup_number)) {
            $data['subgroup_number'] = $this->subgroup_number;
        }
        if (isset($this->total_subgroups)) {
            $data['total_subgroups'] = $this->total_subgroups;
        }

        // CourseGroup minimal
        if ($this->relationLoaded('courseGroup') && $this->courseGroup) {
            $data['courseGroup'] = [
                'id' => $this->courseGroup->id,
                'course_id' => $this->courseGroup->course_id,
            ];

            if ($this->courseGroup->relationLoaded('course') && $this->courseGroup->course) {
                $data['courseGroup']['course'] = [
                    'id' => $this->courseGroup->course->id,
                    'name' => $this->courseGroup->course->name,
                    'sport_id' => $this->courseGroup->course->sport_id,
                    'course_type' => $this->courseGroup->course->course_type,
                    'max_participants' => $this->courseGroup->course->max_participants,
                    'date_start' => $this->courseGroup->course->date_start,
                    'date_end' => $this->courseGroup->course->date_end,
                ];
            }
        }

        // Course minimal (si está cargado directamente)
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

        // BookingUsers minimal
        if ($this->relationLoaded('bookingUsers')) {
            $data['bookingUsers'] = $this->bookingUsers->map(function ($bookingUser) {
                $buData = [
                    'id' => $bookingUser->id,
                    'client_id' => $bookingUser->client_id,
                    'degree_id' => $bookingUser->degree_id,
                    'status' => $bookingUser->status,
                ];

                if ($bookingUser->relationLoaded('booking') && $bookingUser->booking) {
                    $buData['booking'] = [
                        'id' => $bookingUser->booking->id,
                    ];

                    if ($bookingUser->booking->relationLoaded('user') && $bookingUser->booking->user) {
                        $buData['booking']['user'] = [
                            'id' => $bookingUser->booking->user->id,
                            'first_name' => $bookingUser->booking->user->first_name,
                            'last_name' => $bookingUser->booking->user->last_name,
                        ];
                    }
                }

                if ($bookingUser->relationLoaded('client') && $bookingUser->client) {
                    $buData['client'] = [
                        'id' => $bookingUser->client->id,
                        'first_name' => $bookingUser->client->first_name,
                        'last_name' => $bookingUser->client->last_name,
                        'birth_date' => $bookingUser->client->birth_date,
                        'language1_id' => $bookingUser->client->language1_id,
                    ];

                    if ($bookingUser->client->relationLoaded('sports')) {
                        $buData['client']['sports'] = $bookingUser->client->sports->map(function ($sport) {
                            return [
                                'id' => $sport->id,
                                'name' => $sport->name,
                            ];
                        })->toArray();
                    }

                    if ($bookingUser->client->relationLoaded('evaluations')) {
                        $buData['client']['evaluations'] = $bookingUser->client->evaluations->map(function ($evaluation) {
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

                return $buData;
            })->toArray();
        }

        return $data;
    }
}

