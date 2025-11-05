<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MonitorPlannerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'image' => $this->image,
            'language1_id' => $this->language1_id,
            'language2_id' => $this->language2_id,
            'language3_id' => $this->language3_id,
            'hasFullDayNwd' => $this->hasFullDayNwd ?? false,
            'sports' => $this->whenLoaded('sports', function () {
                return $this->sports->map(function ($sport) {
                    return [
                        'id' => $sport->id,
                        'name' => $sport->name,
                        'icon_selected' => $sport->icon_selected,
                        'authorizedDegrees' => $sport->authorizedDegrees->map(function ($degree) {
                            return [
                                'degree_id' => $degree->degree_id,
                            ];
                        }),
                    ];
                });
            }),
            'courseSubgroups' => $this->whenLoaded('courseSubgroups', function () {
                return $this->courseSubgroups;
            }),
        ];
    }
}
