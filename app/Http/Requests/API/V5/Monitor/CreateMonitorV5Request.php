<?php

namespace App\Http\Requests\API\V5\Monitor;

use Illuminate\Foundation\Http\FormRequest;

class CreateMonitorV5Request extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:255',
            'active' => 'sometimes|boolean',
        ];
    }
}

