<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Unit extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'property_id',
        'parent_unit_id',
        'unit_type',
        'code',
        'name',
        'is_rentable',
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

    public function isApartment(): bool
    {
        return $this->unit_type === 'apartment';
    }

    public function isRoom(): bool
    {
        return $this->unit_type === 'room';
    }

    public function isBed(): bool
    {
        return $this->unit_type === 'bed';
    }

    public function isShop(): bool
    {
        return $this->unit_type === 'shop';
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->unit_type) {
            'apartment' => 'شقة',
            'room' => 'أوضة',
            'bed' => 'سرير',
            'shop' => 'محل',
            default => 'وحدة',
        };
    }

    public function allowsChildren(): bool
    {
        return in_array($this->unit_type, ['apartment', 'room'], true);
    }

    public function allowedChildType(): ?string
    {
        return match ($this->unit_type) {
            'apartment' => 'room',
            'room' => 'bed',
            default => null,
        };
    }

    /**
     * Get all ancestor IDs of this unit up to the root parent.
     *
     * @return array<int>
     */
    public function getAncestorIds(): array
    {
        $ancestorIds = [];
        $currentParentId = $this->parent_unit_id;

        while ($currentParentId !== null) {
            if (in_array((int) $currentParentId, $ancestorIds, true)) {
                break; // Prevent infinite cycle
            }
            $ancestorIds[] = (int) $currentParentId;
            $parent = static::withoutGlobalScopes()->select(['id', 'parent_unit_id'])->find($currentParentId);
            $currentParentId = $parent?->parent_unit_id;
        }

        return $ancestorIds;
    }

    /**
     * Get all descendant IDs of this unit at any depth.
     *
     * @return array<int>
     */
    public function getDescendantIds(): array
    {
        $results = DB::select('
            WITH RECURSIVE unit_descendants AS (
                SELECT id, parent_unit_id
                FROM units
                WHERE parent_unit_id = ?

                UNION ALL

                SELECT u.id, u.parent_unit_id
                FROM units u
                JOIN unit_descendants ud ON u.parent_unit_id = ud.id
            )
            SELECT id FROM unit_descendants
        ', [$this->id]);

        return array_map(fn ($r) => (int) $r->id, $results);
    }

    /**
     * Get all units in the hierarchy of this unit: self, all ancestors, all descendants.
     *
     * @return array<int>
     */
    public function getHierarchyUnitIds(): array
    {
        return array_values(array_unique(array_merge(
            [(int) $this->id],
            $this->getAncestorIds(),
            $this->getDescendantIds()
        )));
    }

    /**
     * Billable units query:
     * A unit counts as billable when:
     * 1. is_rentable = true
     * 2. It has no rentable descendants at any depth.
     *
     * Vacant units still count; properties never count as billable units.
     */
    public function scopeBillableLeaves(Builder $query): Builder
    {
        return $query->where('is_rentable', true)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw(1)
                    ->fromRaw('(
                        WITH RECURSIVE unit_descendants AS (
                            SELECT id, parent_unit_id, is_rentable, parent_unit_id AS ancestor_id
                            FROM units
                            WHERE parent_unit_id IS NOT NULL

                            UNION ALL

                            SELECT u.id, u.parent_unit_id, u.is_rentable, ud.ancestor_id
                            FROM units u
                            JOIN unit_descendants ud ON u.parent_unit_id = ud.id
                        )
                        SELECT ancestor_id, is_rentable FROM unit_descendants
                    ) AS ud')
                    ->whereColumn('ud.ancestor_id', 'units.id')
                    ->where('ud.is_rentable', 1);
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
