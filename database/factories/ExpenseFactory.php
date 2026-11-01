<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Property;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'property_id' => Property::factory(),
            'unit_id' => null,
            'maintenance_request_id' => null,
            'recorded_by' => User::factory(),
            'category' => 'maintenance',
            'amount' => 500.00,
            'expense_date' => now()->toDateString(),
            'notes' => fake()->sentence(),
        ];
    }
}
