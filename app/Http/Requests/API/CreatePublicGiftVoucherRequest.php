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
            'amount' => ['required', 'numeric', 'min:10', 'max:1000'],
            'currency' => ['required', Rule::in(['EUR', 'USD', 'CHF'])],
            'school_id' => ['required', 'integer', 'exists:schools,id'],

            'buyer_name' => ['required', 'string', 'max:150'],
            'buyer_email' => ['required', 'email:rfc,dns', 'max:255'],
            'buyer_phone' => ['nullable', 'string', 'max:50'],
            'buyer_locale' => ['nullable', 'string', 'max:10'],

            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_email' => ['required', 'email:rfc,dns', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'recipient_locale' => ['nullable', 'string', 'max:10'],

            'sender_name' => ['nullable', 'string', 'max:150'],
            'personal_message' => ['nullable', 'string', 'max:500'],
            'template' => ['nullable', 'string', 'in:default,christmas,birthday,anniversary,thank_you,congratulations,valentine,easter,summer,winter'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
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

            'buyer_name.required' => 'El nombre del comprador es obligatorio',
            'buyer_email.required' => 'El email del comprador es obligatorio',
            'buyer_email.email' => 'El email del comprador no es válido',

            'recipient_email.required' => 'El email del destinatario es obligatorio',
            'recipient_email.email' => 'El email del destinatario no es válido',
            'recipient_name.required' => 'El nombre del destinatario es obligatorio',

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
            'school_id' => 'escuela',
            'buyer_name' => 'nombre del comprador',
            'buyer_email' => 'email del comprador',
            'buyer_phone' => 'teléfono del comprador',
            'buyer_locale' => 'idioma del comprador',
            'recipient_email' => 'email del destinatario',
            'recipient_name' => 'nombre del destinatario',
            'recipient_phone' => 'teléfono del destinatario',
            'recipient_locale' => 'idioma del destinatario',
            'sender_name' => 'nombre del remitente',
            'personal_message' => 'mensaje personal',
            'template' => 'plantilla',
            'delivery_date' => 'fecha de entrega'
        ];
    }
}
