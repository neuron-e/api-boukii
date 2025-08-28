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

        $school = School::first();
        if (!$school) {
            $school = School::factory()->create();
        }

        return [
            'code' => $this->faker->text($this->faker->numberBetween(5, 255)),
            'quantity' => $this->faker->numberBetween(0, 9223372036854775807),
            'remaining_balance' => $this->faker->numberBetween(0, 9223372036854775807),
            'payed' => $this->faker->boolean,
            'is_gift' => $this->faker->boolean,
            'client_id' => $this->faker->word,
            'school_id' => $this->faker->word,
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
