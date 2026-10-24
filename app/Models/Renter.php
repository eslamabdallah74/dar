<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Renter extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'phone',
        'email',
        'national_id',
        'id_card_front',
        'id_card_back',
        'whatsapp_opt_in_at',
        'whatsapp_opt_in_source',
        'whatsapp_opt_in_recorded_by',
        'whatsapp_opted_out_at',
        'notes',
    ];

    protected $appends = [
        'id_card_front_url',
        'id_card_back_url',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_opt_in_at' => 'datetime',
            'whatsapp_opted_out_at' => 'datetime',
        ];
    }

    public function getIdCardFrontUrlAttribute(): ?string
    {
        return $this->id_card_front ? Storage::disk('public')->url($this->id_card_front) : null;
    }

    public function getIdCardBackUrlAttribute(): ?string
    {
        return $this->id_card_back ? Storage::disk('public')->url($this->id_card_back) : null;
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
