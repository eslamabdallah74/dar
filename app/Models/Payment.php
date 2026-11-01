<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Activity;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'renter_id',
        'recorded_by',
        'amount',
        'paid_at',
        'payment_method',
        'reference',
        'notes',
        'receipt_path',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        // Enforce Section 6: Deleting financial transactions permanently is not an acceptable correction strategy.
        static::deleting(function (Payment $payment) {
            throw new \RuntimeException('الحذف الدائم لسندات القبض والمعاملات المالية غير مسموح به نظامياً. يرجى استخدام آلية التصحيح المالي والرقابي.');
        });
    }

    /**
     * @return BelongsTo<Renter, $this>
     */
    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return MorphMany<Activity, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest();
    }

    public function getLeaseAttribute(): ?Lease
    {
        return $this->allocations->first()?->rentCharge->lease
            ?? $this->renter?->leases()->where('status', 'active')->first()
            ?? $this->renter?->leases()->latest()->first();
    }

    public function getAllocatedAmountAttribute(): float
    {
        if ($this->relationLoaded('allocations')) {
            return (float) $this->allocations->sum('amount');
        }

        return (float) $this->allocations()->sum('amount');
    }

    public function getUnallocatedAmountAttribute(): float
    {
        return max(0, round((float) $this->amount - $this->allocated_amount, 2));
    }
}
