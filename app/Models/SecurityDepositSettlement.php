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
        'inspection_category',
        'inspection_notes',
        'total_received',
        'total_deductions',
        'total_refunded',
        'refundable_balance',
        'settled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_received' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_refunded' => 'decimal:2',
            'refundable_balance' => 'decimal:2',
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
}
