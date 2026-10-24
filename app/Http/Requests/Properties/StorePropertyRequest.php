<?php

declare(strict_types=1);

namespace App\Http\Requests\Properties;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'property_type' => ['required', 'in:building,commercial,villa,standalone,other'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:150'],
            'building_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'google_maps_url' => ['nullable', 'url', 'max:500'],
            'floors_count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'total_area' => ['nullable', 'numeric', 'min:0'],
            'year_built' => ['nullable', 'integer', 'min:1800', 'max:2100'],
            'finishing_type' => ['nullable', 'string', 'max:50'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:50'],
            'electricity_meter_number' => ['nullable', 'string', 'max:100'],
            'water_meter_number' => ['nullable', 'string', 'max:100'],
            'gas_meter_number' => ['nullable', 'string', 'max:100'],
            'guard_name' => ['nullable', 'string', 'max:150'],
            'guard_phone' => ['nullable', 'string', 'max:30'],
            'deed_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'cover_image_file' => ['nullable', 'image', 'max:5120'],
            'units' => ['nullable', 'array'],
            'units.*.name' => ['required_with:units', 'string', 'max:255'],
            'units.*.code' => ['nullable', 'string', 'max:50'],
            'units.*.unit_type' => ['required_with:units', 'in:apartment,shop,room,bed'],
            'units.*.is_rentable' => ['nullable', 'boolean'],
        ];
    }
}
