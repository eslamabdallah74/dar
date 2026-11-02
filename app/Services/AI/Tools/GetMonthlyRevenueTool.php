<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Models\Workspace;
use App\Services\AI\AiToolContract;
use Carbon\Carbon;

class GetMonthlyRevenueTool implements AiToolContract
{
    public function name(): string
    {
        return 'get_monthly_revenue';
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
                'description' => 'حساب إجمالي الإيرادات والمدفوعات المحصلة لشهر محدد (Calculate total collected revenue for a given month YYYY-MM)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'month' => [
                            'type' => 'string',
                            'description' => 'الشهر المراد الاستعلام عنه بصيغة YYYY-MM مثل 2026-10 (Month formatted as YYYY-MM)',
                        ],
                    ],
                    'required' => ['month'],
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
        $rawMonth = $arguments['month'] ?? null;
        $month = is_string($rawMonth) && preg_match('/^\d{4}-\d{2}$/', trim($rawMonth)) === 1
            ? trim($rawMonth)
            : Carbon::now()->format('Y-m');

        $parsed = Carbon::createFromFormat('Y-m-d', $month.'-01');
        $startOfMonth = $parsed instanceof Carbon ? $parsed->startOfMonth() : Carbon::now()->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $totalRevenue = (float) $workspace->payments()
            ->whereBetween('paid_at', [
                $startOfMonth->toDateTimeString(),
                $endOfMonth->toDateTimeString(),
            ])
            ->sum('amount');

        return [
            'month' => $month,
            'total_revenue' => $totalRevenue,
            'currency' => $workspace->currencySymbol(),
            'formatted' => number_format($totalRevenue, 2).' '.$workspace->currencySymbol(),
        ];
    }
}
