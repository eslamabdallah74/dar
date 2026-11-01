<?php

declare(strict_types=1);

namespace App\Http\Requests\SecurityDeposits;

use App\Http\Requests\Concerns\ResolvesRouteLease;
use App\Models\Lease;
use App\Models\SecurityDepositDeduction;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSecurityDepositDeductionRequest extends FormRequest
{
    use ResolvesRouteLease;

    public function authorize(): bool
    {
        $lease = $this->route('lease');
        if ($lease instanceof Lease) {
            $workspace = app(CurrentWorkspace::class)->get();
            if ($workspace && (int) $lease->workspace_id !== (int) $workspace->id) {
                return false;
            }
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('deductions')) {
            return;
        }

        if ($this->has('amount') || $this->has('category') || $this->has('description')) {
            $this->merge([
                'deductions' => [$this->only(['id', 'category', 'description', 'notes', 'amount', 'deducted_at'])],
            ]);

            if ($attachment = $this->file('attachment')) {
                $this->files->set('deductions', [
                    0 => ['attachment' => $attachment],
                ]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'deductions' => ['required', 'array', 'min:1', 'max:25'],
            'deductions.*.id' => ['nullable', 'integer'],
            'deductions.*.category' => ['required', 'string', 'max:50'],
            'deductions.*.description' => ['required', 'string', 'max:1000'],
            'deductions.*.notes' => ['nullable', 'string', 'max:1000'],
            'deductions.*.attachment' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf,webp', 'max:10240'],
            'deductions.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'deductions.*.deducted_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $lease = $this->routeLease();
            if (! $lease) {
                return;
            }

            if ($lease->security_deposit_status === 'settled') {
                $v->errors()->add('amount', 'لا يمكن إضافة أو تعديل الخصومات بعد اعتماد التسوية النهائية.');
                $v->errors()->add('deductions', 'لا يمكن إضافة أو تعديل الخصومات بعد اعتماد التسوية النهائية.');

                return;
            }

            $deductions = $this->array('deductions');
            $editingDeduction = $this->route('deduction');

            $availableForDeduction = (float) $lease->refundable_deposit_balance;
            if ($editingDeduction instanceof SecurityDepositDeduction) {
                $availableForDeduction += (float) $editingDeduction->amount;
            }

            $deductionsTotal = round(collect($deductions)->sum(fn (array $d): float => (float) ($d['amount'] ?? 0)), 2);

            if ($deductionsTotal > $availableForDeduction) {
                $key = count($deductions) === 1 ? 'amount' : 'deductions';
                $v->errors()->add($key, "إجمالي الخصومات المطلوبة ({$deductionsTotal} ج.م) يتجاوز الرصيد المتاح للخصم من مبلغ التأمين ({$availableForDeduction} ج.م).");
            }
        });
    }

    public function messages(): array
    {
        return [
            'deductions.required' => 'يجب إدخال بند خصم واحد على الأقل.',
            'deductions.*.amount.required' => 'مبلغ الخصم مطلوب.',
            'deductions.*.amount.numeric' => 'يجب أن يكون مبلغ الخصم رقماً صحيحاً أو عشرياً صالحاً.',
            'deductions.*.amount.gt' => 'يجب أن يكون مبلغ الخصم أكبر من صفر.',
            'deductions.*.amount.min' => 'يجب أن يكون مبلغ الخصم أكبر من صفر.',
            'deductions.*.category.required' => 'تصنيف وسبب الخصم مطلوب.',
            'deductions.*.description.required' => 'يرجى كتابة سبب الخصم بالتفصيل.',
            'deductions.*.attachment.mimes' => 'يجب أن يكون المرفق ملف صورة أو PDF صالح.',
            'deductions.*.attachment.max' => 'الحد الأقصى لحجم المرفق هو 10 ميجابايت.',
        ];
    }
}
