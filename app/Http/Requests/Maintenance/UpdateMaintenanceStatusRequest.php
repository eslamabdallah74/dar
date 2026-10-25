<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use App\Models\MaintenanceRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:pending,open,in_progress,completed,closed'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $status = $this->input('status');

            if (in_array($status, ['completed', 'closed'], true)) {
                $maintenance = $this->route('maintenance');

                if (is_numeric($maintenance)) {
                    $maintenance = MaintenanceRequest::find($maintenance);
                }

                if ($maintenance instanceof MaintenanceRequest && ! $maintenance->hasValidExpenseAmount()) {
                    $validator->errors()->add(
                        'status',
                        'يجب تسجيل تكلفة الصيانة أولًا من صفحة المصروفات قبل إنهاء الطلب.'
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
            'status.required' => 'حالة طلب الصيانة مطلوبة.',
            'status.in' => 'حالة طلب الصيانة المحددة غير صحيحة.',
        ];
    }
}
