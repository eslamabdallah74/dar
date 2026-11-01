<?php

namespace Database\Factories;

use App\Models\Renter;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Renter>
 */
class RenterFactory extends Factory
{
    protected $model = Renter::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->name(),
            'phone' => fake()->randomElement(['010', '011', '012', '015']).fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'national_id' => fake()->numerify('1#########'),
            'gender' => fake()->randomElement(['male', 'female']),
            'whatsapp_opt_in_at' => now()->subMonths(2),
            'whatsapp_opt_in_source' => 'contract_signature',
            'whatsapp_opt_in_recorded_by' => null,
            'whatsapp_opted_out_at' => null,
            'notes' => fake()->sentence(),
        ];
    }
}
