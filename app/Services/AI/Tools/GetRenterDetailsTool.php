<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Models\Lease;
use App\Models\Renter;
use App\Models\Workspace;
use App\Services\AI\AiToolContract;

class GetRenterDetailsTool implements AiToolContract
{
    public function name(): string
    {
        return 'get_renter_details';
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
                'description' => 'البحث عن بيانات مستأجر وعقوده الحالية بالاسم (Search renter by name and retrieve active leases without PII)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'renter_name' => [
                            'type' => 'string',
                            'description' => 'اسم المستأجر أو جزء منه للبحث (Renter name or partial search keyword)',
                        ],
                    ],
                    'required' => ['renter_name'],
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
        $rawName = $arguments['renter_name'] ?? '';
        $name = is_string($rawName) ? trim($rawName) : '';

        if ($name === '') {
            return [
                'count' => 0,
                'renters' => [],
            ];
        }

        // Query only id, workspace_id, name - national_id, phone and email are strictly omitted
        $renters = $workspace->renters()
            ->select(['id', 'workspace_id', 'name'])
            ->where('name', 'like', '%'.$name.'%')
            ->with([
                'leases' => static function ($query): void {
                    $query->where('status', 'active')
                        ->with(['unit:id,name,code,unit_type']);
                },
            ])
            ->limit(5)
            ->get();

        $items = $renters->map(static fn (Renter $renter): array => [
            'name' => $renter->name,
            'active_leases' => $renter->leases->map(static fn (Lease $lease): array => [
                'contract_number' => $lease->contract_number,
                'unit_name' => $lease->unit !== null ? $lease->unit->name : '—',
                'rent_amount' => (float) $lease->rent_amount,
                'starts_on' => $lease->starts_on->toDateString(),
                'ends_on' => $lease->ends_on->toDateString(),
            ])->all(),
        ])->all();

        return [
            'count' => count($items),
            'currency' => $workspace->currencySymbol(),
            'renters' => $items,
        ];
    }
}
