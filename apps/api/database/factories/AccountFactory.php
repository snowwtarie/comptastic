<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
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
            'name' => $this->faker->company().' - Compte courant',
            'bank' => $this->faker->company(),
            'type' => 'checking',
            'iban_last4' => $this->faker->numerify('####'),
            'opening_balance_cents' => 0,
        ];
    }
}
