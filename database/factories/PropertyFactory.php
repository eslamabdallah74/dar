<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => 'عمارة '.fake()->streetName(),
            'property_type' => fake()->randomElement(['building', 'villa', 'other']),
            'address' => fake()->address(),
        ];
    }

    public function building(): static
    {
        return $this->state(fn () => [
            'property_type' => 'building',
        ]);
    }

    public function villa(): static
    {
        return $this->state(fn () => [
            'property_type' => 'villa',
        ]);
    }
}
