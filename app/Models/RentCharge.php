<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\RentChargeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentCharge extends Model
{
    /** @use HasFactory<RentChargeFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'lease_id',
        'period_start',
        'period_end',
        'due_date',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Lease, $this>
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return HasMany<MessageLog, $this>
     */
    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function getPaidAmountAttribute(): float
    {
        if ($this->relationLoaded('allocations')) {
            return (float) $this->allocations->sum('amount');
        }

        return (float) $this->allocations()->sum('amount');
    }

    /**
     * Arabic month and year of the billed period, e.g. "أكتوبر 2026".
     */
    public function getPeriodNameAttribute(): string
    {
        // settings() is Carbon's typed equivalent of locale(); it applies the locale to this instance only.
        return $this->period_start->settings(['locale' => 'ar'])->translatedFormat('F Y');
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return max(0, round((float) $this->amount - $this->paid_amount, 2));
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->outstanding_balance <= 0.00;
    }

    public function getIsOverdueAttribute(): bool
    {
        return ! $this->is_fully_paid && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_fully_paid) {
            return 'paid';
        }

        if ($this->paid_amount > 0) {
            return 'partially_paid';
        }

        if ($this->is_overdue) {
            return 'overdue';
        }

        return 'pending';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'مسدد بالكامل',
            'partially_paid' => 'مدفوع جزئياً',
            'overdue' => 'متأخر',
            'unpaid', 'pending' => 'غير مسدد',
            default => 'غير مسدد',
        };
    }

    /**
     * @param  Builder<RentCharge>  $query
     * @return Builder<RentCharge>
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereRaw('amount > (SELECT COALESCE(SUM(amount), 0) FROM payment_allocations WHERE payment_allocations.rent_charge_id = rent_charges.id)');
    }

    /**
     * @param  Builder<RentCharge>  $query
     * @return Builder<RentCharge>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->where('due_date', '<', today());
    }
}
