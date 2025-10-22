<?php

namespace App\Http\Requests\API;

use App\Models\GiftVoucher;
use InfyOm\Generator\Request\APIRequest;

class UpdateGiftVoucherAPIRequest extends APIRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = GiftVoucher::$rules;

        // Make school_id optional for updates
        if (isset($rules['school_id'])) {
            $rules['school_id'] = str_replace('required', 'sometimes', $rules['school_id']);
        }

        // Make amount optional for updates
        if (isset($rules['amount'])) {
            $rules['amount'] = str_replace('required', 'sometimes', $rules['amount']);
        }

        // Make recipient_email optional for updates
        if (isset($rules['recipient_email'])) {
            $rules['recipient_email'] = str_replace('required', 'sometimes', $rules['recipient_email']);
        }

        return $rules;
    }
}
