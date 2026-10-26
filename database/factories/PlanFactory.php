<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => 'باقة النمو (Growth)',
            'slug' => fake()->unique()->slug(),
            'monthly_price' => 199.00,
            'unit_limit' => 25,
            'is_active' => true,
        ];
    }
}
