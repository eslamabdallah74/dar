<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand property_type enum to include commercial and standalone
        DB::statement("ALTER TABLE `properties` MODIFY COLUMN `property_type` ENUM('building', 'commercial', 'villa', 'compound', 'standalone', 'other') NOT NULL DEFAULT 'building'");

        if (Schema::hasColumn('properties', 'code')) {
            return;
        }

        Schema::table('properties', function (Blueprint $table) {
            // Identifier & Code
            $table->string('code', 50)->nullable()->after('name');

            // Detailed Location
            $table->string('city', 100)->nullable()->after('address');
            $table->string('district', 100)->nullable()->after('city');
            $table->string('street', 150)->nullable()->after('district');
            $table->string('building_number', 50)->nullable()->after('street');
            $table->string('postal_code', 20)->nullable()->after('building_number');
            $table->string('google_maps_url', 500)->nullable()->after('postal_code');

            // Building Specifications
            $table->unsignedInteger('floors_count')->default(1)->after('google_maps_url');
            $table->decimal('total_area', 10, 2)->nullable()->after('floors_count');
            $table->unsignedSmallInteger('year_built')->nullable()->after('total_area');
            $table->string('finishing_type', 50)->nullable()->after('year_built');

            // Facilities & Amenities
            $table->json('amenities')->nullable()->after('finishing_type');

            // Utility Meters
            $table->string('electricity_meter_number', 100)->nullable()->after('amenities');
            $table->string('water_meter_number', 100)->nullable()->after('electricity_meter_number');
            $table->string('gas_meter_number', 100)->nullable()->after('water_meter_number');

            // Management & Ownership
            $table->string('guard_name', 150)->nullable()->after('gas_meter_number');
            $table->string('guard_phone', 30)->nullable()->after('guard_name');
            $table->string('deed_number', 100)->nullable()->after('guard_phone');
            $table->text('notes')->nullable()->after('deed_number');

            // Media
            $table->string('cover_image', 255)->nullable()->after('notes');
            $table->json('images')->nullable()->after('cover_image');

            $table->index(['workspace_id', 'city']);
            $table->index(['workspace_id', 'property_type']);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'city']);
            $table->dropIndex(['workspace_id', 'property_type']);

            $table->dropColumn([
                'code',
                'city',
                'district',
                'street',
                'building_number',
                'postal_code',
                'google_maps_url',
                'floors_count',
                'total_area',
                'year_built',
                'finishing_type',
                'amenities',
                'electricity_meter_number',
                'water_meter_number',
                'gas_meter_number',
                'guard_name',
                'guard_phone',
                'deed_number',
                'notes',
                'cover_image',
                'images',
            ]);
        });
    }
};
