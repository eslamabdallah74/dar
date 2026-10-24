<?php

declare(strict_types=1);

namespace App\Http\Requests\Leases;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'exists:units,id'],
            'renter_id' => ['required', 'exists:renters,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'rent_amount' => ['required', 'numeric', 'min:1'],
            'security_deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'billing_interval' => ['nullable', 'in:monthly'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
