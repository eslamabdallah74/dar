<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityDepositDeduction extends Model
{
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

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SecurityDepositSettlement::class, 'settlement_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'furniture_damage' => 'تلف أثاث أو أجهزة',
            'fixture_damage' => 'تلف مرافق وتركيبات',
            'unpaid_dues' => 'مستحقات غير مدفوعة',
            'unpaid_rent' => 'متأخرات إيجارية مستحقة',
            'missing_items' => 'مفقودات ومحتويات ناقصة',
            'other_damage' => 'أضرار أو مطالبات أخرى',
            default => $this->category,
        };
    }
}
