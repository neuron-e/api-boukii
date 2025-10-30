<?php

namespace Database\Factories;

use App\Models\GiftVoucher;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class GiftVoucherFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = GiftVoucher::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $school = School::first();
        if (!$school) {
            $school = School::factory()->create();
        }

        $senderName = $this->faker->name();
        $recipientName = $this->faker->name();
        $locales = ['es', 'en', 'fr', 'de', 'it'];

        return [
            'code' => GiftVoucher::generateUniqueCode(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'balance' => null,
            'currency' => $this->faker->randomElement(['EUR', 'USD', 'CHF']),
            'recipient_email' => $this->faker->email(),
            'recipient_name' => $recipientName,
            'recipient_phone' => $this->faker->optional()->e164PhoneNumber(),
            'recipient_locale' => $this->faker->randomElement($locales),
            'sender_name' => $senderName,
            'buyer_name' => $senderName,
            'buyer_email' => $this->faker->safeEmail(),
            'buyer_phone' => $this->faker->optional()->e164PhoneNumber(),
            'buyer_locale' => $this->faker->randomElement($locales),
            'personal_message' => $this->faker->optional()->sentence(),
            'template' => $this->faker->randomElement([
                'default',
                'christmas',
                'birthday',
                'anniversary',
                'thank_you',
                'congratulations',
                'valentine',
                'easter',
                'summer',
                'winter'
            ]),
            'school_id' => $school->id,
            'status' => 'pending',
            'is_paid' => false,
            'is_delivered' => false,
            'is_redeemed' => false,
            'delivery_date' => null,
            'expires_at' => null,
            'created_by' => 'factory'
        ];
    }

    /**
     * Indicate that the gift voucher is active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'is_paid' => true,
            'balance' => $attributes['amount'],
            'expires_at' => now()->addYear(),
        ]);
    }

    /**
     * Indicate that the gift voucher is pending
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'is_paid' => false,
            'balance' => null,
        ]);
    }

    /**
     * Indicate that the gift voucher is used
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'used',
            'is_paid' => true,
            'balance' => 0,
            'is_redeemed' => true,
            'redeemed_at' => now(),
        ]);
    }

    /**
     * Indicate that the gift voucher is expired
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'is_paid' => true,
            'balance' => $attributes['amount'],
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the gift voucher is cancelled
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'is_paid' => false,
            'balance' => null,
        ]);
    }
}
