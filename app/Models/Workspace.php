<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',
        'currency',
        'cr_number',
        'tax_number',
        'phone',
        'address',
        'timezone',
        'status',
        'rent_reminder_days_before',
        'auto_whatsapp_reminders',
        'auto_instant_receipts',
        'overdue_warning_days_after',
        'weekly_summary_report',
    ];

    protected function casts(): array
    {
        return [
            'rent_reminder_days_before' => 'integer',
            'overdue_warning_days_after' => 'integer',
            'auto_whatsapp_reminders' => 'boolean',
            'auto_instant_receipts' => 'boolean',
            'weekly_summary_report' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<WorkspaceSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(WorkspaceSubscription::class);
    }

    /**
     * @return HasOne<WorkspaceSubscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(WorkspaceSubscription::class)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latestOfMany();
    }

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * @return HasMany<Renter, $this>
     */
    public function renters(): HasMany
    {
        return $this->hasMany(Renter::class);
    }

    /**
     * @return HasMany<Lease, $this>
     */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /**
     * @return HasMany<RentCharge, $this>
     */
    public function rentCharges(): HasMany
    {
        return $this->hasMany(RentCharge::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return HasMany<MaintenanceRequest, $this>
     */
    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
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
     * @return HasMany<SecurityDepositSettlement, $this>
     */
    public function securityDepositSettlements(): HasMany
    {
        return $this->hasMany(SecurityDepositSettlement::class);
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
     * Display symbol of the workspace currency; unknown currencies are shown by their code.
     */
    public function currencySymbol(): string
    {
        $currency = $this->currency ?? 'EGP';

        return match (strtoupper($currency)) {
            'EGP' => 'ج.م',
            'SAR' => 'ر.س',
            'AED' => 'د.إ',
            'USD' => '$',
            'EUR' => '€',
            'KWD' => 'د.ك',
            default => $currency,
        };
    }
}
