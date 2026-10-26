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
            'rent_reminder_days_before' => 5,
        ];
    }
}
