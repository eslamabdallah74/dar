<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Renter extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'phone',
        'email',
        'national_id',
        'whatsapp_opt_in_at',
        'whatsapp_opt_in_source',
        'whatsapp_opt_in_recorded_by',
        'whatsapp_opted_out_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_opt_in_at' => 'datetime',
            'whatsapp_opted_out_at' => 'datetime',
        ];
    }

    public function whatsappOptInRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'whatsapp_opt_in_recorded_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RenterDocument::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function hasConsentedToWhatsApp(): bool
    {
        return $this->whatsapp_opt_in_at !== null && $this->whatsapp_opted_out_at === null;
    }
}
