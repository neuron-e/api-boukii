<?php

namespace Database\Factories;

use App\Models\DiscountCode;
use Illuminate\Database\Eloquent\Factories\Factory;

use App\Models\School;

class DiscountCodeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DiscountCode::class;

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

        // Generar tipos de descuento realistas
        $discountType = $this->faker->randomElement(['percentage', 'fixed_amount']);
        $discountValue = $discountType === 'percentage'
            ? $this->faker->numberBetween(5, 50) // 5% a 50%
            : $this->faker->numberBetween(10, 200); // 10 a 200 euros

        // Generar total de usos realista (o null para ilimitado)
        $total = $this->faker->optional(0.3)->passthrough($this->faker->numberBetween(10, 500));
        $remaining = $total !== null ? $this->faker->numberBetween(0, $total) : null;

        // Generar nombres descriptivos realistas
        $nameTemplates = [
            'Descuento ' . $this->faker->randomElement(['Verano', 'Invierno', 'Primavera', 'Otoño']) . ' ' . $this->faker->year(),
            $this->faker->randomElement(['Black Friday', 'Cyber Monday', 'Bienvenida', 'Fidelización', 'Promoción']),
            'Oferta ' . $this->faker->randomElement(['Especial', 'Limitada', 'Exclusiva', 'Premium']),
            $discountType === 'percentage' ? $discountValue . '% Descuento' : $discountValue . '€ Descuento',
        ];

        return [
            'code' => strtoupper($this->faker->bothify('????##')), // Ej: ABCD12
            'name' => $this->faker->randomElement($nameTemplates),
            'description' => $this->faker->optional(0.7)->sentence(8),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'quantity' => null, // DEPRECATED
            'percentage' => null, // DEPRECATED
            'school_id' => $school->id,
            'total' => $total,
            'remaining' => $remaining,
            'max_uses_per_user' => $this->faker->randomElement([1, 3, 5]),
            'valid_from' => $this->faker->optional(0.5)->dateTimeBetween('-1 month', '+1 month'),
            'valid_to' => $this->faker->optional(0.5)->dateTimeBetween('+1 month', '+6 months'),
            'sport_ids' => null, // Por defecto aplica a todos
            'course_ids' => null,
            'client_ids' => null,
            'degree_ids' => null,
            'min_purchase_amount' => $this->faker->optional(0.3)->numberBetween(50, 200),
            'max_discount_amount' => $discountType === 'percentage' ? $this->faker->optional(0.3)->numberBetween(50, 300) : null,
            'applicable_to' => 'all',
            'active' => $this->faker->boolean(80), // 80% activos
            'created_by' => $this->faker->optional(0.5)->name(),
            'notes' => $this->faker->optional(0.4)->sentence(12),
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'deleted_at' => null
        ];
    }
}
