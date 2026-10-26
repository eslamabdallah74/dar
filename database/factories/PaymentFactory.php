<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Renter;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'renter_id' => Renter::factory(),
            'recorded_by' => User::factory(),
            'amount' => 3000.00,
            'paid_at' => now(),
            'payment_method' => 'bank_transfer',
            'reference' => 'PAY-'.strtoupper(fake()->bothify('##??####')),
            'notes' => fake()->sentence(),
        ];
    }
}
