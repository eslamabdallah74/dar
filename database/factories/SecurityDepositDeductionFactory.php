<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\SecurityDepositDeduction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityDepositDeduction>
 */
class SecurityDepositDeductionFactory extends Factory
{
    protected $model = SecurityDepositDeduction::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'lease_id' => Lease::factory()->withDeposit(),
            'settlement_id' => null,
            'recorded_by' => User::factory(),
            'amount' => 500.00,
            'category' => 'fixture_damage',
            'description' => 'إصلاح مقبض الباب المكسور',
            'status' => 'confirmed',
            'deducted_at' => now(),
        ];
    }
}
