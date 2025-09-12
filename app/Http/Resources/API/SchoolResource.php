<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Ensure settings include booking.social keys with nulls if missing
        $settingsStr = $this->settings;
        try {
            $settings = is_string($settingsStr) ? json_decode($settingsStr, true) : $settingsStr;
            if (is_array($settings)) {
                if (!isset($settings['booking']) || !is_array($settings['booking'])) {
                    $settings['booking'] = [];
                }
                if (!isset($settings['booking']['social']) || !is_array($settings['booking']['social'])) {
                    $settings['booking']['social'] = [];
                }
                foreach (['facebook','instagram','x','youtube','tiktok','linkedin'] as $key) {
                    if (!array_key_exists($key, $settings['booking']['social'])) {
                        $settings['booking']['social'][$key] = null;
                    }
                }
                $settingsStr = json_encode($settings);
            }
        } catch (\Throwable $e) {
            // keep original settings string on error
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'contact_telephone' => $this->contact_telephone,
            'contact_address' => $this->contact_address,
            'contact_cp' => $this->contact_cp,
            'contact_city' => $this->contact_city,
            'contact_province' => $this->contact_province,
            'contact_country' => $this->contact_country,
            'fiscal_name' => $this->fiscal_name,
            'fiscal_id' => $this->fiscal_id,
            'fiscal_address' => $this->fiscal_address,
            'fiscal_cp' => $this->fiscal_cp,
            'fiscal_city' => $this->fiscal_city,
            'fiscal_province' => $this->fiscal_province,
            'fiscal_country' => $this->fiscal_country,
            'iban' => $this->iban,
            'logo' => $this->logo,
            'slug' => $this->slug,
            'cancellation_insurance_percent' => $this->cancellation_insurance_percent,
            'payrexx_instance' => $this->payrexx_instance,
            'payrexx_key' => $this->payrexx_key,
            'conditions_url' => $this->conditions_url,
            'bookings_comission_cash' => $this->bookings_comission_cash,
            'bookings_comission_boukii_pay' => $this->bookings_comission_boukii_pay,
            'bookings_comission_other' => $this->bookings_comission_other,
            'school_rate' => $this->school_rate,
            'has_ski' => $this->has_ski,
            'has_snowboard' => $this->has_snowboard,
            'has_telemark' => $this->has_telemark,
            'has_rando' => $this->has_rando,
            'inscription' => $this->inscription,
            'type' => $this->type,
            'active' => $this->active,
            'settings' => $settingsStr,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
}
