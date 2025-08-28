<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;


class SchoolFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = School::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        
        return [
            'name' => $this->faker->text($this->faker->numberBetween(5, 255)),
            'description' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'contact_email' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'contact_phone' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'contact_telephone' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'contact_address' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'contact_cp' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'contact_city' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'contact_province' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'contact_country' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'fiscal_name' => $this->faker->firstName,
            'fiscal_id' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'fiscal_address' => $this->faker->address,
            'fiscal_cp' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'fiscal_city' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'fiscal_province' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'fiscal_country' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'iban' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'logo' => $this->faker->text($this->faker->numberBetween(5, 500)),
            'slug' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'cancellation_insurance_percent' => $this->faker->numberBetween(0, 9223372036854775807),
            'payrexx_instance' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'payrexx_key' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'payment_provider' => $this->faker->randomElement(['payrexx', 'payyo']),
            'payyo_instance' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'payyo_key' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'conditions_url' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'bookings_comission_cash' => $this->faker->numberBetween(0, 9223372036854775807),
            'bookings_comission_boukii_pay' => $this->faker->numberBetween(0, 9223372036854775807),
            'bookings_comission_other' => $this->faker->numberBetween(0, 9223372036854775807),
            'school_rate' => $this->faker->numberBetween(0, 9223372036854775807),
            'has_ski' => $this->faker->boolean,
            'has_snowboard' => $this->faker->boolean,
            'has_telemark' => $this->faker->boolean,
            'has_rando' => $this->faker->boolean,
            'inscription' => $this->faker->boolean,
            'type' => $this->faker->text($this->faker->numberBetween(5, 100)),
            'active' => $this->faker->boolean,
            'settings' => $this->faker->text($this->faker->numberBetween(5, 4096)),
            'created_at' => $this->faker->date('Y-m-d H:i:s'),
            'updated_at' => $this->faker->date('Y-m-d H:i:s'),
            'deleted_at' => $this->faker->date('Y-m-d H:i:s')
        ];
    }
}
