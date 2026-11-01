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
        'parent_unit_limit',
        'active_rental_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'unit_limit' => 'integer',
            'parent_unit_limit' => 'integer',
            'active_rental_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Plan $plan): void {
            if ($plan->wasChanged(['parent_unit_limit', 'active_rental_limit', 'unit_limit'])) {
                $parentLimit = $plan->parent_unit_limit ?? $plan->unit_limit;
                $activeLimit = $plan->active_rental_limit ?? $plan->unit_limit;

                foreach ($plan->subscriptions()->where('status', 'active')->with('workspace')->get() as $sub) {
                    if ($sub->workspace) {
                        $sub->workspace->update([
                            'parent_unit_limit' => $parentLimit,
                            'active_rental_limit' => $activeLimit,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * @return HasMany<WorkspaceSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(WorkspaceSubscription::class);
    }
}
