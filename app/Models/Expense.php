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
        'applied_at',
        'applied_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'applied_at' => 'datetime',
        ];
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

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /**
     * Scope for applied expenses only (financially active).
     */
    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('status', 'applied');
    }

    /**
     * Scope for pending expenses (either waiting for amount or waiting to be applied).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending_amount', 'ready_to_apply']);
    }

    /**
     * Scope by origin.
     */
    public function scopeOrigin(Builder $query, string $origin): Builder
    {
        return $query->where('origin', $origin);
    }

    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }

    public function isPendingAmount(): bool
    {
        return $this->status === 'pending_amount';
    }

    public function isReadyToApply(): bool
    {
        return $this->status === 'ready_to_apply';
    }

    public function isMaintenance(): bool
    {
        return $this->origin === 'maintenance';
    }
}
