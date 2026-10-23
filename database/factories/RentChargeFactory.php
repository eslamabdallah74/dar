<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\RentCharge;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentCharge>
 */
class RentChargeFactory extends Factory
{
    protected $model = RentCharge::class;

    public function definition(): array
    {
        $start = now()->startOfMonth();
        $end = (clone $start)->endOfMonth();

        return [
            'workspace_id' => Workspace::factory(),
            'lease_id' => Lease::factory(),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'due_date' => $start->toDateString(),
            'amount' => 3000.00,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->subDays(10)->toDateString(),
        ]);
    }

    public function future(): static
    {
        return $this->state(fn () => [
            'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
            'period_end' => now()->addMonth()->endOfMonth()->toDateString(),
            'due_date' => now()->addMonth()->startOfMonth()->toDateString(),
        ]);
    }
}
