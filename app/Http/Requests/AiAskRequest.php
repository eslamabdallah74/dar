<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiAskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:2', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'يرجى كتابة السؤال المراد طرحه.',
            'question.min' => 'يجب ألا يقل السؤال عن حرفين.',
            'question.max' => 'تجاوز السؤال الحد الأقصى المسموح به (1000 حرف).',
        ];
    }
}
