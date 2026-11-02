<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\SecurityDepositRefund;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityDepositRefund>
 */
class SecurityDepositRefundFactory extends Factory
{
    protected $model = SecurityDepositRefund::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'lease_id' => Lease::factory()->withDeposit(),
            'settlement_id' => null,
            'recorded_by' => User::factory(),
            'amount' => 5000.00,
            'refunded_at' => now(),
            'payment_method' => 'bank_transfer',
            'reference' => 'REF-'.fake()->numerify('####'),
            'notes' => 'رد مبلغ التأمين بالكامل',
            'is_reversed' => false,
            'reversal_reason' => null,
            'reversed_at' => null,
            'reversed_by' => null,
        ];
    }
}
