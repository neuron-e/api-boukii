<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CourseTranslateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization is handled in the controller (TranslationAPIController)
     * which checks if the course belongs to the user's school.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'short_description' => 'required|string',
            'description' => 'required|string',
            'languages' => 'array',
            'languages.*' => 'string|in:fr,en,de,es,it',
        ];
    }
}
