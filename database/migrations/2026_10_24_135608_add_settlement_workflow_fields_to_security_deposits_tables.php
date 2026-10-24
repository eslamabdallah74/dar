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
        Schema::table('security_deposit_settlements', function (Blueprint $table) {
            $table->enum('settlement_type', ['full_refund', 'partial_refund', 'no_refund'])->nullable()->after('status');
            $table->text('retention_reason')->nullable()->after('inspection_notes');
            $table->decimal('original_deposit', 12, 2)->nullable()->after('retention_reason');
            $table->enum('refund_status', ['pending', 'refunded', 'not_applicable'])->default('not_applicable')->after('refundable_balance');
            $table->string('refund_method', 50)->nullable()->after('refund_status');
            $table->date('refund_payment_date')->nullable()->after('refund_method');
            $table->string('refund_reference', 100)->nullable()->after('refund_payment_date');
        });

        Schema::table('security_deposit_deductions', function (Blueprint $table) {
            $table->string('attachment_path', 500)->nullable()->after('description');
            $table->text('notes')->nullable()->after('attachment_path');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE security_deposit_deductions MODIFY COLUMN category ENUM('furniture_damage', 'fixture_damage', 'unpaid_dues', 'missing_items', 'other_damage') NOT NULL DEFAULT 'other_damage'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_deposit_deductions', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'notes']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE security_deposit_deductions MODIFY COLUMN category ENUM('furniture_damage', 'fixture_damage', 'missing_items', 'other_damage') NOT NULL DEFAULT 'other_damage'");
        }

        Schema::table('security_deposit_settlements', function (Blueprint $table) {
            $table->dropColumn([
                'settlement_type',
                'retention_reason',
                'original_deposit',
                'refund_status',
                'refund_method',
                'refund_payment_date',
                'refund_reference',
            ]);
        });
    }
};
