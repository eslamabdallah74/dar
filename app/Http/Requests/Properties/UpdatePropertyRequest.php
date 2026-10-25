<?php

declare(strict_types=1);

namespace App\Http\Requests\Properties;

use App\Enums\PropertyType;
use App\Rules\EgyptianPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'property_type' => ['sometimes', 'required', Rule::enum(PropertyType::class)],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:150'],
            'building_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'google_maps_url' => ['nullable', 'url', 'max:500'],
            'floors_count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'floor_number' => ['nullable', 'string', 'max:50'],
            'total_area' => ['nullable', 'numeric', 'min:0'],
            'commercial_activity' => ['nullable', 'string', 'max:150'],
            'year_built' => ['nullable', 'integer', 'min:1800', 'max:2100'],
            'finishing_type' => ['nullable', 'string', 'max:50'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:50'],
            'electricity_meter_number' => ['nullable', 'string', 'max:100'],
            'water_meter_number' => ['nullable', 'string', 'max:100'],
            'gas_meter_number' => ['nullable', 'string', 'max:100'],
            'guard_name' => ['nullable', 'string', 'min:3', 'max:150'],
            'guard_phone' => ['nullable', 'string', new EgyptianPhoneNumber],
            'deed_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'cover_image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
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
            'name.required' => 'يرجى إدخال اسم العقار.',
            'name.min' => 'يجب ألا يقل اسم العقار عن 3 أحرف.',
            'guard_name.min' => 'يجب ألا يقل اسم الحارس عن 3 أحرف.',
        ];
    }
}
