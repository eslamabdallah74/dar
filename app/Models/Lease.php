<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lease extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'unit_id',
        'renter_id',
        'renewed_from_lease_id',
        'starts_on',
        'ends_on',
        'rent_amount',
        'security_deposit_amount',
        'security_deposit_status',
        'billing_interval',
        'billing_anchor_day',
        'first_due_date',
        'status',
        'contract_image',
        'notes',
    ];

    protected $appends = [
        'contract_image_url',
    ];

    public function getContractImageUrlAttribute(): ?string
    {
        return $this->contract_image ? route('leases.contract-image', $this) : null;
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'first_due_date' => 'date',
            'rent_amount' => 'decimal:2',
            'security_deposit_amount' => 'decimal:2',
            'billing_anchor_day' => 'integer',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($lease) {
            if (empty($lease->billing_anchor_day) && ! empty($lease->starts_on)) {
                $lease->billing_anchor_day = (int) $lease->starts_on->format('j');
            }
            if (empty($lease->first_due_date) && ! empty($lease->starts_on)) {
                $lease->first_due_date = $lease->starts_on;
            }
            if (empty($lease->security_deposit_status)) {
                $depositAmount = (float) ($lease->security_deposit_amount ?? 0);
                $lease->security_deposit_status = $depositAmount > 0 ? 'due' : 'not_required';
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    public function renewedFromLease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'renewed_from_lease_id');
    }

    public function renewalLease(): HasOne
    {
        return $this->hasOne(Lease::class, 'renewed_from_lease_id');
    }

    public function rentCharges(): HasMany
    {
        return $this->hasMany(RentCharge::class);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function securityDepositPayments(): HasMany
    {
        return $this->hasMany(SecurityDepositPayment::class);
    }

    public function securityDepositSettlement(): HasOne
    {
        return $this->hasOne(SecurityDepositSettlement::class);
    }

    public function securityDepositDeductions(): HasMany
    {
        return $this->hasMany(SecurityDepositDeduction::class);
    }

    public function securityDepositRefunds(): HasMany
    {
        return $this->hasMany(SecurityDepositRefund::class);
    }

    public function getTotalDepositReceivedAttribute(): float
    {
        if ($this->relationLoaded('securityDepositPayments')) {
            return (float) $this->securityDepositPayments
                ->where('is_reversed', false)
                ->sum('amount');
        }

        return (float) $this->securityDepositPayments()
            ->where('is_reversed', false)
            ->sum('amount');
    }

    public function getOutstandingDepositAmountAttribute(): float
    {
        $depositRequired = (float) $this->security_deposit_amount;
        if ($depositRequired <= 0) {
            return 0.0;
        }

        return max(0.0, $depositRequired - $this->total_deposit_received);
    }

    public function getTotalDepositDeductionsAttribute(): float
    {
        if ($this->relationLoaded('securityDepositDeductions')) {
            return (float) $this->securityDepositDeductions
                ->where('status', 'confirmed')
                ->sum('amount');
        }

        return (float) $this->securityDepositDeductions()
            ->where('status', 'confirmed')
            ->sum('amount');
    }

    public function getTotalDepositRefundedAttribute(): float
    {
        if ($this->relationLoaded('securityDepositRefunds')) {
            return (float) $this->securityDepositRefunds
                ->where('is_reversed', false)
                ->sum('amount');
        }

        return (float) $this->securityDepositRefunds()
            ->where('is_reversed', false)
            ->sum('amount');
    }

    public function getRefundableDepositBalanceAttribute(): float
    {
        return max(0.0, $this->total_deposit_received - $this->total_deposit_deductions - $this->total_deposit_refunded);
    }

    public function getIsPastEndDateAttribute(): bool
    {
        return $this->status === 'active' && $this->ends_on !== null && $this->ends_on->lt(today());
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->status === 'active'
            && $this->ends_on !== null
            && $this->ends_on->gte(today())
            && $this->ends_on->lte(today()->addDays(30));
    }

    public function syncSecurityDepositStatus(): void
    {
        $requiredAmount = (float) $this->security_deposit_amount;

        if ($requiredAmount <= 0) {
            $newStatus = 'not_required';
        } else {
            $this->unsetRelation('securityDepositSettlement');
            $settlement = $this->securityDepositSettlement;
            if ($settlement && $settlement->status === 'settled') {
                $newStatus = 'settled';
            } elseif (($settlement && $settlement->status === 'under_review') || in_array($this->status, ['expired', 'terminated'], true)) {
                $newStatus = 'under_review';
            } else {
                $totalReceived = $this->total_deposit_received;
                if ($totalReceived <= 0) {
                    $newStatus = 'due';
                } elseif ($totalReceived < $requiredAmount) {
                    $newStatus = 'partially_received';
                } else {
                    $newStatus = 'held';
                }
            }
        }

        if ($this->security_deposit_status !== $newStatus) {
            $this->updateQuietly(['security_deposit_status' => $newStatus]);
        }
    }
}
