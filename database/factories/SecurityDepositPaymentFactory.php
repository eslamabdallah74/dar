<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\SecurityDepositPayment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityDepositPayment>
 */
class SecurityDepositPaymentFactory extends Factory
{
    protected $model = SecurityDepositPayment::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'lease_id' => Lease::factory()->withDeposit(),
            'recorded_by' => User::factory(),
            'amount' => 5000.00,
            'paid_at' => now(),
            'payment_method' => 'cash',
            'reference' => 'DEP-REC-'.fake()->numerify('####'),
            'notes' => null,
            'is_reversed' => false,
            'reversal_reason' => null,
            'reversed_at' => null,
            'reversed_by' => null,
        ];
    }

    public function reversed(): static
    {
        return $this->state(fn () => [
            'is_reversed' => true,
            'reversal_reason' => 'سداد بالخطأ',
            'reversed_at' => now(),
            'reversed_by' => User::factory(),
        ]);
    }
}
