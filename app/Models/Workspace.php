<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workspace extends Model
{
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(WorkspaceSubscription::class);
    }

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

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function renters(): HasMany
    {
        return $this->hasMany(Renter::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function rentCharges(): HasMany
    {
        return $this->hasMany(RentCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function securityDepositPayments(): HasMany
    {
        return $this->hasMany(SecurityDepositPayment::class);
    }

    public function securityDepositSettlements(): HasMany
    {
        return $this->hasMany(SecurityDepositSettlement::class);
    }

    public function securityDepositDeductions(): HasMany
    {
        return $this->hasMany(SecurityDepositDeduction::class);
    }

    public function securityDepositRefunds(): HasMany
    {
        return $this->hasMany(SecurityDepositRefund::class);
    }
}
