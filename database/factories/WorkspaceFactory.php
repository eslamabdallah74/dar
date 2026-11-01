<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'name' => 'عقارات '.fake()->company(),
            'slug' => fake()->unique()->slug(),
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
            'status' => 'active',
            'parent_unit_limit' => 1,
            'active_rental_limit' => 1,
            'rent_reminder_days_before' => 5,
        ];
    }

    /**
     * Indicate that the workspace has unlimited creation and rental limits.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_unit_limit' => null,
            'active_rental_limit' => null,
        ]);
    }
}
