<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MonitorPlannerResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'image' => $this->image,
            'language1_id' => $this->language1_id,
            'language2_id' => $this->language2_id,
            'language3_id' => $this->language3_id,
        ];

        // Agregar hasFullDayNwd si existe (se agrega dinámicamente)
        if (isset($this->hasFullDayNwd)) {
            $data['hasFullDayNwd'] = $this->hasFullDayNwd;
        }

        // Sports minimal
        if ($this->relationLoaded('sports')) {
            $data['sports'] = $this->sports->map(function ($sport) {
                $sportData = [
                    'id' => $sport->id,
                    'name' => $sport->name,
                    'icon_selected' => $sport->icon_selected,
                ];

                // AuthorizedDegrees (se agregan dinámicamente en el controller)
                if (isset($sport->authorizedDegrees)) {
                    $sportData['authorizedDegrees'] = $sport->authorizedDegrees->map(function ($degree) {
                        return [
                            'degree_id' => $degree->degree_id,
                        ];
                    })->toArray();
                }

                return $sportData;
            })->toArray();
        }

        // CourseSubgroups (si está cargado)
        if ($this->relationLoaded('courseSubgroups')) {
            $data['courseSubgroups'] = $this->courseSubgroups->toArray();
        }

        return $data;
    }
}

