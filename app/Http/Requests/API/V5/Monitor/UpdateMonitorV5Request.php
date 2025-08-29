<?php

namespace App\Http\Requests\API\V5\Monitor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMonitorV5Request extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:100',
            'phone' => 'sometimes|nullable|string|max:255',
            'active' => 'sometimes|boolean',
        ];
    }
}

