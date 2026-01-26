<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ddd = fake()->numberBetween(11, 99);
        $number = fake()->numberBetween(90000, 99999);
        $suffix = fake()->numberBetween(1000, 9999);

        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'phone' => "({$ddd}){$number}-{$suffix}",
            'email' => fake()->optional(0.7)->safeEmail(),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Indicate that the contact has an email.
     */
    public function withEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => fake()->safeEmail(),
        ]);
    }

    /**
     * Indicate that the contact has notes.
     */
    public function withNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => fake()->paragraph(),
        ]);
    }
}
