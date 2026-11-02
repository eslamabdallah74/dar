<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Exceptions\AiExecutionException;

class AiToolRegistry
{
    /**
     * @var array<string, AiToolContract>
     */
    private array $tools = [];

    /**
     * @param  iterable<AiToolContract>  $tools
     */
    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(AiToolContract $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function resolve(string $name): AiToolContract
    {
        if (! isset($this->tools[$name])) {
            throw new AiExecutionException("الأداة [{$name}] غير معروفة للنظام.");
        }

        return $this->tools[$name];
    }

    /**
     * @return array<int, array{type: 'function', function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function getDefinitions(): array
    {
        return array_values(array_map(
            static fn (AiToolContract $tool): array => $tool->definition(),
            $this->tools
        ));
    }
}
