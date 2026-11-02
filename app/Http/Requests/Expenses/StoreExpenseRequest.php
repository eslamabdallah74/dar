<?php

declare(strict_types=1);

namespace App\Http\Requests\Expenses;

use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
        $workspaceId = app(CurrentWorkspace::class)->id();
        $propertyId = $this->input('property_id');

        return [
            'property_id' => [
                'nullable',
                Rule::exists('properties', 'id')->where(fn ($q) => $workspaceId ? $q->where('workspace_id', $workspaceId) : $q),
            ],
            'unit_id' => [
                'nullable',
                Rule::exists('units', 'id')->where(function ($q) use ($workspaceId, $propertyId) {
                    if ($workspaceId) {
                        $q->where('workspace_id', $workspaceId);
                    }
                    if ($propertyId) {
                        $q->where('property_id', $propertyId);
                    }
                }),
            ],
            'category' => ['required', 'string', 'min:2', 'max:100'],
            'title' => ['nullable', 'string', 'min:3', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.5', 'max:100000000'],
            'expense_date' => ['required', 'date'],
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
            'property_id.exists' => 'العقار المحدد غير موجود أو غير متاح.',
            'unit_id.exists' => 'الوحدة المحددة غير صحيحة أو لا تنتمي إلى هذا العقار.',
            'category.required' => 'يرجى تحديد بند أو تصنيف المصروف.',
            'category.min' => 'يجب ألا يقل اسم التصنيف عن حرفين.',
            'title.min' => 'يجب ألا يقل بيان المصروف عن 3 أحرف.',
            'amount.required' => 'يرجى إدخال قيمة المصروف.',
            'amount.min' => 'يجب ألا تقل قيمة المصروف عن 0.5 ج.م.',
            'expense_date.required' => 'يرجى تحديد تاريخ المصروف.',
        ];
    }
}
