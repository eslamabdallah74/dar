<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\AI\AiConfigurationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['sometimes', 'nullable', 'string', Rule::in(AiConfigurationService::SUPPORTED_PROVIDERS)],
            'token' => ['sometimes', 'nullable', 'string', 'min:10', 'max:500'],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider.in' => 'مزود الذكاء الاصطناعي المختار غير مدعوم.',
            'token.min' => 'يجب ألا يقل مفتاح API عن 10 أحرف.',
            'token.max' => 'تجاوز مفتاح API الحد الأقصى للطول.',
            'model.max' => 'تجاوز اسم النموذج الحد الأقصى للطول.',
        ];
    }
}
