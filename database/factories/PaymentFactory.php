<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\School;
use App\Models\Booking;

class PaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        
        $booking = Booking::first();
        if (!$booking) {
            $booking = Booking::factory()->create();
        }

        return [
            'booking_id' => $this->faker->word,
            'school_id' => $this->faker->word,
            'amount' => $this->faker->numberBetween(0, 9223372036854775807),
            'status' => $this->faker->text($this->faker->numberBetween(5, 255)),
            'notes' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'payrexx_reference' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'payrexx_transaction' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'payyo_reference' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'payyo_transaction' => $this->faker->text($this->faker->numberBetween(5, 65535)),
            'created_at' => $this->faker->date('Y-m-d H:i:s'),
            'updated_at' => $this->faker->date('Y-m-d H:i:s'),
            'deleted_at' => $this->faker->date('Y-m-d H:i:s')
        ];
    }
}
