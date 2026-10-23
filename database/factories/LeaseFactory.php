<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    protected $model = Lease::class;

    public function definition(): array
    {
        $startsOn = now()->subMonths(3)->startOfMonth();
        $endsOn = (clone $startsOn)->addYear()->subDay();

        return [
            'workspace_id' => Workspace::factory(),
            'unit_id' => Unit::factory(),
            'renter_id' => Renter::factory(),
            'renewed_from_lease_id' => null,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'rent_amount' => 3000.00,
            'billing_interval' => 'monthly',
            'billing_anchor_day' => 1,
            'first_due_date' => $startsOn->toDateString(),
            'status' => 'active',
            'notes' => fake()->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'starts_on' => now()->subMonths(3)->startOfMonth()->toDateString(),
            'ends_on' => now()->addMonths(9)->endOfMonth()->toDateString(),
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'starts_on' => now()->subMonths(11)->startOfMonth()->toDateString(),
            'ends_on' => now()->addDays(15)->toDateString(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'starts_on' => now()->subMonths(14)->startOfMonth()->toDateString(),
            'ends_on' => now()->subMonths(2)->endOfMonth()->toDateString(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'starts_on' => now()->addMonth()->startOfMonth()->toDateString(),
            'ends_on' => now()->addMonths(13)->endOfMonth()->toDateString(),
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn () => [
            'status' => 'terminated',
            'starts_on' => now()->subMonths(6)->startOfMonth()->toDateString(),
            'ends_on' => now()->addMonths(6)->endOfMonth()->toDateString(),
        ]);
    }
}
