<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Workspace;

interface AiToolContract
{
    /**
     * Unique tool name used in function calling.
     */
    public function name(): string;

    /**
     * OpenAI tool schema definition.
     *
     * @return array{type: 'function', function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    public function definition(): array;

    /**
     * Execute the tool with authenticated workspace isolation.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, Workspace $workspace): array;
}
