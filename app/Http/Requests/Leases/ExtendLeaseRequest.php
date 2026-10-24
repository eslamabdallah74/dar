<?php

declare(strict_types=1);

namespace App\Http\Requests\Leases;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExtendLeaseRequest extends FormRequest
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
            'ends_on' => ['required', 'date'],
            'rent_amount' => ['nullable', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'ends_on.required' => 'تاريخ انتهاء العقد الجديد مطلوب.',
            'ends_on.date' => 'صيغة تاريخ التمديد غير صالحة.',
            'rent_amount.min' => 'يجب ألا يقل مبلغ الإيجار عن 1 ج.م.',
        ];
    }
}
