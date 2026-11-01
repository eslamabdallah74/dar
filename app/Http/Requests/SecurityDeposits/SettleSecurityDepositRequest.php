<?php

declare(strict_types=1);

namespace App\Http\Requests\SecurityDeposits;

use App\Enums\PaymentMethod;
use App\Enums\SecurityDepositRefundStatus;
use App\Http\Requests\Concerns\ResolvesRouteLease;
use App\Models\Lease;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SettleSecurityDepositRequest extends FormRequest
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
        return [
            'inspection_category' => ['nullable', 'string', 'max:50'],
            'inspection_notes' => ['nullable', 'string', 'max:2000'],
            'retention_reason' => ['nullable', 'string', 'max:2000'],
            'deductions' => ['nullable', 'array', 'max:25'],
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
        $validator->after(function (Validator $v) {
            $lease = $this->routeLease();
            if (! $lease) {
                return;
            }

            if ($lease->security_deposit_status === 'settled') {
                $v->errors()->add('settlement', 'تم اعتماد تسوية التأمين لهذا العقد مسبقاً ولا يمكن تكرار التسوية.');

                return;
            }

            $totalReceived = (float) $lease->total_deposit_received;
            $previouslyRefunded = (float) $lease->total_deposit_refunded;

            $submittedDeductions = $this->array('deductions');
            $submittedIds = collect($submittedDeductions)->pluck('id')->filter()->all();

            $otherExistingDeductionsTotal = (float) $lease->securityDepositDeductions()
                ->when(! empty($submittedIds), fn ($q) => $q->whereNotIn('id', $submittedIds))
                ->sum('amount');

            $availableToDeduct = max(0.0, $totalReceived - $previouslyRefunded - $otherExistingDeductionsTotal);
            $submittedTotal = round(collect($submittedDeductions)->sum(fn (array $d): float => (float) ($d['amount'] ?? 0)), 2);

            if ($submittedTotal > $availableToDeduct) {
                $v->errors()->add('deductions', "إجمالي الخصومات ({$submittedTotal} ج.م) يتجاوز الرصيد المتاح من مبلغ التأمين ({$availableToDeduct} ج.م).");
            }
        });
    }

    public function messages(): array
    {
        return [
            'deductions.*.amount.required_with' => 'مبلغ الخصم مطلوب لكل بند.',
            'deductions.*.amount.numeric' => 'يجب أن يكون مبلغ الخصم رقماً صحيحاً أو عشرياً صالحاً.',
            'deductions.*.amount.gt' => 'يجب أن يكون مبلغ الخصم أكبر من صفر.',
            'deductions.*.category.required_with' => 'سبب وتصنيف الخصم مطلوب.',
            'deductions.*.description.required_with' => 'يرجى كتابة سبب الخصم بالتفصيل.',
            'refund_method.required_if' => 'طريقة السداد مطلوبة عند تسجيل أن المبلغ تم صرفه للمستأجر.',
            'refund_payment_date.required_if' => 'تاريخ السداد مطلوب عند تسجيل أن المبلغ تم صرفه للمستأجر.',
        ];
    }
}
