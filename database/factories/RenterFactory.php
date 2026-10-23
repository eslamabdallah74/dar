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
            'phone' => '05'.fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'national_id' => fake()->numerify('1#########'),
            'whatsapp_opt_in_at' => now()->subMonths(2),
            'whatsapp_opt_in_source' => 'contract_signature',
            'whatsapp_opt_in_recorded_by' => null,
            'whatsapp_opted_out_at' => null,
            'notes' => fake()->sentence(),
        ];
    }

    public function optedIn(): static
    {
        return $this->state(fn () => [
            'whatsapp_opt_in_at' => now()->subMonths(1),
            'whatsapp_opted_out_at' => null,
        ]);
    }

    public function optedOut(): static
    {
        return $this->state(fn () => [
            'whatsapp_opt_in_at' => now()->subMonths(3),
            'whatsapp_opted_out_at' => now()->subDay(),
        ]);
    }
}
