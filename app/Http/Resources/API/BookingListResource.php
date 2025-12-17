<?php

namespace App\Http\Resources\API;

use App\Http\Resources\API\BookingUserListResource;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'price_total' => $this->price_total,
            'paid' => $this->paid,
            'status' => $this->status,
            'payment_method_id' => $this->payment_method_id,
            'has_cancellation_insurance' => $this->has_cancellation_insurance,
            'has_boukii_care' => $this->has_boukii_care,
            'has_observations' => $this->has_observations,
            'paxes' => $this->paxes,
            'color' => $this->color,
            'sport' => $this->sport,
            'client_main' => $this->clientMain ? [
                'id' => $this->clientMain->id,
                'first_name' => $this->clientMain->first_name,
                'last_name' => $this->clientMain->last_name,
                'email' => $this->clientMain->email,
                'image' => $this->clientMain->image,
                'language1_id' => $this->clientMain->language1_id,
                'country' => $this->clientMain->country,
                'birth_date' => $this->clientMain->birth_date,
            ] : null,
            'booking_users' => BookingUserListResource::collection($this->whenLoaded('bookingUsers')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
