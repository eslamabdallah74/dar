<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('property_type', 50)->default('building');
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('building_number', 50)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('google_maps_url', 500)->nullable();

            $table->unsignedInteger('floors_count')->default(1);
            $table->string('floor_number', 50)->nullable();
            $table->decimal('total_area', 10, 2)->nullable();
            $table->string('commercial_activity', 150)->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->string('finishing_type', 50)->nullable();

            $table->json('amenities')->nullable();

            $table->string('electricity_meter_number', 100)->nullable();
            $table->string('water_meter_number', 100)->nullable();
            $table->string('gas_meter_number', 100)->nullable();

            $table->string('guard_name', 150)->nullable();
            $table->string('guard_phone', 30)->nullable();
            $table->string('deed_number', 100)->nullable();
            $table->text('notes')->nullable();

            $table->string('cover_image', 255)->nullable();
            $table->json('images')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'city']);
            $table->index(['workspace_id', 'property_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
