<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\RenterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Renter extends Model
{
    /** @use HasFactory<RenterFactory> */
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'phone',
        'email',
        'national_id',
        'gender',
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
            'gender' => Gender::class,
            'whatsapp_opt_in_at' => 'datetime',
            'whatsapp_opted_out_at' => 'datetime',
        ];
    }

    public function getGenderLabelAttribute(): ?string
    {
        return $this->gender?->label();
    }

    public function isMale(): bool
    {
        return $this->gender === Gender::Male;
    }

    public function isFemale(): bool
    {
        return $this->gender === Gender::Female;
    }

    public function getIdCardFrontUrlAttribute(): ?string
    {
        return $this->id_card_front ? route('renters.id-card', [$this->id, 'front']) : null;
    }

    public function getIdCardBackUrlAttribute(): ?string
    {
        return $this->id_card_back ? route('renters.id-card', [$this->id, 'back']) : null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function whatsappOptInRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'whatsapp_opt_in_recorded_by');
    }

    /**
     * @return HasMany<RenterDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RenterDocument::class);
    }

    /**
     * @return HasMany<Lease, $this>
     */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<MessageLog, $this>
     */
    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function hasConsentedToWhatsApp(): bool
    {
        return $this->whatsapp_opt_in_at !== null && $this->whatsapp_opted_out_at === null;
    }
}
