<?php

declare(strict_types=1);

namespace App\Http\Requests\Leases;

use App\Models\Lease;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExtendLeaseRequest extends FormRequest
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
        $lease = $this->route('lease');
        $currentEndsOn = $lease instanceof Lease ? $lease->ends_on->toDateString() : null;

        return [
            'ends_on' => $currentEndsOn !== null ? ['required', 'date', "after:{$currentEndsOn}"] : ['required', 'date'],
            'rent_amount' => ['nullable', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'ends_on.required' => 'تاريخ انتهاء العقد الجديد مطلوب.',
            'ends_on.date' => 'صيغة تاريخ التمديد غير صالحة.',
            'ends_on.after' => 'يجب أن يكون تاريخ نهاية العقد الجديد بعد تاريخ نهاية العقد الحالية.',
            'rent_amount.min' => 'يجب ألا يقل مبلغ الإيجار عن 1 ج.م.',
        ];
    }
}
