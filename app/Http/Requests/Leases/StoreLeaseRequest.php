<?php

declare(strict_types=1);

namespace App\Http\Requests\Leases;

use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $workspaceId = app(CurrentWorkspace::class)->id() ?? 0;

        return [
            'unit_id' => [
                'required',
                Rule::exists('units', 'id')->where('workspace_id', $workspaceId),
            ],
            'renter_id' => [
                'required',
                Rule::exists('renters', 'id')->where('workspace_id', $workspaceId),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'rent_amount' => ['required', 'numeric', 'min:1'],
            'security_deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'billing_interval' => ['nullable', 'in:monthly'],
            'contract_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:5120'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_id.required' => 'يرجى تحديد الوحدة السكنية/التجارية.',
            'unit_id.exists' => 'الوحدة المحددة غير موجودة.',
            'renter_id.required' => 'يرجى اختيار المستأجر أو إضافته.',
            'renter_id.exists' => 'المستأجر المحدد غير موجود.',
            'starts_on.required' => 'تاريخ بداية العقد مطلوب.',
            'ends_on.required' => 'تاريخ نهاية العقد مطلوب.',
            'ends_on.after' => 'يجب أن يكون تاريخ نهاية العقد بعد تاريخ البداية.',
            'rent_amount.required' => 'يرجى تحديد القيمة الإيجارية الشهرية.',
            'rent_amount.min' => 'يجب أن تكون القيمة الإيجارية أكبر من صفر.',
            'security_deposit_amount.min' => 'يجب ألا يكون مبلغ التأمين سالباً.',
            'contract_image.file' => 'يجب أن يكون مرفق العقد ملفاً صالحاً.',
            'contract_image.mimes' => 'يجب أن يكون مرفق العقد صورة (JPEG, PNG, WEBP) أو ملف PDF.',
            'contract_image.max' => 'يجب ألا يتجاوز حجم مرفق العقد 5 ميجابايت.',
        ];
    }
}
