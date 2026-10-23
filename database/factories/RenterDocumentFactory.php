<?php

namespace Database\Factories;

use App\Models\Renter;
use App\Models\RenterDocument;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RenterDocument>
 */
class RenterDocumentFactory extends Factory
{
    protected $model = RenterDocument::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'renter_id' => Renter::factory(),
            'document_type' => 'national_id',
            'storage_path' => 'documents/renters/'.fake()->uuid().'.pdf',
            'uploaded_by' => User::factory(),
        ];
    }

    public function nationalId(): static
    {
        return $this->state(fn () => ['document_type' => 'national_id']);
    }

    public function passport(): static
    {
        return $this->state(fn () => ['document_type' => 'passport']);
    }

    public function employmentLetter(): static
    {
        return $this->state(fn () => ['document_type' => 'employment_letter']);
    }
}
