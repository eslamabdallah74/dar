<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            // Self-referencing FK for hierarchy (apartment → room → bed)
            $table->foreignId('parent_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->enum('unit_type', ['apartment', 'room', 'bed', 'other']);
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->boolean('is_rentable')->default(false);
            $table->timestamps();

            $table->index('workspace_id');
            $table->index('property_id');
            $table->index('parent_unit_id');
            $table->index(['workspace_id', 'is_rentable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
