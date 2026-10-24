<?php

declare(strict_types=1);

namespace App\Http\Requests\Renters;

use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRenterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('renters', 'phone')->where(fn ($query) => $workspaceId ? $query->where('workspace_id', $workspaceId) : $query),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'whatsapp_consent' => ['nullable', 'boolean'],
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
            'phone.unique' => 'رقم الهاتف مسجل بالفعل لمستأجر آخر في هذه المنشأة.',
        ];
    }
}
