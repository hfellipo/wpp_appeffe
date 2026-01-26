<?php

namespace Database\Factories;

use App\Models\ContactField;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContactField>
 */
class ContactFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();
        
        return [
            'user_id' => User::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'type' => fake()->randomElement(array_keys(ContactField::TYPES)),
            'options' => null,
            'required' => fake()->boolean(20),
            'show_in_list' => fake()->boolean(70),
            'order' => fake()->numberBetween(1, 100),
            'active' => true,
        ];
    }

    /**
     * Indicate that the field is a text field.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'options' => null,
        ]);
    }

    /**
     * Indicate that the field is a select field with options.
     */
    public function select(array $options = ['Option 1', 'Option 2', 'Option 3']): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'select',
            'options' => $options,
        ]);
    }

    /**
     * Indicate that the field is a date field.
     */
    public function date(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'date',
            'options' => null,
        ]);
    }

    /**
     * Indicate that the field is required.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'required' => true,
        ]);
    }

    /**
     * Indicate that the field is not required.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'required' => false,
        ]);
    }

    /**
     * Indicate that the field should be shown in list.
     */
    public function showInList(): static
    {
        return $this->state(fn (array $attributes) => [
            'show_in_list' => true,
        ]);
    }

    /**
     * Indicate that the field should be hidden from list.
     */
    public function hideFromList(): static
    {
        return $this->state(fn (array $attributes) => [
            'show_in_list' => false,
        ]);
    }

    /**
     * Indicate that the field is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
