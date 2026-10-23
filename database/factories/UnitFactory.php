<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Unit;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'property_id' => Property::factory(),
            'parent_unit_id' => null,
            'unit_type' => 'apartment',
            'code' => 'APT-'.fake()->unique()->numerify('###'),
            'name' => 'شقة '.fake()->numerify('###'),
            'is_rentable' => true,
        ];
    }

    public function apartment(): static
    {
        return $this->state(fn () => [
            'unit_type' => 'apartment',
        ]);
    }

    public function room(): static
    {
        return $this->state(fn () => [
            'unit_type' => 'room',
        ]);
    }

    public function bed(): static
    {
        return $this->state(fn () => [
            'unit_type' => 'bed',
        ]);
    }

    public function rentable(): static
    {
        return $this->state(fn () => [
            'is_rentable' => true,
        ]);
    }

    public function notRentable(): static
    {
        return $this->state(fn () => [
            'is_rentable' => false,
        ]);
    }
}
