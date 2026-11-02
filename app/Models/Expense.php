<?php

namespace App\Models;

use App\Enums\ExpenseOrigin;
use App\Enums\ExpenseStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'property_id',
        'unit_id',
        'maintenance_request_id',
        'recorded_by',
        'category',
        'title',
        'amount',
        'expense_date',
        'notes',
        'origin',
        'status',
        'payment_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            $expense->applyCreationDefaults();
        });

        static::deleting(function (Expense $expense) {
            if ($expense->maintenance_request_id !== null) {
                throw new \DomainException('لا يمكن حذف مصروف مرتبط بطلب صيانة نشط لمنع كسر العلاقة المالية للطلب.');
            }
        });

        static::updating(function (Expense $expense) {
            if ($expense->isDirty('maintenance_request_id') && $expense->getOriginal('maintenance_request_id') !== null) {
                throw new \DomainException('لا يمكن فك أو تغيير ربط المصروف بطلب الصيانة بعد إنشائه.');
            }

            if ($expense->isDirty('origin') && $expense->getOriginal('origin') !== null) {
                throw new \DomainException('لا يمكن تعديل مصدر المصروف بعد تسجيله.');
            }
        });
    }

    /**
     * The recorder defaults to the signed-in user (or the workspace owner), and a manual expense
     * without a payment status is recorded as already paid.
     */
    private function applyCreationDefaults(): void
    {
        if (empty($this->recorded_by)) {
            $recorderId = auth()->user()->id ?? $this->workspace?->owner_user_id;
            if ($recorderId !== null) {
                $this->recorded_by = $recorderId;
            }
        }

        if (($this->origin === 'manual' || empty($this->origin)) && empty($this->payment_status)) {
            $this->payment_status = 'paid';
            $this->paid_at ??= now();
        }
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<MaintenanceRequest, $this>
     */
    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid' || $this->paid_at !== null;
    }

    public function isRecorded(): bool
    {
        return in_array($this->status, ['recorded', 'applied'], true)
            && $this->amount !== null
            && (float) $this->amount > 0;
    }

    public function isReady(): bool
    {
        return in_array($this->status, ['ready', 'ready_to_apply'], true)
            && $this->amount !== null
            && (float) $this->amount > 0;
    }

    public function isPendingAmount(): bool
    {
        return $this->status === 'pending_amount'
            || $this->amount === null
            || (float) $this->amount <= 0;
    }

    public function getStatusLabelAttribute(): string
    {
        $status = $this->isPendingAmount() ? ExpenseStatus::PendingAmount : ExpenseStatus::fromLoose($this->status);

        return $status->label();
    }

    public function getOriginLabelAttribute(): string
    {
        return (ExpenseOrigin::tryFrom((string) $this->origin) ?? ExpenseOrigin::Manual)->label();
    }
}
