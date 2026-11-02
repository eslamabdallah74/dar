<?php

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $parent_unit_limit
 * @property int|null $active_rental_limit
 * @property int|null $requested_plan_id
 * @property string|null $plan_request_status
 * @property Carbon|null $plan_requested_at
 * @property string|null $plan_request_notes
 * @property bool $ai_assistant_enabled
 * @property-read int|null $parent_units_count
 */
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
        'parent_unit_limit',
        'active_rental_limit',
        'requested_plan_id',
        'plan_request_status',
        'plan_requested_at',
        'plan_request_notes',
        'rent_reminder_days_before',
        'auto_whatsapp_reminders',
        'auto_instant_receipts',
        'overdue_warning_days_after',
        'weekly_summary_report',
        'ai_assistant_enabled',
    ];

    protected function casts(): array
    {
        return [
            'parent_unit_limit' => 'integer',
            'active_rental_limit' => 'integer',
            'requested_plan_id' => 'integer',
            'plan_requested_at' => 'datetime',
            'rent_reminder_days_before' => 'integer',
            'overdue_warning_days_after' => 'integer',
            'auto_whatsapp_reminders' => 'boolean',
            'auto_instant_receipts' => 'boolean',
            'weekly_summary_report' => 'boolean',
            'ai_assistant_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace): void {
            if (! array_key_exists('parent_unit_limit', $workspace->getAttributes())) {
                $defaultParent = config('rental.default_limits.parent_unit_limit', 1);
                $workspace->parent_unit_limit = is_numeric($defaultParent) ? (int) $defaultParent : 1;
            }
            if (! array_key_exists('active_rental_limit', $workspace->getAttributes())) {
                $defaultActive = config('rental.default_limits.active_rental_limit', 1);
                $workspace->active_rental_limit = is_numeric($defaultActive) ? (int) $defaultActive : 1;
            }
        });
    }

    public function isParentUnitLimitUnlimited(): bool
    {
        return $this->parent_unit_limit === null;
    }

    public function isActiveRentalLimitUnlimited(): bool
    {
        return $this->active_rental_limit === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
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
