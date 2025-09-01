<?php

namespace App\Http\Requests\API\V5\Renting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRentingV5Request extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'equipment_type' => 'sometimes|string|in:skis,boots,poles,helmet,goggles,snowboard,bindings,clothing,protection,other',
            'name' => 'sometimes|string|max:255',
            'brand' => 'sometimes|nullable|string|max:255',
            'model' => 'sometimes|nullable|string|max:255',
            'size' => 'sometimes|nullable|string|max:50',
            'daily_rate' => 'sometimes|numeric|min:0',
            'rental_days' => 'sometimes|integer|min:1',
            'currency' => 'sometimes|string|size:3',
            'deposit' => 'sometimes|numeric|min:0',
            'condition_out' => 'sometimes|string|in:excellent,good,fair,poor,damaged',
            'condition_in' => 'sometimes|nullable|string|in:excellent,good,fair,poor,damaged',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}

