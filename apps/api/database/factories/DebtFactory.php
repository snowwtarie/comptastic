<?php

namespace Database\Factories;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Prêt '.$this->faker->word(),
            'original_amount_cents' => 1000000,
            'remaining_amount_cents' => 500000,
            'monthly_payment_cents' => 20000,
            'rate_bps' => 390,
            'end_date' => '2029-01-01',
        ];
    }
}
