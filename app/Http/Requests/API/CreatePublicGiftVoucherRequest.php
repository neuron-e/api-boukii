<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePublicGiftVoucherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Este es un endpoint público, por lo que no requiere autenticación
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
        return [
            'amount' => [
                'required',
                'numeric',
                'min:10',
                'max:1000'
            ],
            'currency' => [
                'required',
                Rule::in(['EUR', 'USD', 'CHF'])
            ],
            'recipient_email' => [
                'required',
                'email:rfc,dns',
                'max:255'
            ],
            'recipient_name' => [
                'required',
                'string',
                'max:100'
            ],
            'sender_name' => [
                'required',
                'string',
                'max:100'
            ],
            'personal_message' => [
                'nullable',
                'string',
                'max:500'
            ],
            'school_id' => [
                'required',
                'integer',
                'exists:schools,id'
            ],
            'template' => [
                'nullable',
                'string',
                'in:default,christmas,birthday,anniversary,thank_you,congratulations,valentine,easter,summer,winter'
            ],
            'delivery_date' => [
                'nullable',
                'date',
                'after_or_equal:today'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'El monto es obligatorio',
            'amount.min' => 'El monto mínimo es 10',
            'amount.max' => 'El monto máximo es 1000',
            'currency.required' => 'La moneda es obligatoria',
            'currency.in' => 'La moneda debe ser EUR, USD o CHF',
            'recipient_email.required' => 'El email del destinatario es obligatorio',
            'recipient_email.email' => 'El email del destinatario no es válido',
            'recipient_name.required' => 'El nombre del destinatario es obligatorio',
            'sender_name.required' => 'El nombre del remitente es obligatorio',
            'personal_message.max' => 'El mensaje personal no puede exceder 500 caracteres',
            'school_id.required' => 'La escuela es obligatoria',
            'school_id.exists' => 'La escuela seleccionada no existe',
            'delivery_date.after_or_equal' => 'La fecha de entrega no puede ser en el pasado'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'amount' => 'monto',
            'currency' => 'moneda',
            'recipient_email' => 'email del destinatario',
            'recipient_name' => 'nombre del destinatario',
            'sender_name' => 'nombre del remitente',
            'personal_message' => 'mensaje personal',
            'school_id' => 'escuela',
            'template' => 'plantilla',
            'delivery_date' => 'fecha de entrega'
        ];
    }
}
