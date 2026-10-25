<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseOrigin;
use App\Enums\ExpenseStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Expense;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaintenanceExpenseService
{
    /**
     * Atomically create a maintenance request and its corresponding pending expense.
     * Guarantees that neither record can be orphaned if the other fails.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \Throwable
     */
    public function createMaintenanceWithExpense(Workspace $workspace, array $data, ?int $userId = null): MaintenanceRequest
    {
        return DB::transaction(function () use ($workspace, $data, $userId) {
            $recordedBy = $userId ?? $workspace->owner_user_id ?? auth()->id();

            // Validate property belongs to workspace
            $propertyExists = $workspace->properties()->where('id', $data['property_id'])->exists();
            if (! $propertyExists) {
                throw new \InvalidArgumentException('العقار المحدد غير صالح أو لا ينتمي لمساحة العمل الحالية.');
            }

            // Validate unit belongs to property and workspace if specified
            if (! empty($data['unit_id'])) {
                $unitExists = $workspace->units()
                    ->where('id', $data['unit_id'])
                    ->where('property_id', $data['property_id'])
                    ->exists();

                if (! $unitExists) {
                    throw new \InvalidArgumentException('الوحدة المحددة غير صالحة أو لا تنتمي للعقار المحدد.');
                }
            }

            // 1. Create maintenance request
            $maintenance = $workspace->maintenanceRequests()->create([
                'property_id' => $data['property_id'],
                'unit_id' => $data['unit_id'] ?? null,
                'reported_by_renter_id' => $data['reported_by_renter_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'status' => MaintenanceStatus::Pending->value,
                'reported_at' => now(),
            ]);

            // 2. Atomically create corresponding pending expense with null amount
            $expense = Expense::create([
                'workspace_id' => $workspace->id,
                'property_id' => $maintenance->property_id,
                'unit_id' => $maintenance->unit_id,
                'maintenance_request_id' => $maintenance->id,
                'category' => 'صيانة وتشغيل',
                'title' => $maintenance->title,
                'amount' => null, // Explicitly null initially
                'expense_date' => now()->toDateString(),
                'notes' => $maintenance->description,
                'origin' => ExpenseOrigin::Maintenance->value,
                'status' => ExpenseStatus::PendingAmount->value,
                'payment_status' => 'unpaid',
                'recorded_by' => $recordedBy,
            ]);

            // 3. Store initial attachments if provided
            if (! empty($data['attachments']) && is_array($data['attachments'])) {
                foreach ($data['attachments'] as $file) {
                    if ($file instanceof UploadedFile) {
                        $originalName = $file->getClientOriginalName();
                        $extension = $file->getClientOriginalExtension() ?: 'bin';
                        $safeName = Str::uuid()->toString().'.'.strtolower($extension);
                        $path = $file->storeAs("maintenance/{$workspace->id}/{$maintenance->id}", $safeName, 'local');

                        $maintenance->attachments()->create([
                            'workspace_id' => $workspace->id,
                            'file_path' => $path,
                            'original_filename' => $originalName,
                            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                            'file_size' => $file->getSize() ?: 0,
                            'uploaded_by' => $recordedBy,
                        ]);
                    }
                }
            }

            // 4. Record history event
            if (function_exists('activity')) {
                $causer = $userId ? User::find($userId) : auth()->user();
                activity('maintenance')
                    ->performedOn($maintenance)
                    ->causedBy($causer)
                    ->withProperties([
                        'workspace_id' => $workspace->id,
                        'property_id' => $maintenance->property_id,
                        'unit_id' => $maintenance->unit_id,
                        'title' => $maintenance->title,
                        'status' => $maintenance->status,
                        'expense_id' => $expense->id,
                    ])
                    ->log('تم تسجيل طلب صيانة جديد وإنشاء بند المصروف المرتبط به');
            }

            return $maintenance->load(['expense', 'attachments']);
        });
    }

    /**
     * Automatically sync or create the expense record linked to a maintenance request.
     * Guarantees 1-to-1 mapping and preserves any amount already entered by the user.
     */
    public function syncFromMaintenance(MaintenanceRequest $maintenance, ?int $userId = null): Expense
    {
        return DB::transaction(function () use ($maintenance, $userId) {
            $existingExpense = Expense::where('maintenance_request_id', $maintenance->id)
                ->lockForUpdate()
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
                'origin' => ExpenseOrigin::Maintenance->value,
                'status' => ExpenseStatus::PendingAmount->value,
                'payment_status' => 'unpaid',
                'recorded_by' => $recordedBy,
            ]);
        });
    }

    /**
     * Safely transition a maintenance request's status with concurrency controls,
     * timestamp recordings, completion eligibility validation, and history logging.
     *
     * @throws \DomainException|\InvalidArgumentException
     */
    public function transitionStatus(MaintenanceRequest $maintenance, string $newStatus, ?int $userId = null): MaintenanceRequest
    {
        return DB::transaction(function () use ($maintenance, $newStatus, $userId) {
            $maintenance = MaintenanceRequest::where('id', $maintenance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $maintenance->status;

            $targetStatus = match ($newStatus) {
                'pending', 'open' => 'pending',
                'in_progress' => 'in_progress',
                'completed', 'closed' => 'completed',
                default => throw new \InvalidArgumentException("حالة غير صالحة: {$newStatus}"),
            };

            if ($targetStatus === 'completed') {
                // Lock and inspect linked expense
                $expense = Expense::where('maintenance_request_id', $maintenance->id)
                    ->lockForUpdate()
                    ->first();

                if (! $expense || (int) $expense->workspace_id !== (int) $maintenance->workspace_id || (int) $expense->maintenance_request_id !== (int) $maintenance->id) {
                    throw new \DomainException('بند المصروف المرتبط بطلب الصيانة مفقود أو غير مرتبط بصورة صحيحة.');
                }

                if ($expense->amount === null || (float) $expense->amount <= 0) {
                    throw new \DomainException('يجب تسجيل تكلفة الصيانة أولًا من صفحة المصروفات قبل إنهاء الطلب.');
                }

                // Finalize expense status to recorded, but DO NOT mark as paid
                $expense->update([
                    'status' => ExpenseStatus::Recorded->value,
                ]);

                $maintenance->update([
                    'status' => 'completed',
                    'resolved_at' => now(),
                ]);
            } elseif ($targetStatus === 'in_progress') {
                $updates = ['status' => 'in_progress'];
                if (! $maintenance->started_at) {
                    $updates['started_at'] = now();
                }
                $maintenance->update($updates);
            } else {
                // Revert to pending
                $maintenance->update([
                    'status' => 'pending',
                    'resolved_at' => null,
                ]);

                $expense = Expense::where('maintenance_request_id', $maintenance->id)->first();
                if ($expense) {
                    $expense->update([
                        'status' => ($expense->amount !== null && (float) $expense->amount > 0)
                            ? ExpenseStatus::Ready->value
                            : ExpenseStatus::PendingAmount->value,
                    ]);
                }
            }

            // Log activity history
            if (function_exists('activity')) {
                $causer = $userId ? User::find($userId) : auth()->user();
                activity('maintenance')
                    ->performedOn($maintenance)
                    ->causedBy($causer)
                    ->withProperties([
                        'workspace_id' => $maintenance->workspace_id,
                        'from_status' => $oldStatus,
                        'to_status' => $targetStatus,
                        'started_at' => $maintenance->started_at?->toIso8601String(),
                        'resolved_at' => $maintenance->resolved_at?->toIso8601String(),
                    ])
                    ->log(match ($targetStatus) {
                        'in_progress' => 'تم بدء العمل في طلب الصيانة',
                        'completed' => 'تم إنهاء وإكمال طلب الصيانة بنجاح',
                        default => 'تم تعديل حالة طلب الصيانة إلى قيد الانتظار',
                    });
            }

            return $maintenance->fresh(['expense']);
        });
    }

    /**
     * Mark a maintenance request as completed. Requires a valid recorded cost.
     */
    public function completeRequest(MaintenanceRequest $maintenance, ?int $userId = null): MaintenanceRequest
    {
        return $this->transitionStatus($maintenance, MaintenanceStatus::Completed->value, $userId);
    }

    /**
     * Update an expense from the Expenses page.
     * Updates status based on whether an amount is entered.
     */
    public function updateExpense(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            $expense = Expense::where('id', $expense->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Strip protected system fields that clients must never mass-assign
            unset($data['workspace_id'], $data['payment_status'], $data['paid_at'], $data['recorded_by']);

            if ($expense->origin === ExpenseOrigin::Maintenance->value || $expense->maintenance_request_id !== null) {
                // Prevent altering property, unit, origin, or maintenance link on linked maintenance expenses
                unset($data['property_id'], $data['unit_id'], $data['maintenance_request_id'], $data['origin']);

                $hasAmount = array_key_exists('amount', $data)
                    && $data['amount'] !== null
                    && (float) $data['amount'] > 0;

                if ($hasAmount) {
                    $linkedMaintenance = $expense->relationLoaded('maintenanceRequest')
                        ? $expense->maintenanceRequest
                        : $expense->maintenanceRequest()->first();

                    $data['status'] = ($linkedMaintenance && $linkedMaintenance->isCompleted())
                        ? ExpenseStatus::Recorded->value
                        : ExpenseStatus::Ready->value;

                    if ($linkedMaintenance && function_exists('activity')) {
                        activity('maintenance')
                            ->performedOn($linkedMaintenance)
                            ->causedBy(auth()->user())
                            ->withProperties([
                                'workspace_id' => $expense->workspace_id,
                                'expense_id' => $expense->id,
                                'amount' => $data['amount'],
                            ])
                            ->log('تم تسجيل تكلفة الصيانة بمبلغ: '.number_format((float) $data['amount'], 2));
                    }
                } else {
                    $data['status'] = ExpenseStatus::PendingAmount->value;
                    $data['amount'] = null;
                }
            } else {
                // Manual expenses cannot be linked to maintenance via generic update
                unset($data['maintenance_request_id']);
            }

            $expense->update($data);

            return $expense->fresh();
        });
    }
}
