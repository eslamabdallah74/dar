<?php

declare(strict_types=1);

namespace App\Http\Requests\Leases;

use App\Models\Lease;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TerminateLeaseRequest extends FormRequest
{
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
        /** @var Lease|null $lease */
        $lease = $this->route('lease');

        $hasDeposit = $lease && ((float) $lease->security_deposit_amount > 0 || $lease->total_deposit_received > 0);

        if (! $hasDeposit) {
            return [
                'notes' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return [
            'settlement_type' => ['required', 'in:full_refund,partial_refund,no_refund'],
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
            'refund_status' => ['nullable', 'in:pending,refunded,not_applicable'],
            'refund_method' => ['required_if:refund_status,refunded', 'nullable', 'in:cash,bank_transfer,cheque,other'],
            'refund_payment_date' => ['required_if:refund_status,refunded', 'nullable', 'date'],
            'refund_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            /** @var Lease|null $lease */
            $lease = $this->route('lease');
            if (! $lease) {
                return;
            }

            if ($lease->status === 'terminated') {
                $v->errors()->add('lease', 'هذا العقد منتهٍ بالفعل.');

                return;
            }

            $hasDeposit = (float) $lease->security_deposit_amount > 0 || $lease->total_deposit_received > 0;
            if (! $hasDeposit) {
                return;
            }

            $settlementType = $this->input('settlement_type');
            $totalReceived = (float) $lease->total_deposit_received;
            $previouslyRefunded = (float) $lease->total_deposit_refunded;
            $previouslyDeducted = (float) $lease->total_deposit_deductions;
            $availableBalance = max(0.0, $totalReceived - $previouslyRefunded - $previouslyDeducted);

            if ($settlementType === 'partial_refund') {
                $deductions = $this->input('deductions', []);
                if (empty($deductions)) {
                    $v->errors()->add('deductions', 'يجب إضافة بند خصم واحد على الأقل في حالة الرد الجزئي.');

                    return;
                }

                $newDeductionsTotal = 0.0;
                foreach ($deductions as $d) {
                    $newDeductionsTotal += (float) ($d['amount'] ?? 0);
                }

                if ($newDeductionsTotal > $availableBalance) {
                    $v->errors()->add('deductions', "مجموع الخصومات ({$newDeductionsTotal} ج.م) يتجاوز الرصيد المتاح من مبلغ التأمين ({$availableBalance} ج.م).");
                }

                $remainingToRefund = max(0.0, $availableBalance - $newDeductionsTotal);
                if ($remainingToRefund > 0) {
                    $refundStatus = $this->input('refund_status');
                    if (! in_array($refundStatus, ['pending', 'refunded'], true)) {
                        $v->errors()->add('refund_status', 'يجب تحديد حالة صرف المبلغ المتبقي المستحق للمستأجر (معلق أو تم الصرف).');
                    }
                }
            } elseif ($settlementType === 'full_refund') {
                if ($availableBalance > 0) {
                    $refundStatus = $this->input('refund_status');
                    if (! in_array($refundStatus, ['pending', 'refunded'], true)) {
                        $v->errors()->add('refund_status', 'يجب تحديد حالة صرف مبلغ التأمين للمستأجر (معلق أو تم الصرف).');
                    }
                }
            } elseif ($settlementType === 'no_refund') {
                $reason = trim((string) $this->input('retention_reason', ''));
                $deductions = $this->input('deductions', []);
                if (mb_strlen($reason) < 3 && empty($deductions)) {
                    $v->errors()->add('retention_reason', 'يجب تسجيل أسباب واضحة وموثقة للاحتفاظ بمبلغ التأمين بالكامل.');
                }
            }
        });
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
