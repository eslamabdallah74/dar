<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\SecurityDepositSettlement;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityDepositSettlement>
 */
class SecurityDepositSettlementFactory extends Factory
{
    protected $model = SecurityDepositSettlement::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'lease_id' => Lease::factory()->withDeposit(),
            'settled_by' => User::factory(),
            'status' => 'under_review',
            'inspection_category' => 'no_issues',
            'inspection_notes' => 'تم فحص الوحدة وحالتها ممتازة',
            'total_received' => 5000.00,
            'total_deductions' => 0.00,
            'total_refunded' => 0.00,
            'refundable_balance' => 5000.00,
            'settled_at' => null,
            'notes' => null,
        ];
    }

    public function settled(): static
    {
        return $this->state(fn () => [
            'status' => 'settled',
            'settled_at' => now(),
        ]);
    }
}
