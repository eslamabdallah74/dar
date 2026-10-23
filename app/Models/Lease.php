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
        'billing_interval',
        'billing_anchor_day',
        'first_due_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'first_due_date' => 'date',
            'rent_amount' => 'decimal:2',
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
}
