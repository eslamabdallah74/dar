<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
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
            if (empty($expense->recorded_by)) {
                $expense->recorded_by = auth()->id() ?? $expense->workspace?->owner_user_id;
            }

            if (($expense->origin === 'manual' || empty($expense->origin)) && empty($expense->payment_status)) {
                $expense->payment_status = 'paid';
                $expense->paid_at ??= now();
            }
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

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Scope for recorded expenses with valid amounts (financially active).
     */
    public function scopeRecorded(Builder $query): Builder
    {
        return $query->whereNotNull('amount')->where('amount', '>', 0);
    }

    /**
     * Alias for recorded expenses (financially active).
     */
    public function scopeApplied(Builder $query): Builder
    {
        return $this->scopeRecorded($query);
    }

    /**
     * Scope for pending expenses awaiting amount.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('amount')
                ->orWhere('amount', '<=', 0)
                ->orWhere('status', 'pending_amount');
        });
    }

    /**
     * Scope by origin.
     */
    public function scopeOrigin(Builder $query, string $origin): Builder
    {
        return $query->where('origin', $origin);
    }

    /**
     * Scope for paid expenses.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('payment_status', 'paid')
                ->orWhereNotNull('paid_at');
        });
    }

    /**
     * Scope for unpaid expenses.
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('payment_status', '!=', 'paid')
                ->orWhereNull('payment_status');
        })->whereNull('paid_at');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid' || $this->paid_at !== null;
    }

    public function isUnpaid(): bool
    {
        return ! $this->isPaid();
    }

    public function isRecorded(): bool
    {
        return in_array($this->status, ['recorded', 'applied'], true)
            && $this->amount !== null
            && (float) $this->amount > 0;
    }

    public function isApplied(): bool
    {
        return $this->isRecorded();
    }

    public function isReady(): bool
    {
        return in_array($this->status, ['ready', 'ready_to_apply'], true)
            && $this->amount !== null
            && (float) $this->amount > 0;
    }

    public function isReadyToApply(): bool
    {
        return $this->isReady();
    }

    public function isPendingAmount(): bool
    {
        return $this->status === 'pending_amount'
            || $this->amount === null
            || (float) $this->amount <= 0;
    }

    public function isMaintenance(): bool
    {
        return $this->origin === 'maintenance';
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->isPendingAmount()) {
            return 'بانتظار المبلغ';
        }

        return match ($this->status) {
            'ready', 'ready_to_apply' => 'جاهز',
            'recorded', 'applied' => 'تم تسجيلها',
            default => 'تم تسجيلها',
        };
    }
}
