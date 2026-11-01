<?php

namespace App\Models;

use App\Enums\PropertyType;
use App\Models\Concerns\BelongsToWorkspace;
use App\Services\UnitHierarchyService;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'code',
        'property_type',
        'address',
        'city',
        'district',
        'street',
        'building_number',
        'postal_code',
        'google_maps_url',
        'floors_count',
        'floor_number',
        'total_area',
        'commercial_activity',
        'year_built',
        'finishing_type',
        'amenities',
        'electricity_meter_number',
        'water_meter_number',
        'gas_meter_number',
        'guard_name',
        'guard_phone',
        'deed_number',
        'notes',
        'cover_image',
        'images',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'property_type' => PropertyType::class,
        'amenities' => 'array',
        'images' => 'array',
        'floors_count' => 'integer',
        'total_area' => 'decimal:2',
        'year_built' => 'integer',
    ];

    /**
     * Get the full URL for the cover image.
     *
     * @return Attribute<string|null, never>
     */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null,
        );
    }

    public function isBuilding(): bool
    {
        return $this->property_type === PropertyType::Building;
    }

    public function isApartment(): bool
    {
        return $this->property_type === PropertyType::Apartment;
    }

    public function isShop(): bool
    {
        return $this->property_type === PropertyType::Shop;
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->property_type->label();
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * @return HasMany<Lease, $this>
     */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /**
     * @return HasMany<MaintenanceRequest, $this>
     */
    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    /**
     * Get the gender classification info if this property is an apartment or has apartment units.
     *
     * @return array{
     *     status: 'male'|'female'|'mixed'|'unspecified',
     *     label: string,
     *     short_label: string,
     *     description: string,
     *     badge_class: string,
     *     male_count: int,
     *     female_count: int,
     *     unspecified_count: int,
     *     total_active_renters: int
     * }
     */
    public function getApartmentGenderInfo(): array
    {
        $activeRenters = Lease::query()
            ->whereHas('unit', fn ($q) => $q->where('property_id', $this->id))
            ->where('status', 'active')
            ->where('starts_on', '<=', today())
            ->where('ends_on', '>=', today())
            ->with('renter')
            ->get()
            ->pluck('renter')
            ->filter();

        return app(UnitHierarchyService::class)->computeApartmentGender($activeRenters);
    }
}
