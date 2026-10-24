<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('properties')
            ->where('property_type', 'compound')
            ->update(['property_type' => 'other']);

        Schema::table('properties', function (Blueprint $table) {
            $table->enum('property_type', ['building', 'commercial', 'villa', 'standalone', 'other'])
                ->default('building')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->enum('property_type', ['building', 'commercial', 'villa', 'compound', 'standalone', 'other'])
                ->default('building')
                ->change();
        });
    }
};
