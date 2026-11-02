<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiConfigurationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiConfiguration extends Model
{
    /** @use HasFactory<AiConfigurationFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public static function getValue(string $key): ?string
    {
        /** @var static|null $config */
        $config = static::query()->where('key', $key)->first();

        return $config?->value;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'is_active' => true,
            ]
        );
    }

    public static function removeKey(string $key): void
    {
        static::query()->where('key', $key)->delete();
    }
}
