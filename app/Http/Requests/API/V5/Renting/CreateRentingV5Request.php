<?php

namespace App\Http\Requests\API\V5\Renting;

use Illuminate\Foundation\Http\FormRequest;

class CreateRentingV5Request extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'booking_id' => 'required|integer|exists:v5_bookings,id',
            'equipment_type' => 'required|string|in:skis,boots,poles,helmet,goggles,snowboard,bindings,clothing,protection,other',
            'name' => 'required|string|max:255',
            'brand' => 'sometimes|nullable|string|max:255',
            'model' => 'sometimes|nullable|string|max:255',
            'size' => 'sometimes|nullable|string|max:50',
            'daily_rate' => 'required|numeric|min:0',
            'rental_days' => 'required|integer|min:1',
            'currency' => 'required|string|size:3',
            'deposit' => 'sometimes|numeric|min:0',
            'condition_out' => 'required|string|in:excellent,good,fair,poor,damaged',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}

