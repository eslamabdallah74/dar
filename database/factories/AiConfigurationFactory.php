<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiConfiguration>
 */
class AiConfigurationFactory extends Factory
{
    protected $model = AiConfiguration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'openai_api_key',
            'value' => 'sk-fake-test-key-1234567890',
            'is_active' => true,
        ];
    }
}
