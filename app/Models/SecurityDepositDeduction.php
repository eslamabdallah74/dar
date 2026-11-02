<?php

namespace App\Models;

use App\Enums\SecurityDepositDeductionCategory;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\SecurityDepositDeductionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityDepositDeduction extends Model
{
    /** @use HasFactory<SecurityDepositDeductionFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'lease_id',
        'settlement_id',
        'recorded_by',
        'amount',
        'category',
        'description',
        'notes',
        'attachment_path',
        'status',
        'deducted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'deducted_at' => 'datetime',
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
     * @return BelongsTo<SecurityDepositSettlement, $this>
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SecurityDepositSettlement::class, 'settlement_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return SecurityDepositDeductionCategory::tryFrom($this->category)?->label() ?? $this->category;
    }

    public function settlesRentArrears(): bool
    {
        return SecurityDepositDeductionCategory::tryFrom($this->category)?->settlesRentArrears() ?? false;
    }
}
