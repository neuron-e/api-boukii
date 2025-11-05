<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CourseSubgroupPlannerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'course_group_id' => $this->course_group_id,
            'course_date_id' => $this->course_date_id,
            'course_id' => $this->course_id,
            'monitor_id' => $this->monitor_id,
            'subgroup_number' => $this->subgroup_number ?? null,
            'total_subgroups' => $this->total_subgroups ?? null,

            // CourseGroup minimal
            'courseGroup' => $this->whenLoaded('courseGroup', function () {
                return [
                    'id' => $this->courseGroup->id,
                    'course_id' => $this->courseGroup->course_id,
                    'course' => $this->when($this->courseGroup->relationLoaded('course'), function () {
                        return [
                            'id' => $this->courseGroup->course->id,
                            'name' => $this->courseGroup->course->name,
                            'sport_id' => $this->courseGroup->course->sport_id,
                            'course_type' => $this->courseGroup->course->course_type,
                            'max_participants' => $this->courseGroup->course->max_participants,
                            'date_start' => $this->courseGroup->course->date_start,
                            'date_end' => $this->courseGroup->course->date_end,
                        ];
                    }),
                ];
            }),

            // Course minimal (si está cargado directamente)
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

            // BookingUsers minimal
            'bookingUsers' => $this->whenLoaded('bookingUsers', function () {
                return $this->bookingUsers->map(function ($bookingUser) {
                    return [
                        'id' => $bookingUser->id,
                        'client_id' => $bookingUser->client_id,
                        'degree_id' => $bookingUser->degree_id,
                        'status' => $bookingUser->status,
                        'booking' => $this->when($bookingUser->relationLoaded('booking'), function () use ($bookingUser) {
                            return [
                                'id' => $bookingUser->booking->id,
                                'user' => $this->when($bookingUser->booking->relationLoaded('user'), function () use ($bookingUser) {
                                    return [
                                        'id' => $bookingUser->booking->user->id,
                                        'first_name' => $bookingUser->booking->user->first_name,
                                        'last_name' => $bookingUser->booking->user->last_name,
                                    ];
                                }),
                            ];
                        }),
                        'client' => $this->when($bookingUser->relationLoaded('client'), function () use ($bookingUser) {
                            return [
                                'id' => $bookingUser->client->id,
                                'first_name' => $bookingUser->client->first_name,
                                'last_name' => $bookingUser->client->last_name,
                                'birth_date' => $bookingUser->client->birth_date,
                                'language1_id' => $bookingUser->client->language1_id,
                                'sports' => $this->when($bookingUser->client->relationLoaded('sports'), function () use ($bookingUser) {
                                    return $bookingUser->client->sports->map(function ($sport) {
                                        return [
                                            'id' => $sport->id,
                                            'name' => $sport->name,
                                        ];
                                    });
                                }),
                                'evaluations' => $this->when($bookingUser->client->relationLoaded('evaluations'), function () use ($bookingUser) {
                                    return $bookingUser->client->evaluations->map(function ($evaluation) {
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
                    ];
                });
            }),
        ];
    }
}
