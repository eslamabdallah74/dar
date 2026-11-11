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
        Schema::table('message_logs', function (Blueprint $table) {
            $table->dropForeign(['renter_id']);
        });

        Schema::table('message_logs', function (Blueprint $table) {
            $table->foreignId('renter_id')->nullable()->change();
            $table->foreign('renter_id')->references('id')->on('renters')->nullOnDelete();
            $table->json('payload_parameters')->nullable()->after('template_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_logs', function (Blueprint $table) {
            $table->dropForeign(['renter_id']);
            $table->dropColumn('payload_parameters');
        });

        DB::table('message_logs')->whereNull('renter_id')->delete();

        Schema::table('message_logs', function (Blueprint $table) {
            $table->foreignId('renter_id')->nullable(false)->change();
            $table->foreign('renter_id')->references('id')->on('renters')->restrictOnDelete();
        });
    }
};
