<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class EgyptianPhoneNumber implements ValidationRule
{
    /**
     * Regex pattern matching Egyptian mobile numbers:
     * - Local format: 010xxxxxxxx, 011xxxxxxxx, 012xxxxxxxx, 015xxxxxxxx (11 digits)
     * - International format: +2010..., 002010..., 2010..., or with optional leading 0 (+20010...)
     */
    public const PATTERN = '/^(?:\+20|0020|20)?0?1[0125][0-9]{8}$/';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! static::isValid($value)) {
            $fail('يجب أن يكون :attribute رقم هاتف مصري صحيح (مثال: 01012345678 أو +201012345678).');
        }
    }

    /**
     * Check if the provided value is a valid Egyptian mobile phone number.
     */
    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        $cleaned = preg_replace('/[\s\-\(\)\.]/', '', (string) $value);

        return (bool) preg_match(self::PATTERN, $cleaned);
    }

    /**
     * Normalize Egyptian phone number into standard local (01xxxxxxxxx) or international (+201xxxxxxxxx) format.
     */
    public static function normalize(string $value, bool $international = false): string
    {
        $cleaned = preg_replace('/[\s\-\(\)\.]/', '', $value);

        if (! preg_match('/^(?:\+20|0020|20)?0?(1[0125][0-9]{8})$/', $cleaned, $matches)) {
            return $value;
        }

        $nationalNumber = $matches[1];

        return $international ? '+20'.$nationalNumber : '0'.$nationalNumber;
    }
}
