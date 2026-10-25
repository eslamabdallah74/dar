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
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('title')->nullable()->after('category');
            $table->decimal('amount', 12, 2)->nullable()->change();
            $table->enum('origin', ['manual', 'maintenance'])->default('manual')->after('recorded_by');
            $table->enum('status', ['pending_amount', 'ready_to_apply', 'applied'])->default('applied')->after('origin');
            $table->timestamp('applied_at')->nullable()->after('status');
            $table->foreignId('applied_by')->nullable()->after('applied_at')->constrained('users')->nullOnDelete();

            $table->unique('maintenance_request_id');
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'origin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['applied_by']);
            $table->dropUnique(['maintenance_request_id']);
            $table->dropIndex(['workspace_id', 'status']);
            $table->dropIndex(['workspace_id', 'origin']);

            $table->dropColumn(['title', 'origin', 'status', 'applied_at', 'applied_by']);
            $table->decimal('amount', 12, 2)->nullable(false)->change();
        });
    }
};
