<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Models\Lease;
use App\Models\Workspace;
use App\Services\AI\AiToolContract;
use Carbon\Carbon;

class GetExpiringContractsTool implements AiToolContract
{
    public function name(): string
    {
        return 'get_expiring_contracts';
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
                'description' => 'استعلام عن عقود الإيجار النشطة التي تقترب من الانتهاء خلال فترة معينة بالأيام (List active leases expiring within N days)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'days' => [
                            'type' => 'integer',
                            'description' => 'عدد الأيام القادمة للبحث عن العقود المنتهية خلالها افتراضياً 30 يوماً (Number of days ahead, default 30)',
                        ],
                    ],
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
        $rawDays = $arguments['days'] ?? 30;
        $days = is_numeric($rawDays) ? max(1, min(365, (int) $rawDays)) : 30;

        $today = Carbon::today();
        $targetDate = $today->copy()->addDays($days);

        $leases = $workspace->leases()
            ->where('status', 'active')
            ->whereBetween('ends_on', [
                $today->toDateString(),
                $targetDate->toDateString(),
            ])
            ->with(['renter', 'unit'])
            ->orderBy('ends_on')
            ->limit(50)
            ->get();

        $items = $leases->map(static fn (Lease $lease): array => [
            'contract_number' => $lease->contract_number,
            'renter_name' => $lease->renter !== null ? $lease->renter->name : '—',
            'unit_name' => $lease->unit !== null ? $lease->unit->name : '—',
            'starts_on' => $lease->starts_on->toDateString(),
            'ends_on' => $lease->ends_on->toDateString(),
            'days_remaining' => (int) $today->diffInDays($lease->ends_on, false),
            'rent_amount' => (float) $lease->rent_amount,
        ])->all();

        return [
            'days_window' => $days,
            'count' => count($items),
            'currency' => $workspace->currencySymbol(),
            'contracts' => $items,
        ];
    }
}
