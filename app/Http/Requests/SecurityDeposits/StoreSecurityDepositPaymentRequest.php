<?php

declare(strict_types=1);

namespace App\Http\Requests\SecurityDeposits;

use App\Enums\PaymentMethod;
use App\Models\Lease;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSecurityDepositPaymentRequest extends FormRequest
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
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
