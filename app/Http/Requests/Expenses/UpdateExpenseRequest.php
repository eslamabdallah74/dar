<?php

declare(strict_types=1);

namespace App\Http\Requests\Expenses;

use App\Models\Expense;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
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
        $expense = $this->route('expense');
        $propertyId = $this->input('property_id') ?? ($expense instanceof Expense ? $expense->property_id : null);

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
            'category' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'title' => ['nullable', 'string', 'min:3', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0.5', 'max:100000000'],
            'expense_date' => ['sometimes', 'required', 'date'],
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
            'amount.min' => 'يجب ألا تقل قيمة المصروف عن 0.5 ج.م.',
            'expense_date.required' => 'يرجى تحديد تاريخ المصروف.',
        ];
    }
}
