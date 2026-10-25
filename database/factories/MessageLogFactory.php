<?php

namespace Database\Factories;

use App\Models\MessageLog;
use App\Models\Renter;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageLog>
 */
class MessageLogFactory extends Factory
{
    protected $model = MessageLog::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'renter_id' => Renter::factory(),
            'lease_id' => null,
            'rent_charge_id' => null,
            'recipient_phone' => fake()->randomElement(['010', '011', '012', '015']).fake()->numerify('########'),
            'template_name' => 'rent_due_reminder_ar',
            'scheduled_at' => now(),
            'sent_at' => now(),
            'status' => 'delivered',
            'provider_message_id' => 'MSG-'.fake()->uuid(),
            'error_code' => null,
            'error_message' => null,
            'attempts' => 1,
            'provider_cost' => 0.045000,
            'provider_cost_currency' => 'USD',
            'deduplication_key' => fake()->unique()->uuid(),
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'sent_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'sent_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_code' => 'undelivered',
            'error_message' => 'تعذر التسليم لرقم الهاتف',
        ]);
    }
}
