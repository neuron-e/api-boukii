<?php

namespace App\Http\Requests\V5\Context;

use App\Traits\ProblemDetails;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class SwitchSeasonRequest extends FormRequest
{
    use ProblemDetails;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'season_id' => 'required|integer',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        throw new HttpResponseException(
            $this->problem('Validation failed', Response::HTTP_UNPROCESSABLE_ENTITY, $errors)
        );
    }
}

