<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RentCharge;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'payment_id' => Payment::factory(),
            'rent_charge_id' => RentCharge::factory(),
            'amount' => 3000.00,
        ];
    }
}
