<?php

declare(strict_types=1);

namespace App\Http\Requests\SecurityDeposits;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ResolvesRouteLease;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordPendingRefundPaymentRequest extends FormRequest
{
    use ResolvesRouteLease;

    public function authorize(): bool
    {
        $lease = $this->routeLease();
        $workspaceId = app(CurrentWorkspace::class)->id();

        if (! $lease || ! $workspaceId) {
            return false;
        }

        return $lease->workspace_id === $workspaceId;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'refunded_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $lease = $this->routeLease();
            if (! $lease) {
                return;
            }

            $refundableBalance = $lease->refundable_deposit_balance;
            $amount = (float) $this->input('amount');

            if ($amount > $refundableBalance) {
                $currencySymbol = $lease->workspace?->currencySymbol() ?? 'ج.م';
                $v->errors()->add('amount', "المبلغ المطلوب صرفه ({$amount} {$currencySymbol}) يتجاوز الرصيد المستحق رده للمستأجر ({$refundableBalance} {$currencySymbol}).");
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
