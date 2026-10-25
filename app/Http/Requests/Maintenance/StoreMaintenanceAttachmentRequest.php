<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceAttachmentRequest extends FormRequest
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
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,webp,pdf',
                'max:10240', // 10MB max
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'يرجى اختيار ملف واحد على الأقل للرفع.',
            'files.array' => 'صيغة الملفات المرسلة غير صحيحة.',
            'files.min' => 'يرجى اختيار ملف واحد على الأقل للرفع.',
            'files.max' => 'الحد الأقصى لعدد الملفات في المرة الواحدة هو 10 ملفات.',
            'files.*.required' => 'الملف المرفق مطلوب.',
            'files.*.file' => 'الملف المرفق غير صالح.',
            'files.*.mimes' => 'نوع الملف غير مدعوم. الصيغ المسموح بها هي: JPG, JPEG, PNG, WEBP, PDF.',
            'files.*.max' => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت.',
        ];
    }
}
