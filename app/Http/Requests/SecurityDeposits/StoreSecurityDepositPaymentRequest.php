<?php

declare(strict_types=1);

namespace App\Http\Requests\SecurityDeposits;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\ResolvesRouteLease;
use App\Models\Lease;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSecurityDepositPaymentRequest extends FormRequest
{
    use ResolvesRouteLease;

    public function authorize(): bool
    {
        $lease = $this->routeLease();
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $lease = $this->routeLease();
            if ($lease instanceof Lease) {
                $this->validateDepositPayment($v, $lease);
            }
        });
    }

    private function validateDepositPayment(Validator $validator, Lease $lease): void
    {
        if ($lease->security_deposit_status === 'settled') {
            $validator->errors()->add('amount', 'لا يمكن تسجيل دفعات تأمين لعقد تمت تسوية تأمينه بالكامل.');

            return;
        }

        $depositRequired = round((float) $lease->security_deposit_amount, 2);
        if ($depositRequired <= 0) {
            $validator->errors()->add('amount', 'هذا العقد لا يتطلب سداد مبلغ تأمين.');

            return;
        }

        $outstanding = round((float) $lease->outstanding_deposit_amount, 2);
        if ($outstanding <= 0) {
            $validator->errors()->add('amount', 'تم سداد مبلغ التأمين المتفق عليه بالكامل بالفعل.');

            return;
        }

        $rawAmount = $this->input('amount');
        $amount = is_numeric($rawAmount) ? round((float) $rawAmount, 2) : 0.0;
        if ($amount > $outstanding) {
            $formattedOutstanding = number_format($outstanding);
            $formattedAgreed = number_format($depositRequired);
            $currency = $lease->workspace->currency ?? 'ج.م';

            $validator->errors()->add(
                'amount',
                "لا يمكن سداد مبلغ أكبر من قيمة التأمين المستحقة ({$formattedOutstanding} {$currency} متبقية من إجمالي تأمين {$formattedAgreed} {$currency})."
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'يرجى إدخال مبلغ دفعة التأمين.',
            'amount.numeric' => 'يجب أن يكون مبلغ التأمين رقماً صحيحاً أو عشرياً.',
            'amount.min' => 'يجب أن يكون مبلغ التأمين أكبر من صفر.',
            'payment_method.required' => 'يرجى اختيار طريقة السداد.',
            'payment_method.in' => 'طريقة السداد المحددة غير صالحة.',
            'paid_at.date' => 'تاريخ التحصيل غير صالح.',
        ];
    }
}
