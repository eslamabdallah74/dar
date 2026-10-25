<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PhoneNumber implements ValidationRule
{
    public function __construct(
        protected string $country = 'EG'
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        match (strtoupper($this->country)) {
            'EG' => (new EgyptianPhoneNumber)->validate($attribute, $value, $fail),
            default => (new EgyptianPhoneNumber)->validate($attribute, $value, $fail),
        };
    }

    /**
     * Static factory for Egyptian phone numbers.
     */
    public static function egyptian(): EgyptianPhoneNumber
    {
        return new EgyptianPhoneNumber;
    }
}
