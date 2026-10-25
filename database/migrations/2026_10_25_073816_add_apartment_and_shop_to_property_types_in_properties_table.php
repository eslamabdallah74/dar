<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `properties` MODIFY COLUMN `property_type` ENUM('building', 'apartment', 'shop', 'commercial', 'villa', 'standalone', 'other') NOT NULL DEFAULT 'building'");

        Schema::table('properties', function (Blueprint $table) {
            $table->string('commercial_activity')->nullable()->after('total_area');
            $table->string('floor_number')->nullable()->after('floors_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['commercial_activity', 'floor_number']);
        });

        DB::table('properties')
            ->whereIn('property_type', ['apartment', 'shop'])
            ->update(['property_type' => 'standalone']);

        DB::statement("ALTER TABLE `properties` MODIFY COLUMN `property_type` ENUM('building', 'commercial', 'villa', 'standalone', 'other') NOT NULL DEFAULT 'building'");
    }
};
