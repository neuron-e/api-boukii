<?php

namespace App\Http\Requests\API\V5\Course;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseV5Request extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'short_description' => 'sometimes|string',
            'description' => 'sometimes|string',
            'sport_id' => 'sometimes|integer|exists:sports,id',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'max_participants' => 'sometimes|integer|min:1',
            'confirm_attendance' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
            'online' => 'sometimes|boolean',
            'is_flexible' => 'sometimes|boolean',
            'course_type' => 'sometimes|integer|in:1,2,3',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'highlighted' => 'sometimes|boolean',
            'image' => 'sometimes|nullable|string',
        ];
    }
}

