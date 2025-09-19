<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientsSchoolResource extends JsonResource
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
            'client_id' => $this->client_id,
            'school_id' => $this->school_id,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
            'status_updated_at' => $this->status_updated_at,
            'accepted_at' => $this->accepted_at,
            'accepts_newsletter' => (bool) $this->accepts_newsletter,
            'is_vip' => (bool) $this->is_vip,
        ];
    }
}
