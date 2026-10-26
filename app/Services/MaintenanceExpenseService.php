<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseOrigin;
use App\Enums\ExpenseStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Expense;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MaintenanceExpenseService
{
    public function __construct(
        private readonly DocumentStorageService $storage
    ) {}

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

            $this->assertLocationBelongsToWorkspace($workspace, $data['property_id'], $data['unit_id'] ?? null);

            $maintenance = $workspace->maintenanceRequests()->create([
                'property_id' => $data['property_id'],
                'unit_id' => $data['unit_id'] ?? null,
                'reported_by_renter_id' => $data['reported_by_renter_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'status' => MaintenanceStatus::Pending->value,
                'reported_at' => now(),
            ]);

            $expense = $this->createPendingExpense($maintenance, $recordedBy, now()->toDateString());

            if (is_array($data['attachments'] ?? null)) {
                foreach ($data['attachments'] as $file) {
                    if ($file instanceof UploadedFile) {
                        $this->storeAttachment($maintenance, $file, $recordedBy);
                    }
                }
            }

            activity('maintenance')
                ->performedOn($maintenance)
                ->causedBy($this->causer($userId))
                ->withProperties([
                    'workspace_id' => $workspace->id,
                    'property_id' => $maintenance->property_id,
                    'unit_id' => $maintenance->unit_id,
                    'title' => $maintenance->title,
                    'status' => $maintenance->status,
                    'expense_id' => $expense->id,
                ])
                ->log('تم تسجيل طلب صيانة جديد وإنشاء بند المصروف المرتبط به');

            return $maintenance->load(['expense', 'attachments']);
        });
    }

    /**
     * Store an uploaded maintenance attachment on the private disk and record it.
     */
    public function storeAttachment(MaintenanceRequest $maintenance, UploadedFile $file, int|string|null $uploadedBy): MaintenanceAttachment
    {
        return $maintenance->attachments()->create([
            'workspace_id' => $maintenance->workspace_id,
            'file_path' => $this->storage->storePrivate($file, "maintenance/{$maintenance->workspace_id}/{$maintenance->id}"),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize() ?: 0,
            'uploaded_by' => $uploadedBy,
        ]);
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

            if ($existingExpense === null) {
                $recordedBy = $userId ?? $maintenance->workspace->owner_user_id ?? auth()->id();

                return $this->createPendingExpense($maintenance, $recordedBy, $maintenance->reported_at->toDateString());
            }

            $updatedAttributes = [
                'property_id' => $maintenance->property_id,
                'unit_id' => $maintenance->unit_id,
                'title' => $maintenance->title,
            ];

            // Notes that are empty or still a copy of the previous description follow the description; notes the user wrote are kept.
            if (empty($existingExpense->notes) || $existingExpense->notes === $this->descriptionBeforeLastSave($maintenance)) {
                $updatedAttributes['notes'] = $maintenance->description;
            }

            $existingExpense->update($updatedAttributes);

            return $existingExpense->refresh();
        });
    }

    /**
     * The description the request had before its latest save; callers sync right after saving the request.
     */
    private function descriptionBeforeLastSave(MaintenanceRequest $maintenance): ?string
    {
        if (! $maintenance->wasChanged('description')) {
            return $maintenance->description;
        }

        $previousDescription = $maintenance->getPrevious()['description'] ?? null;

        return is_string($previousDescription) ? $previousDescription : null;
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
            $lockedMaintenance = MaintenanceRequest::where('id', $maintenance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = $lockedMaintenance->status;

            $targetStatus = match ($newStatus) {
                'pending', 'open' => 'pending',
                'in_progress' => 'in_progress',
                'completed', 'closed' => 'completed',
                default => throw new \InvalidArgumentException("حالة غير صالحة: {$newStatus}"),
            };

            match ($targetStatus) {
                'completed' => $this->complete($lockedMaintenance),
                'in_progress' => $this->start($lockedMaintenance),
                'pending' => $this->reopen($lockedMaintenance),
            };

            activity('maintenance')
                ->performedOn($lockedMaintenance)
                ->causedBy($this->causer($userId))
                ->withProperties([
                    'workspace_id' => $lockedMaintenance->workspace_id,
                    'from_status' => $previousStatus,
                    'to_status' => $targetStatus,
                    'started_at' => $lockedMaintenance->started_at?->toIso8601String(),
                    'resolved_at' => $lockedMaintenance->resolved_at?->toIso8601String(),
                ])
                ->log(match ($targetStatus) {
                    'in_progress' => 'تم بدء العمل في طلب الصيانة',
                    'completed' => 'تم إنهاء وإكمال طلب الصيانة بنجاح',
                    default => 'تم تعديل حالة طلب الصيانة إلى قيد الانتظار',
                });

            return $lockedMaintenance->refresh()->load('expense');
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
     *
     * @param  array<string, mixed>  $data
     */
    public function updateExpense(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            $lockedExpense = Expense::where('id', $expense->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Strip protected system fields that clients must never mass-assign
            unset($data['workspace_id'], $data['payment_status'], $data['paid_at'], $data['recorded_by']);

            $isMaintenanceExpense = $lockedExpense->origin === ExpenseOrigin::Maintenance->value
                || $lockedExpense->maintenance_request_id !== null;

            if ($isMaintenanceExpense) {
                $data = $this->maintenanceExpenseChanges($lockedExpense, $data);
            } else {
                // Manual expenses cannot be linked to maintenance via generic update
                unset($data['maintenance_request_id']);
            }

            $lockedExpense->update($data);

            return $lockedExpense->refresh();
        });
    }

    /**
     * A maintenance expense keeps its location and link; entering a positive amount makes it ready
     * (or recorded once the work is completed), clearing it puts it back to awaiting an amount.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function maintenanceExpenseChanges(Expense $expense, array $data): array
    {
        unset($data['property_id'], $data['unit_id'], $data['maintenance_request_id'], $data['origin']);

        $hasAmount = array_key_exists('amount', $data)
            && $data['amount'] !== null
            && (float) $data['amount'] > 0;

        if (! $hasAmount) {
            return [...$data, 'status' => ExpenseStatus::PendingAmount->value, 'amount' => null];
        }

        $linkedMaintenance = $expense->relationLoaded('maintenanceRequest')
            ? $expense->maintenanceRequest
            : $expense->maintenanceRequest()->first();

        if ($linkedMaintenance !== null) {
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

        return [
            ...$data,
            'status' => $linkedMaintenance?->isCompleted() ? ExpenseStatus::Recorded->value : ExpenseStatus::Ready->value,
        ];
    }

    /**
     * Completion requires the linked expense to carry a recorded cost; the expense becomes
     * "recorded" but is deliberately not marked as paid.
     *
     * @throws \DomainException
     */
    private function complete(MaintenanceRequest $maintenance): void
    {
        $expense = Expense::where('maintenance_request_id', $maintenance->id)
            ->lockForUpdate()
            ->first();

        $isLinkedCorrectly = $expense !== null
            && (int) $expense->workspace_id === (int) $maintenance->workspace_id
            && (int) $expense->maintenance_request_id === (int) $maintenance->id;

        if (! $isLinkedCorrectly) {
            throw new \DomainException('بند المصروف المرتبط بطلب الصيانة مفقود أو غير مرتبط بصورة صحيحة.');
        }

        if ($expense->amount === null || (float) $expense->amount <= 0) {
            throw new \DomainException('يجب تسجيل تكلفة الصيانة أولًا من صفحة المصروفات قبل إنهاء الطلب.');
        }

        $expense->update(['status' => ExpenseStatus::Recorded->value]);

        $maintenance->update([
            'status' => 'completed',
            'resolved_at' => now(),
        ]);
    }

    private function start(MaintenanceRequest $maintenance): void
    {
        $maintenance->update([
            'status' => 'in_progress',
            ...($maintenance->started_at ? [] : ['started_at' => now()]),
        ]);
    }

    private function reopen(MaintenanceRequest $maintenance): void
    {
        $maintenance->update([
            'status' => 'pending',
            'resolved_at' => null,
        ]);

        $expense = Expense::where('maintenance_request_id', $maintenance->id)->first();
        $expense?->update([
            'status' => ($expense->amount !== null && (float) $expense->amount > 0)
                ? ExpenseStatus::Ready->value
                : ExpenseStatus::PendingAmount->value,
        ]);
    }

    private function createPendingExpense(MaintenanceRequest $maintenance, int|string|null $recordedBy, string $expenseDate): Expense
    {
        return Expense::create([
            'workspace_id' => $maintenance->workspace_id,
            'property_id' => $maintenance->property_id,
            'unit_id' => $maintenance->unit_id,
            'maintenance_request_id' => $maintenance->id,
            'category' => 'صيانة وتشغيل',
            'title' => $maintenance->title,
            'amount' => null,
            'expense_date' => $expenseDate,
            'notes' => $maintenance->description,
            'origin' => ExpenseOrigin::Maintenance->value,
            'status' => ExpenseStatus::PendingAmount->value,
            'payment_status' => 'unpaid',
            'recorded_by' => $recordedBy,
        ]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertLocationBelongsToWorkspace(Workspace $workspace, mixed $propertyId, mixed $unitId): void
    {
        if (! $workspace->properties()->where('id', $propertyId)->exists()) {
            throw new \InvalidArgumentException('العقار المحدد غير صالح أو لا ينتمي لمساحة العمل الحالية.');
        }

        $unitBelongsToProperty = empty($unitId) || $workspace->units()
            ->where('id', $unitId)
            ->where('property_id', $propertyId)
            ->exists();

        if (! $unitBelongsToProperty) {
            throw new \InvalidArgumentException('الوحدة المحددة غير صالحة أو لا تنتمي للعقار المحدد.');
        }
    }

    private function causer(?int $userId): ?User
    {
        return $userId ? User::find($userId) : auth()->user();
    }
}
