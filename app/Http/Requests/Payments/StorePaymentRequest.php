<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspaceId = app(CurrentWorkspace::class)->id();

        return [
            'renter_id' => [
                'required',
                Rule::exists('renters', 'id')->where(fn ($query) => $workspaceId ? $query->where('workspace_id', $workspaceId) : $query),
            ],
            'amount' => ['required', 'numeric', 'min:0.5'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cheque,other'],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'rent_charge_id' => [
                'nullable',
                Rule::exists('rent_charges', 'id')->where(fn ($query) => $workspaceId ? $query->where('workspace_id', $workspaceId) : $query),
            ],
        ];
    }
}
