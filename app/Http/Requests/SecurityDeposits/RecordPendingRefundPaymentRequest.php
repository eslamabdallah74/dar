<?php

declare(strict_types=1);

namespace App\Http\Requests\SecurityDeposits;

use App\Models\Lease;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RecordPendingRefundPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cheque,other'],
            'refunded_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
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

            $refundableBalance = $lease->refundable_deposit_balance;
            $amount = (float) $this->input('amount');

            if ($amount > $refundableBalance) {
                $v->errors()->add('amount', "المبلغ المطلوب صرفه ({$amount} ج.م) يتجاوز الرصيد المستحق رده للمستأجر ({$refundableBalance} ج.م).");
            }
        });
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'مبلغ الرد الفعلي مطلوب.',
            'amount.min' => 'يجب أن يكون المبلغ أكبر من صفر.',
            'payment_method.required' => 'طريقة السداد مطلوبة.',
            'refunded_at.required' => 'تاريخ الصرف والرد مطلوب.',
        ];
    }
}
