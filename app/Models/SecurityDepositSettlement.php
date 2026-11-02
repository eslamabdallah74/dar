<?php

namespace App\Models;

use App\Enums\SecurityDepositDeductionCategory;
use App\Enums\SecurityDepositRefundStatus;
use App\Enums\SecurityDepositSettlementType;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\SecurityDepositSettlementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityDepositSettlement extends Model
{
    /** @use HasFactory<SecurityDepositSettlementFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'lease_id',
        'settled_by',
        'status',
        'settlement_type',
        'retention_reason',
        'original_deposit',
        'inspection_category',
        'inspection_notes',
        'total_received',
        'total_deductions',
        'total_refunded',
        'refundable_balance',
        'refund_status',
        'refund_method',
        'refund_payment_date',
        'refund_reference',
        'settled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'original_deposit' => 'decimal:2',
            'total_received' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_refunded' => 'decimal:2',
            'refundable_balance' => 'decimal:2',
            'refund_payment_date' => 'date',
            'settled_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function settledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    /**
     * @return HasMany<SecurityDepositDeduction, $this>
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(SecurityDepositDeduction::class, 'settlement_id');
    }

    /**
     * @return HasMany<SecurityDepositRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(SecurityDepositRefund::class, 'settlement_id');
    }

    public function getSettlementTypeLabelAttribute(): ?string
    {
        return SecurityDepositSettlementType::tryFrom((string) $this->settlement_type)?->label();
    }

    public function getInspectionCategoryLabelAttribute(): ?string
    {
        if ($this->inspection_category === 'no_issues') {
            return 'لا توجد ملاحظات أو أضرار';
        }

        return SecurityDepositDeductionCategory::tryFrom((string) $this->inspection_category)?->label() ?? $this->inspection_category;
    }

    public function getRefundStatusLabelAttribute(): string
    {
        return (SecurityDepositRefundStatus::tryFrom((string) $this->refund_status) ?? SecurityDepositRefundStatus::NotApplicable)->label();
    }
}
