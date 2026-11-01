<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceSubscription>
 */
class WorkspaceSubscriptionFactory extends Factory
{
    protected $model = WorkspaceSubscription::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'plan_id' => Plan::factory(),
            'activated_by' => User::factory(),
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addYear(),
            'provider_reference' => 'SUB-'.fake()->numerify('#####'),
        ];
    }
}
