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
}
