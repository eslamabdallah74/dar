<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRequest extends FormRequest
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
        $workspaceId = app(CurrentWorkspace::class)->id();
        $propertyId = $this->input('property_id');

        return [
            'property_id' => [
                'required',
                Rule::exists('properties', 'id')->where(fn ($q) => $workspaceId ? $q->where('workspace_id', $workspaceId) : $q),
            ],
            'unit_id' => [
                'nullable',
                Rule::exists('units', 'id')->where(function ($q) use ($workspaceId, $propertyId) {
                    if ($workspaceId) {
                        $q->where('workspace_id', $workspaceId);
                    }
                    if ($propertyId) {
                        $q->where('property_id', $propertyId);
                    }
                }),
            ],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string'],
            'reported_by_renter_id' => [
                'nullable',
                Rule::exists('renters', 'id')->where(fn ($q) => $workspaceId ? $q->where('workspace_id', $workspaceId) : $q),
            ],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'mimes:jpeg,jpg,png,webp,pdf',
                'max:10240',
            ],
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
            'property_id.required' => 'يرجى تحديد العقار المراد طلب صيانة له.',
            'property_id.exists' => 'العقار المحدد غير موجود أو غير متاح.',
            'unit_id.exists' => 'الوحدة المحددة غير صحيحة أو لا تنتمي إلى هذا العقار.',
            'title.required' => 'يرجى كتابة عنوان أو نوع الصيانة المطلوبة.',
            'title.min' => 'يجب ألا يقل عنوان طلب الصيانة عن 3 أحرف.',
            'reported_by_renter_id.exists' => 'المستأجر المحدد غير مسجل في مساحة العمل.',
            'attachments.array' => 'صيغة المرفقات غير صحيحة.',
            'attachments.max' => 'الحد الأقصى لعدد المرفقات هو 10 ملفات.',
            'attachments.*.file' => 'الملف المرفق غير صالح.',
            'attachments.*.mimes' => 'نوع الملف غير مدعوم. الصيغ المسموح بها هي: JPG, JPEG, PNG, WEBP, PDF.',
            'attachments.*.max' => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت.',
        ];
    }
}
