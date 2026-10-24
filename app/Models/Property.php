<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Property extends Model
{
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
        'total_area',
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
        'amenities' => 'array',
        'images' => 'array',
        'floors_count' => 'integer',
        'total_area' => 'decimal:2',
        'year_built' => 'integer',
    ];

    /**
     * Get the full URL for the cover image.
     */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null,
        );
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
