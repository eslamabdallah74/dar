<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Models\RentCharge;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
        $workspaceId = app(CurrentWorkspace::class)->id();

        return [
            'renter_id' => [
                'required',
                Rule::exists('renters', 'id')->where(fn ($query) => $workspaceId ? $query->where('workspace_id', $workspaceId) : $query),
            ],
            'amount' => ['required', 'numeric', 'min:0.5', 'max:100000000'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cheque,other'],
            'paid_at' => ['nullable', 'date'],
            'record_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'receipt_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
            'rent_charge_id' => [
                'nullable',
                Rule::exists('rent_charges', 'id')->where(fn ($query) => $workspaceId ? $query->where('workspace_id', $workspaceId) : $query),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! empty($value) && $this->filled('renter_id')) {
                        $charge = RentCharge::withoutGlobalScopes()->with('lease')->find($value);
                        if ($charge && (int) $charge->lease?->renter_id !== (int) $this->input('renter_id')) {
                            $fail('بند الاستحقاق المحدد لا ينتمي إلى عقود المستأجر المختار.');
                        }
                    }
                },
            ],
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
            'renter_id.required' => 'يرجى تحديد المستأجر.',
            'renter_id.exists' => 'المستأجر المحدد غير موجود.',
            'amount.required' => 'يرجى إدخال مبلغ الدفعة.',
            'amount.min' => 'يجب ألا يقل مبلغ الدفعة عن 0.5 ج.م.',
            'amount.max' => 'مبلغ الدفعة المدخل يتجاوز الحد المسموح به.',
            'payment_method.required' => 'يرجى اختيار طريقة السداد.',
            'payment_method.in' => 'طريقة السداد المحددة غير صالحة.',
            'paid_at.date' => 'تاريخ السداد غير صالح.',
            'record_date.date' => 'تاريخ القيد والتسجيل غير صالح.',
            'receipt_image.mimes' => 'صيغة صورة إيصال التحويل غير مدعومة. الصيغ المدعومة: JPG, PNG, WEBP, PDF.',
            'receipt_image.max' => 'حجم ملف الإيصال لا يجب أن يتجاوز 10 ميجابايت.',
        ];
    }
}
