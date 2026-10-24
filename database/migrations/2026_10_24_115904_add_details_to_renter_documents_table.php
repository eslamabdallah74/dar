<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('renter_documents', 'title')) {
            return;
        }

        Schema::table('renter_documents', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->after('document_type');
            $table->string('original_filename', 255)->nullable()->after('title');
            $table->unsignedInteger('file_size')->nullable()->after('storage_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('renter_documents', function (Blueprint $table) {
            $cols = array_filter(['title', 'original_filename', 'file_size'], fn (string $col) => Schema::hasColumn('renter_documents', $col));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
