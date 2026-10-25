<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use App\Support\CurrentWorkspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceRequest extends FormRequest
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

        return [
            'property_id' => [
                'required',
                Rule::exists('properties', 'id')->where(fn ($q) => $workspaceId ? $q->where('workspace_id', $workspaceId) : $q),
            ],
            'unit_id' => [
                'nullable',
                Rule::exists('units', 'id')->where(fn ($q) => $workspaceId ? $q->where('workspace_id', $workspaceId) : $q),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'reported_by_renter_id' => [
                'nullable',
                Rule::exists('renters', 'id')->where(fn ($q) => $workspaceId ? $q->where('workspace_id', $workspaceId) : $q),
            ],
        ];
    }
}
