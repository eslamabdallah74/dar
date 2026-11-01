<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\MessageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    /** @use HasFactory<MessageLogFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'renter_id',
        'lease_id',
        'rent_charge_id',
        'recipient_phone',
        'template_name',
        'scheduled_at',
        'sent_at',
        'status',
        'provider_message_id',
        'error_code',
        'error_message',
        'attempts',
        'provider_cost',
        'provider_cost_currency',
        'deduplication_key',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
            'provider_cost' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<Renter, $this>
     */
    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    /**
     * @return BelongsTo<Lease, $this>
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * @return BelongsTo<RentCharge, $this>
     */
    public function rentCharge(): BelongsTo
    {
        return $this->belongsTo(RentCharge::class);
    }
}
