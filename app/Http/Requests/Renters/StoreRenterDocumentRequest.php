<?php

declare(strict_types=1);

namespace App\Http\Requests\Renters;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRenterDocumentRequest extends FormRequest
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
        return [
            'document_type' => ['required', 'in:national_id,passport,employment_letter,other'],
            'title' => ['nullable', 'string', 'min:2', 'max:255'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpeg,png,jpg,webp,doc,docx'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_type.required' => 'يرجى تحديد نوع الوثيقة/المستند.',
            'document_type.in' => 'نوع الوثيقة المحدد غير صالح.',
            'file.required' => 'يرجى إرفاق ملف الوثيقة.',
            'file.max' => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت.',
            'file.mimes' => 'نوع الملف غير مدعوم. الصيغ المدعومة: PDF, JPG, PNG, WEBP, DOC, DOCX.',
        ];
    }
}
