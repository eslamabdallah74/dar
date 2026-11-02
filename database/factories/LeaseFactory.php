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
            'security_deposit_amount' => 0.00,
            'security_deposit_status' => 'not_required',
            'billing_interval' => 'monthly',
            'billing_anchor_day' => 1,
            'first_due_date' => $startsOn->toDateString(),
            'status' => 'active',
            'notes' => fake()->sentence(),
        ];
    }

    public function withDeposit(float $amount = 5000.00): static
    {
        return $this->state(fn () => [
            'security_deposit_amount' => $amount,
            'security_deposit_status' => 'due',
        ]);
    }
}
