<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Models\Unit;
use App\Models\Workspace;
use App\Services\AI\AiToolContract;
use Carbon\Carbon;

class GetVacantUnitsTool implements AiToolContract
{
    public function name(): string
    {
        return 'get_vacant_units';
    }

    /**
     * @return array{type: 'function', function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'استعلام عن الوحدات العقارية الشاغرة والقابلة للتأجير حالياً (List currently vacant and rentable units)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, Workspace $workspace): array
    {
        $today = Carbon::today()->toDateString();

        $units = $workspace->units()
            ->select(['id', 'workspace_id', 'property_id', 'parent_unit_id', 'unit_type', 'code', 'name', 'is_rentable'])
            ->where('is_rentable', true)
            ->whereDoesntHave('leases', static function ($query) use ($today): void {
                $query->where('status', 'active')
                    ->where('starts_on', '<=', $today)
                    ->where('ends_on', '>=', $today);
            })
            ->with(['property:id,name'])
            ->limit(50)
            ->get();

        $items = $units->map(static fn (Unit $unit): array => [
            'id' => $unit->id,
            'name' => $unit->name,
            'code' => $unit->code,
            'unit_type' => $unit->unit_type,
            'property_name' => $unit->property !== null ? $unit->property->name : '—',
        ])->all();

        return [
            'count' => count($items),
            'units' => $items,
        ];
    }
}
