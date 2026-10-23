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

    public function starter(): static
    {
        return $this->state(fn () => [
            'name' => 'باقة الانطلاق (Starter)',
            'slug' => 'starter',
            'monthly_price' => 0.00,
            'unit_limit' => 5,
            'is_active' => true,
        ]);
    }

    public function growth(): static
    {
        return $this->state(fn () => [
            'name' => 'باقة النمو (Growth)',
            'slug' => 'growth',
            'monthly_price' => 199.00,
            'unit_limit' => 25,
            'is_active' => true,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'name' => 'باقة المحترفين (Pro)',
            'slug' => 'pro',
            'monthly_price' => 499.00,
            'unit_limit' => 100,
            'is_active' => true,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state(fn () => [
            'name' => 'باقة الشركات (Enterprise)',
            'slug' => 'enterprise',
            'monthly_price' => 999.00,
            'unit_limit' => null,
            'is_active' => true,
        ]);
    }
}
