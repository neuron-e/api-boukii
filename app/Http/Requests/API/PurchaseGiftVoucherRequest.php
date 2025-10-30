<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseGiftVoucherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:10|max:10000',
            'currency' => 'required|string|size:3',
            'school_id' => 'required|exists:schools,id',
            'buyer_name' => 'required|string|max:255',
            'buyer_email' => 'required|email|max:255',
            'buyer_phone' => 'nullable|string|max:50',
            'buyer_locale' => 'nullable|string|max:10',
            'recipient_name' => 'required|string|max:255',
            'recipient_email' => 'required|email|max:255|different:buyer_email',
            'recipient_phone' => 'nullable|string|max:50',
            'recipient_locale' => 'nullable|string|max:10',
            'personal_message' => 'nullable|string|max:500',
            'template' => 'nullable|string|max:50',
            'delivery_date' => 'nullable|date|after_or_equal:today',
            'accept_terms' => 'nullable|boolean'
        ];
    }
}

