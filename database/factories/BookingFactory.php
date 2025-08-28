<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\School;
use App\Models\Client;

class BookingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Booking::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        
        $client = Client::first();
        if (!$client) {
            $client = Client::factory()->create();
        }

        return [
            'school_id' => $this->faker->word,
            'client_main_id' => $this->faker->word,
            'price_total' => $this->faker->numberBetween(0, 9223372036854775807),
            'has_cancellation_insurance' => $this->faker->boolean,
            'price_cancellation_insurance' => $this->faker->numberBetween(0, 9223372036854775807),
            'currency' => $this->faker->lexify('?????'),
            'payment_method_id' => $this->faker->word,
            'paid_total' => $this->faker->numberBetween(0, 9223372036854775807),
            'paid' => $this->faker->boolean,
            'payrexx_reference' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'payrexx_transaction' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'payyo_reference' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'payyo_transaction' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'attendance' => $this->faker->boolean,
            'payrexx_refund' => $this->faker->boolean,
            'notes' => $this->faker->text($this->faker->numberBetween(5, 500)),
            'paxes' => $this->faker->word,
            'color' => $this->faker->text($this->faker->numberBetween(5, 45)),
            'created_at' => $this->faker->date('Y-m-d H:i:s'),
            'updated_at' => $this->faker->date('Y-m-d H:i:s'),
            'deleted_at' => $this->faker->date('Y-m-d H:i:s')
        ];
    }
}
