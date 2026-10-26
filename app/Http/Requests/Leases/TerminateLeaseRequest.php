<?php

declare(strict_types=1);

namespace App\Http\Requests\Leases;

use App\Enums\PaymentMethod;
use App\Enums\SecurityDepositRefundStatus;
use App\Enums\SecurityDepositSettlementType;
use App\Http\Requests\Concerns\ResolvesRouteLease;
use App\Models\Lease;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TerminateLeaseRequest extends FormRequest
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

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (! $this->routeLease()?->hasSecurityDeposit()) {
            return [
                'notes' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return [
            'settlement_type' => ['required', Rule::in(SecurityDepositSettlementType::values())],
            'inspection_category' => ['nullable', 'string', 'max:50'],
            'inspection_notes' => ['nullable', 'string', 'max:2000'],
            'retention_reason' => ['nullable', 'string', 'max:2000'],
            'deductions' => ['required_if:settlement_type,partial_refund', 'nullable', 'array', 'max:25'],
            'deductions.*.id' => ['nullable', 'integer'],
            'deductions.*.category' => ['required_with:deductions', 'string', 'max:50'],
            'deductions.*.amount' => ['required_with:deductions', 'numeric', 'gt:0', 'max:9999999999.99'],
            'deductions.*.description' => ['required_with:deductions', 'string', 'max:1000'],
            'deductions.*.notes' => ['nullable', 'string', 'max:1000'],
            'deductions.*.attachment' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf,webp', 'max:10240'],
            'refund_status' => ['nullable', Rule::in(SecurityDepositRefundStatus::values())],
            'refund_method' => ['required_if:refund_status,refunded', 'nullable', Rule::in(PaymentMethod::values())],
            'refund_payment_date' => ['required_if:refund_status,refunded', 'nullable', 'date'],
            'refund_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lease = $this->routeLease();
            if ($lease === null) {
                return;
            }

            if ($lease->status === 'terminated') {
                $validator->errors()->add('lease', 'هذا العقد منتهٍ بالفعل.');

                return;
            }

            if (! $lease->hasSecurityDeposit()) {
                return;
            }

            $availableBalance = max(0.0, $lease->total_deposit_received - $lease->total_deposit_refunded - $lease->total_deposit_deductions);

            match ($this->input('settlement_type')) {
                'partial_refund' => $this->validatePartialRefund($validator, $availableBalance),
                'full_refund' => $this->validateRefundStatusChosen($validator, $availableBalance, 'يجب تحديد حالة صرف مبلغ التأمين للمستأجر (معلق أو تم الصرف).'),
                'no_refund' => $this->validateRetentionIsJustified($validator),
                default => null,
            };
        });
    }

    private function validatePartialRefund(Validator $validator, float $availableBalance): void
    {
        $deductions = $this->input('deductions', []);
        if (empty($deductions)) {
            $validator->errors()->add('deductions', 'يجب إضافة بند خصم واحد على الأقل في حالة الرد الجزئي.');

            return;
        }

        $newDeductionsTotal = 0.0;
        foreach ($deductions as $deduction) {
            $newDeductionsTotal += (float) ($deduction['amount'] ?? 0);
        }

        if ($newDeductionsTotal > $availableBalance) {
            $validator->errors()->add('deductions', "مجموع الخصومات ({$newDeductionsTotal} ج.م) يتجاوز الرصيد المتاح من مبلغ التأمين ({$availableBalance} ج.م).");
        }

        $this->validateRefundStatusChosen(
            $validator,
            max(0.0, $availableBalance - $newDeductionsTotal),
            'يجب تحديد حالة صرف المبلغ المتبقي المستحق للمستأجر (معلق أو تم الصرف).'
        );
    }

    /**
     * Whenever money remains to be returned, the form must say whether it was already paid out.
     */
    private function validateRefundStatusChosen(Validator $validator, float $remainingToRefund, string $message): void
    {
        if ($remainingToRefund > 0 && ! in_array($this->input('refund_status'), ['pending', 'refunded'], true)) {
            $validator->errors()->add('refund_status', $message);
        }
    }

    private function validateRetentionIsJustified(Validator $validator): void
    {
        $reason = trim((string) $this->input('retention_reason', ''));
        if (mb_strlen($reason) < 3 && empty($this->input('deductions', []))) {
            $validator->errors()->add('retention_reason', 'يجب تسجيل أسباب واضحة وموثقة للاحتفاظ بمبلغ التأمين بالكامل.');
        }
    }

    public function messages(): array
    {
        return [
            'settlement_type.required' => 'يجب اختيار قرار تسوية التأمين (رد كامل، رد جزئي، أو عدم رد).',
            'retention_reason.required_if' => 'يجب توضيح سبب احتجاز وعدم رد مبلغ التأمين للمستأجر.',
            'deductions.required_if' => 'يجب إدخال تفاصيل الخصومات في حالة الرد الجزئي.',
            'deductions.*.amount.required_with' => 'مبلغ الخصم مطلوب.',
            'deductions.*.amount.gt' => 'يجب أن يكون مبلغ الخصم أكبر من صفر.',
            'deductions.*.category.required_with' => 'سبب وتصنيف الخصم مطلوب.',
            'deductions.*.description.required_with' => 'يرجى كتابة سبب الخصم بالتفصيل.',
            'refund_method.required_if' => 'طريقة السداد مطلوبة عند تسجيل أن المبلغ تم رده فعلياً للمستأجر.',
            'refund_payment_date.required_if' => 'تاريخ السداد مطلوب عند تسجيل أن المبلغ تم رده فعلياً.',
        ];
    }
}
