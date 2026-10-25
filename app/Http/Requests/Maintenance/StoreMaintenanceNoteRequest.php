<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceNoteRequest extends FormRequest
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
            'note' => ['required', 'string', 'min:2', 'max:3000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => 'يرجى إدخال نص الملاحظة.',
            'note.string' => 'نص الملاحظة يجب أن يكون نصًا صالحًا.',
            'note.min' => 'يجب ألا يقل نص الملاحظة عن حرفين.',
            'note.max' => 'يجب ألا يتجاوز نص الملاحظة 3000 حرف.',
        ];
    }
}
