<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Unit extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'property_id',
        'parent_unit_id',
        'name',
        'unit_type',
        'is_rentable',
        'floor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_rentable' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function parentUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'parent_unit_id');
    }

    public function childUnits(): HasMany
    {
        return $this->hasMany(Unit::class, 'parent_unit_id');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function currentLease(): HasOne
    {
        return $this->hasOne(Lease::class)
            ->where('status', 'active')
            ->where('starts_on', '<=', today())
            ->where('ends_on', '>=', today())
            ->latestOfMany();
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Billable units query (Decision 1):
     * Count independently rentable units that do not have rentable child units.
     * Vacant units still count; occupied units count once.
     */
    public function scopeBillableLeaves(Builder $query): Builder
    {
        return $query->where('is_rentable', true)
            ->whereDoesntHave('childUnits', function (Builder $q) {
                $q->where('is_rentable', true);
            });
    }

    /**
     * Helper to get the total billable leaf unit count for a given workspace.
     */
    public static function billableCountForWorkspace(int $workspaceId): int
    {
        return static::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->billableLeaves()
            ->count();
    }
}
