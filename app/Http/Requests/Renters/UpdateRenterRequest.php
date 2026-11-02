<?php

declare(strict_types=1);

namespace App\Http\Requests\Renters;

use App\Enums\Gender;
use App\Models\Renter;
use App\Rules\EgyptianPhoneNumber;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRenterRequest extends FormRequest
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
        $renter = $this->route('renter');
        $renterId = $renter instanceof Renter ? $renter->id : $renter;

        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => [
                'required',
                'string',
                new EgyptianPhoneNumber,
                Rule::unique('renters', 'phone')
                    ->where(fn ($query) => $workspaceId ? $query->where('workspace_id', $workspaceId) : $query)
                    ->ignore($renterId),
            ],
            'email' => ['nullable', 'string', 'lowercase', 'email:rfc', 'max:255'],
            'national_id' => ['nullable', 'string', 'min:6', 'max:50', 'regex:/^[A-Za-z0-9\s\-]+$/'],
            'gender' => ['nullable', 'string', Rule::enum(Gender::class)],
            'notes' => ['nullable', 'string'],
            'id_card_front' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'id_card_back' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
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
            'name.required' => 'يرجى إدخال اسم المستأجر.',
            'name.min' => 'يجب ألا يقل اسم المستأجر عن 3 أحرف.',
            'phone.required' => 'يرجى إدخال رقم الهاتف.',
            'phone.unique' => 'رقم الهاتف مسجل بالفعل لمستأجر آخر في هذه المنشأة.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'national_id.min' => 'يجب ألا يقل الرقم القومي / الهوية عن 6 خانات.',
            'national_id.regex' => 'الرقم القومي / الهوية يحتوي على رموز غير صالحة.',
        ];
    }
}
