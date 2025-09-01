<?php

namespace App\Http\Requests\API\V5;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LoginV5Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'email' => [
                'required',
                'string',
                'email',
                'max:255'
            ],
            'password' => [
                'required',
                'string',
                'min:4'
            ],
            // Allow either school_id or season_id; at least one required
            'school_id' => [
                'required_without:season_id',
                'integer',
            ],
            'season_id' => [
                'required_without:school_id',
                'integer',
            ],
            'remember_me' => [
                'boolean'
            ]
        ];

        if (Schema::hasTable('schools')) {
            $rules['school_id'][] = Rule::exists('schools', 'id');
        }
        if (Schema::hasTable('seasons')) {
            $rules['season_id'][] = Rule::exists('seasons', 'id');
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'password' => 'password',
            'school_id' => 'school',
            'season_id' => 'season',
            'remember_me' => 'remember me option'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'The password is required.',
            'password.min' => 'The password must be at least 6 characters.',
            'school_id.required' => 'Please select a school.',
            'school_id.exists' => 'The selected school is invalid.',
            'season_id.required' => 'Please select a season.',
            'season_id.exists' => 'The selected season is invalid.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'The provided data is invalid.',
                'errors' => $validator->errors(),
                'error_code' => 'VALIDATION_ERROR'
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $payload = [
            'email' => strtolower(trim($this->email ?? '')),
            'remember_me' => $this->boolean('remember_me', false)
        ];

        if ($this->has('school_id')) {
            $payload['school_id'] = (int) $this->school_id;
        }
        if ($this->has('season_id')) {
            $payload['season_id'] = (int) $this->season_id;
        }

        $this->merge($payload);
    }
}
