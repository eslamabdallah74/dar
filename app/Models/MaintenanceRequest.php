<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Activity;

class MaintenanceRequest extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'property_id',
        'unit_id',
        'reported_by_renter_id',
        'title',
        'description',
        'status',
        'reported_at',
        'started_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MaintenanceRequest $maintenance) {
            if (empty($maintenance->reported_at)) {
                $maintenance->reported_at = now();
            }
            if (! isset($maintenance->description)) {
                $maintenance->description = '';
            }
        });

        static::deleting(function (MaintenanceRequest $maintenance) {
            $expense = $maintenance->expense()->first();
            if ($expense && $expense->amount !== null && (float) $expense->amount > 0) {
                throw new \DomainException('لا يمكن حذف طلب صيانة مسجل له تكلفة مالية أو مدفوعات لحماية النزاهة المحاسبية.');
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

    public function reportedByRenter(): BelongsTo
    {
        return $this->belongsTo(Renter::class, 'reported_by_renter_id');
    }

    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MaintenanceAttachment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(MaintenanceNote::class)->latest('id');
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'open'], true);
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'closed'], true);
    }

    public function isCommonArea(): bool
    {
        return $this->unit_id === null;
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * Determine if the linked expense has a valid amount greater than zero.
     * Rejects missing expenses, null amounts, zero amounts, negative amounts, or mismatched relations.
     */
    public function hasValidExpenseAmount(): bool
    {
        $expense = $this->expense()->first();

        if ($expense === null) {
            return false;
        }

        if ((int) $expense->maintenance_request_id !== (int) $this->id) {
            return false;
        }

        if ((int) $expense->workspace_id !== (int) $this->workspace_id) {
            return false;
        }

        if ($expense->amount === null) {
            return false;
        }

        return (float) $expense->amount > 0;
    }

    /**
     * Determine if this maintenance request is eligible to be marked as completed.
     */
    public function canBeCompleted(): bool
    {
        return $this->hasValidExpenseAmount();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending', 'open' => 'قيد الانتظار',
            'in_progress' => 'قيد التنفيذ',
            'completed', 'closed' => 'مكتمل',
            default => $this->status,
        };
    }
}
