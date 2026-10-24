<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityDepositSettlement extends Model
{
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

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function settledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(SecurityDepositDeduction::class, 'settlement_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SecurityDepositRefund::class, 'settlement_id');
    }

    public function getSettlementTypeLabelAttribute(): ?string
    {
        return match ($this->settlement_type) {
            'full_refund' => 'رد المبلغ بالكامل',
            'partial_refund' => 'رد جزء من المبلغ',
            'no_refund' => 'عدم رد المبلغ',
            default => null,
        };
    }

    public function getRefundStatusLabelAttribute(): string
    {
        return match ($this->refund_status) {
            'pending' => 'لم يُرد بعد',
            'refunded' => 'تم الرد',
            default => 'لا ينطبق',
        };
    }
}
