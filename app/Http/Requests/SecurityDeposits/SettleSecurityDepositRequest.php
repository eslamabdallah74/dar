<?php

declare(strict_types=1);

namespace App\Http\Requests\SecurityDeposits;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SettleSecurityDepositRequest extends FormRequest
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
            'inspection_category' => ['required', 'in:no_issues,furniture_damage,fixture_damage,missing_items,other_damage'],
            'inspection_notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
