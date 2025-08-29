<?php

namespace App\Http\Requests\API\V5;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SelectSeasonV5Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->guard('sanctum')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = auth()->guard('sanctum')->user();
        $token = $user?->currentAccessToken();
        $contextData = $token ? json_decode($token->context_data, true) : [];
        $schoolId = $contextData['school_id'] ?? null;

        // Debug: Log the school_id being validated
        \Log::info('SelectSeasonV5Request validation', [
            'user_id' => $user?->id,
            'token_school_id' => $schoolId,
            'requested_season_id' => $this->input('season_id')
        ]);

        return [
            // school_id pasa a opcional: se obtiene del token cuando es posible
            'school_id' => [
                'sometimes',
                'integer',
                'exists:schools,id'
            ],
            'season_id' => [
                'required',
                'integer',
                'exists:seasons,id' // Simplified validation - we'll do business logic checks in controller
            ],
            'create_new_season' => [
                'sometimes',
                'boolean'
            ],
            'new_season_data' => [
                'required_if:create_new_season,true',
                'array'
            ],
            'new_season_data.name' => [
                'required_if:create_new_season,true',
                'string',
                'max:255'
            ],
            'new_season_data.start_date' => [
                'required_if:create_new_season,true',
                'date',
                'after_or_equal:today'
            ],
            'new_season_data.end_date' => [
                'required_if:create_new_season,true',
                'date',
                'after:new_season_data.start_date'
            ],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'season_id' => 'season',
            'create_new_season' => 'create new season option',
            'new_season_data.name' => 'season name',
            'new_season_data.start_date' => 'start date',
            'new_season_data.end_date' => 'end date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'season_id.required' => 'Please select a season.',
            'season_id.exists' => 'The selected season is invalid or not active.',
            'school_id.exists' => 'The selected school is invalid.',
            'new_season_data.name.required_if' => 'Season name is required when creating a new season.',
            'new_season_data.start_date.required_if' => 'Start date is required when creating a new season.',
            'new_season_data.end_date.required_if' => 'End date is required when creating a new season.',
            'new_season_data.end_date.after' => 'End date must be after start date.',
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
        $this->merge([
            'season_id' => (int) $this->season_id,
            'create_new_season' => $this->boolean('create_new_season', false)
        ]);
    }
}
