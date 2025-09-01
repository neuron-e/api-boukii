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
        // Generate realistic, schema-safe values
        return [
            'name' => $this->faker->company(),
            'description' => $this->faker->sentence(8),
            'contact_email' => $this->faker->safeEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'contact_telephone' => $this->faker->phoneNumber(),
            'contact_address' => $this->faker->streetAddress(),
            'contact_cp' => (string) $this->faker->randomNumber(5, true),
            'contact_city' => $this->faker->city(),
            'contact_province' => $this->faker->state(),
            'contact_country' => $this->faker->country(),
            'fiscal_name' => $this->faker->company(),
            'fiscal_id' => strtoupper($this->faker->bothify('??########')),
            'fiscal_address' => $this->faker->address(),
            'fiscal_cp' => (string) $this->faker->randomNumber(5, true),
            'fiscal_city' => $this->faker->city(),
            'fiscal_province' => $this->faker->state(),
            'fiscal_country' => $this->faker->country(),
            'iban' => strtoupper($this->faker->bothify('CH## #### #### #### #### #')),
            'logo' => $this->faker->imageUrl(200, 200, 'business', true),
            'slug' => $this->faker->unique()->slug(2),
            'cancellation_insurance_percent' => $this->faker->randomFloat(2, 0, 100),
            'payrexx_instance' => $this->faker->domainWord(),
            'payrexx_key' => bin2hex(random_bytes(16)),
            'conditions_url' => $this->faker->url(),
            'bookings_comission_cash' => $this->faker->randomFloat(2, 0, 100),
            'bookings_comission_boukii_pay' => $this->faker->randomFloat(2, 0, 100),
            'bookings_comission_other' => $this->faker->randomFloat(2, 0, 100),
            'school_rate' => $this->faker->randomFloat(2, 0, 10),
            'has_ski' => $this->faker->boolean(),
            'has_snowboard' => $this->faker->boolean(),
            'has_telemark' => $this->faker->boolean(),
            'has_rando' => $this->faker->boolean(),
            'inscription' => $this->faker->boolean(),
            'type' => $this->faker->randomElement(['public', 'private']),
            'active' => true,
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ];
    }
}
