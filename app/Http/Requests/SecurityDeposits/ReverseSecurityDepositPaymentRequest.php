<?php

declare(strict_types=1);

namespace App\Http\Requests\SecurityDeposits;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReverseSecurityDepositPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reversal_reason' => ['required', 'string', 'max:500'],
        ];
    }
}
