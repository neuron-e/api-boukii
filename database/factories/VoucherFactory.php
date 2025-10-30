<?php

namespace Database\Factories;

use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\Client;
use App\Models\School;

class VoucherFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Voucher::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {

        $school = School::first() ?? School::factory()->create();
        $client = Client::first() ?? Client::factory()->create();

        $quantity = $this->faker->randomFloat(2, 25, 500);
        $remaining = $this->faker->boolean(70) ? $this->faker->randomFloat(2, 0, $quantity) : $quantity;

        return [
            'code' => strtoupper('VOUCH-' . $this->faker->bothify('####')),
            'name' => $this->faker->optional()->catchPhrase(),
            'description' => $this->faker->optional()->sentence(),
            'quantity' => $quantity,
            'remaining_balance' => $remaining,
            'payed' => $remaining <= 0 ? true : $this->faker->boolean(30),
            'is_gift' => $this->faker->boolean(40),
            'is_transferable' => $this->faker->boolean(30),
            'client_id' => $this->faker->boolean(60) ? $client->id : null,
            'buyer_name' => $this->faker->optional()->name(),
            'buyer_email' => $this->faker->optional()->safeEmail(),
            'buyer_phone' => $this->faker->optional()->e164PhoneNumber(),
            'recipient_name' => $this->faker->optional()->name(),
            'recipient_email' => $this->faker->optional()->safeEmail(),
            'recipient_phone' => $this->faker->optional()->e164PhoneNumber(),
            'school_id' => $school->id,
            'course_type_id' => null,
            'expires_at' => $this->faker->optional()->dateTimeBetween('+1 month', '+1 year'),
            'max_uses' => $this->faker->optional()->numberBetween(1, 10),
            'uses_count' => $this->faker->optional()->numberBetween(0, 5),
            'transferred_to_client_id' => null,
            'transferred_at' => null,
            'payrexx_reference' => $this->faker->optional()->uuid(),
            'payrexx_transaction' => $this->faker->optional()->uuid(),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null
        ];
    }
}
