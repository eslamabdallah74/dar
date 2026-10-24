<?php

declare(strict_types=1);

namespace App\Http\Requests\SecurityDeposits;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSecurityDepositDeductionRequest extends FormRequest
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
            'category' => ['required', 'in:furniture_damage,fixture_damage,unpaid_dues,missing_items,other_damage'],
            'description' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf,webp', 'max:10240'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'deducted_at' => ['nullable', 'date'],
        ];
    }
}
