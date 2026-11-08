<?php

declare(strict_types=1);

namespace App\Http\Requests\Properties;

use App\Models\Property;
use App\Models\Unit;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $property = $this->route('property');
        $unit = $this->route('unit');
        $workspaceId = app(CurrentWorkspace::class)->id();

        if (! $property instanceof Property || ! $workspaceId || $property->workspace_id !== $workspaceId) {
            return false;
        }

        return ! ($unit instanceof Unit && $unit->property_id !== $property->id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspaceId = app(CurrentWorkspace::class)->id() ?? 0;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'unit_type' => ['required', 'in:apartment,room,bed,shop'],
            'is_rentable' => ['nullable', 'boolean'],
            'parent_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where('workspace_id', $workspaceId),
            ],
        ];
    }
}
