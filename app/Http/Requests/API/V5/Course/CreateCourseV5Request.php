<?php

namespace App\Http\Requests\API\V5\Course;

use Illuminate\Foundation\Http\FormRequest;

class CreateCourseV5Request extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'short_description' => 'required|string',
            'description' => 'required|string',
            'sport_id' => 'required|integer|exists:sports,id',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'max_participants' => 'required|integer|min:1',
            'confirm_attendance' => 'required|boolean',
            'active' => 'required|boolean',
            'online' => 'required|boolean',
            'is_flexible' => 'required|boolean',
            'course_type' => 'required|integer|in:1,2,3',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            // Optional extras
            'highlighted' => 'sometimes|boolean',
            'image' => 'sometimes|nullable|string',
        ];
    }
}

