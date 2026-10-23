<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentCharge extends Model
{
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

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->allocations();
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->allocations()->sum('amount');
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
        return ! $this->is_fully_paid && $this->due_date->isPast();
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereRaw('amount > (SELECT COALESCE(SUM(amount), 0) FROM payment_allocations WHERE payment_allocations.rent_charge_id = rent_charges.id)');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->where('due_date', '<', today());
    }
}
