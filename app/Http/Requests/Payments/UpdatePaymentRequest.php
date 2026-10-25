<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.5', 'max:100000000'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cheque,other'],
            'paid_at' => ['required', 'date'],
            'record_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
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
            'amount.required' => 'يرجى إدخال مبلغ الدفعة المعدل.',
            'amount.min' => 'يجب ألا يقل مبلغ الدفعة عن 0.5 ج.م.',
            'amount.max' => 'مبلغ الدفعة المدخل يتجاوز الحد المسموح به.',
            'payment_method.required' => 'يرجى اختيار طريقة السداد.',
            'payment_method.in' => 'طريقة السداد المحددة غير صالحة.',
            'paid_at.required' => 'تاريخ السداد مطلوب.',
            'paid_at.date' => 'تاريخ السداد غير صالح.',
            'record_date.date' => 'تاريخ القيد والتسجيل غير صالح.',
            'reason.required' => 'يرجى توضيح سبب تصحيح الدفعة المالية لتوثيق السجل الرقابي.',
            'reason.min' => 'يجب أن يكون سبب التصحيح 3 أحرف على الأقل.',
        ];
    }
}
