<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\MaintenanceRequest;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class MaintenanceExpenseService
{
    /**
     * Automatically sync or create the expense record linked to a maintenance request.
     * Guarantees 1-to-1 mapping and preserves any cost already entered by the user.
     */
    public function syncFromMaintenance(MaintenanceRequest $maintenance, ?int $userId = null): Expense
    {
        return DB::transaction(function () use ($maintenance, $userId) {
            $existingExpense = Expense::where('maintenance_request_id', $maintenance->id)
                ->first();

            $recordedBy = $userId
                ?? $existingExpense?->recorded_by
                ?? $maintenance->workspace?->owner_user_id
                ?? auth()->id();

            if ($existingExpense) {
                $updateData = [
                    'property_id' => $maintenance->property_id,
                    'unit_id' => $maintenance->unit_id,
                    'title' => $maintenance->title,
                ];

                // If notes were empty or match old description, keep it synced
                if (empty($existingExpense->notes) || $existingExpense->notes === $existingExpense->getOriginal('notes')) {
                    $updateData['notes'] = $maintenance->description;
                }

                $existingExpense->update($updateData);

                return $existingExpense->fresh();
            }

            return Expense::create([
                'workspace_id' => $maintenance->workspace_id,
                'property_id' => $maintenance->property_id,
                'unit_id' => $maintenance->unit_id,
                'maintenance_request_id' => $maintenance->id,
                'category' => 'صيانة وتشغيل',
                'title' => $maintenance->title,
                'amount' => null,
                'expense_date' => $maintenance->reported_at?->toDateString() ?? now()->toDateString(),
                'notes' => $maintenance->description,
                'origin' => 'maintenance',
                'status' => 'pending_amount',
                'recorded_by' => $recordedBy,
            ]);
        });
    }

    /**
     * Update an expense from the Expenses page.
     * Transitions maintenance expenses between pending_amount and ready_to_apply.
     */
    public function updateExpense(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            if ($expense->origin === 'maintenance') {
                $hasAmount = array_key_exists('amount', $data) && $data['amount'] !== null && (float) $data['amount'] > 0;

                if (! $expense->isApplied()) {
                    if ($hasAmount) {
                        $data['status'] = 'ready_to_apply';
                    } else {
                        $data['status'] = 'pending_amount';
                        $data['amount'] = null;
                    }
                }
            }

            $expense->update($data);

            return $expense->fresh();
        });
    }

    /**
     * Apply eligible maintenance expenses that have a valid amount entered.
     * If maintenanceRequestId is provided, applies only that specific record.
     * Otherwise applies all eligible maintenance expenses in the workspace.
     *
     * @return array{
     *     applied_count: int,
     *     applied_total: float,
     *     skipped_missing_amount: int,
     *     already_applied: int,
     *     pending_titles: array<string>,
     *     already_applied_titles: array<string>
     * }
     */
    public function applyExpenses(Workspace $workspace, int $userId, ?int $maintenanceRequestId = null): array
    {
        return DB::transaction(function () use ($workspace, $userId, $maintenanceRequestId) {
            $query = Expense::where('workspace_id', $workspace->id)
                ->where('origin', 'maintenance')
                ->with('maintenanceRequest');

            if ($maintenanceRequestId !== null) {
                $query->where('maintenance_request_id', $maintenanceRequestId);
            }

            $expenses = $query->lockForUpdate()->get();

            $appliedCount = 0;
            $appliedTotal = 0.0;
            $skippedMissingAmount = 0;
            $alreadyApplied = 0;
            $pendingTitles = [];
            $alreadyAppliedTitles = [];

            foreach ($expenses as $expense) {
                $title = $expense->title ?: ($expense->maintenanceRequest?->title ?? "طلب صيانة #{$expense->maintenance_request_id}");

                if ($expense->isApplied()) {
                    $alreadyApplied++;
                    $alreadyAppliedTitles[] = $title;

                    continue;
                }

                if ($expense->amount === null || (float) $expense->amount <= 0) {
                    $skippedMissingAmount++;
                    $pendingTitles[] = $title;

                    continue;
                }

                // Eligible to be applied!
                $expense->update([
                    'status' => 'applied',
                    'applied_at' => now(),
                    'applied_by' => $userId,
                ]);

                $appliedCount++;
                $appliedTotal += (float) $expense->amount;
            }

            return [
                'applied_count' => $appliedCount,
                'applied_total' => $appliedTotal,
                'skipped_missing_amount' => $skippedMissingAmount,
                'already_applied' => $alreadyApplied,
                'pending_titles' => $pendingTitles,
                'already_applied_titles' => $alreadyAppliedTitles,
            ];
        });
    }
}
