<?php

namespace App\Models;

use App\Enums\SecurityDepositStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\LeaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lease extends Model
{
    /** @use HasFactory<LeaseFactory> */
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

    /**
     * Display number of the contract shown to users (not stored).
     */
    public function getContractNumberAttribute(): string
    {
        return 'C-02'.($this->id + 4);
    }

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

        static::creating(function (Lease $lease) {
            if (empty($lease->billing_anchor_day) && ! empty($lease->starts_on)) {
                $lease->billing_anchor_day = max(1, $lease->starts_on->day);
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

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Renter, $this>
     */
    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    /**
     * @return BelongsTo<Lease, $this>
     */
    public function renewedFromLease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'renewed_from_lease_id');
    }

    /**
     * @return HasOne<Lease, $this>
     */
    public function renewalLease(): HasOne
    {
        return $this->hasOne(Lease::class, 'renewed_from_lease_id');
    }

    /**
     * @return HasMany<RentCharge, $this>
     */
    public function rentCharges(): HasMany
    {
        return $this->hasMany(RentCharge::class);
    }

    /**
     * @return HasMany<MessageLog, $this>
     */
    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    /**
     * @return HasMany<SecurityDepositPayment, $this>
     */
    public function securityDepositPayments(): HasMany
    {
        return $this->hasMany(SecurityDepositPayment::class);
    }

    /**
     * @return HasOne<SecurityDepositSettlement, $this>
     */
    public function securityDepositSettlement(): HasOne
    {
        return $this->hasOne(SecurityDepositSettlement::class);
    }

    /**
     * @return HasMany<SecurityDepositDeduction, $this>
     */
    public function securityDepositDeductions(): HasMany
    {
        return $this->hasMany(SecurityDepositDeduction::class);
    }

    /**
     * @return HasMany<SecurityDepositRefund, $this>
     */
    public function securityDepositRefunds(): HasMany
    {
        return $this->hasMany(SecurityDepositRefund::class);
    }

    /**
     * A deposit was agreed in the contract or has been (partly) received anyway.
     */
    public function hasSecurityDeposit(): bool
    {
        return (float) $this->security_deposit_amount > 0 || $this->total_deposit_received > 0;
    }

    public function getTotalDepositReceivedAttribute(): float
    {
        if ($this->relationLoaded('securityDepositPayments')) {
            return round((float) $this->securityDepositPayments
                ->where('is_reversed', false)
                ->sum('amount'), 2);
        }

        return round((float) $this->securityDepositPayments()
            ->where('is_reversed', false)
            ->sum('amount'), 2);
    }

    public function getOutstandingDepositAmountAttribute(): float
    {
        $depositRequired = round((float) $this->security_deposit_amount, 2);
        if ($depositRequired <= 0) {
            return 0.0;
        }

        return max(0.0, round($depositRequired - $this->total_deposit_received, 2));
    }

    public function getTotalDepositDeductionsAttribute(): float
    {
        if ($this->relationLoaded('securityDepositDeductions')) {
            return round((float) $this->securityDepositDeductions
                ->where('status', 'confirmed')
                ->sum('amount'), 2);
        }

        return round((float) $this->securityDepositDeductions()
            ->where('status', 'confirmed')
            ->sum('amount'), 2);
    }

    public function getTotalDepositRefundedAttribute(): float
    {
        if ($this->relationLoaded('securityDepositRefunds')) {
            return round((float) $this->securityDepositRefunds
                ->where('is_reversed', false)
                ->sum('amount'), 2);
        }

        return round((float) $this->securityDepositRefunds()
            ->where('is_reversed', false)
            ->sum('amount'), 2);
    }

    public function getRefundableDepositBalanceAttribute(): float
    {
        return max(0.0, round($this->total_deposit_received - $this->total_deposit_deductions - $this->total_deposit_refunded, 2));
    }

    public function getIsPastEndDateAttribute(): bool
    {
        return $this->status === 'active' && $this->ends_on->lt(today());
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->status === 'active'
            && $this->ends_on->gte(today())
            && $this->ends_on->lte(today()->addDays(30));
    }

    public function activeStatusLabel(): string
    {
        if ($this->is_past_end_date) {
            return 'انتهى موعده (يتطلب إجراء)';
        }

        if ($this->is_expiring_soon) {
            return 'ينتهي قريباً';
        }

        return 'سارٍ';
    }

    public function syncSecurityDepositStatus(): void
    {
        $newStatus = $this->determineSecurityDepositStatus();

        if ($this->security_deposit_status !== $newStatus) {
            $this->updateQuietly(['security_deposit_status' => $newStatus]);
        }
    }

    private function determineSecurityDepositStatus(): string
    {
        $this->unsetRelation('securityDepositSettlement');
        $settlement = $this->securityDepositSettlement;

        if ($settlement?->status === SecurityDepositStatus::Settled->value) {
            return SecurityDepositStatus::Settled->value;
        }

        $depositRequired = round((float) $this->security_deposit_amount, 2);
        if ($depositRequired <= 0) {
            return SecurityDepositStatus::NotRequired->value;
        }

        if ($settlement?->status === SecurityDepositStatus::UnderReview->value || in_array($this->status, ['expired', 'terminated'], true)) {
            return SecurityDepositStatus::UnderReview->value;
        }

        $totalReceived = round($this->total_deposit_received, 2);
        if ($totalReceived <= 0) {
            return SecurityDepositStatus::Due->value;
        }

        if ($totalReceived < $depositRequired) {
            return SecurityDepositStatus::PartiallyReceived->value;
        }

        return SecurityDepositStatus::Held->value;
    }
}
