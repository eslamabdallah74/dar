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
            if (! $lease instanceof Lease) {
                return;
            }

            if ($lease->security_deposit_status === 'settled') {
                $v->errors()->add('amount', 'لا يمكن تسجيل دفعات تأمين لعقد تمت تسوية تأمينه بالكامل.');
            } elseif ((float) $lease->security_deposit_amount <= 0) {
                $v->errors()->add('amount', 'هذا العقد لا يتطلب سداد مبلغ تأمين.');
            } elseif ((float) $lease->outstanding_deposit_amount <= 0) {
                $v->errors()->add('amount', 'تم سداد مبلغ التأمين المتفق عليه بالكامل بالفعل.');
            } else {
                $amount = $this->float('amount');
                $outstanding = (float) $lease->outstanding_deposit_amount;
                if ($amount > $outstanding) {
                    $formattedOutstanding = number_format($outstanding);
                    $formattedAgreed = number_format((float) $lease->security_deposit_amount);
                    $currency = $lease->workspace->currency ?? 'ج.م';

                    $v->errors()->add(
                        'amount',
                        "لا يمكن سداد مبلغ أكبر من قيمة التأمين المستحقة ({$formattedOutstanding} {$currency} متبقية من إجمالي تأمين {$formattedAgreed} {$currency})."
                    );
                }
            }
        });
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
