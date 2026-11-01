<?php

namespace Database\Factories;

use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    protected $model = MaintenanceRequest::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'property_id' => Property::factory(),
            'unit_id' => null,
            'reported_by_renter_id' => null,
            'title' => 'طلب صيانة دورية',
            'description' => fake()->paragraph(),
            'status' => 'open',
            'reported_at' => now(),
            'resolved_at' => null,
        ];
    }
}
