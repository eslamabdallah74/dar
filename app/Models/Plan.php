<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'monthly_price',
        'unit_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'unit_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<WorkspaceSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(WorkspaceSubscription::class);
    }
}
