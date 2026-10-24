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
        if (Schema::hasColumn('workspaces', 'cr_number')) {
            return;
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('cr_number', 50)->nullable()->after('currency');
            $table->string('tax_number', 50)->nullable()->after('cr_number');
            $table->string('phone', 30)->nullable()->after('tax_number');
            $table->string('address', 500)->nullable()->after('phone');
            $table->boolean('auto_whatsapp_reminders')->default(true)->after('rent_reminder_days_before');
            $table->boolean('auto_instant_receipts')->default(true)->after('auto_whatsapp_reminders');
            $table->unsignedTinyInteger('overdue_warning_days_after')->default(5)->after('auto_instant_receipts');
            $table->boolean('weekly_summary_report')->default(true)->after('overdue_warning_days_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $cols = array_filter([
                'cr_number',
                'tax_number',
                'phone',
                'address',
                'auto_whatsapp_reminders',
                'auto_instant_receipts',
                'overdue_warning_days_after',
                'weekly_summary_report',
            ], fn (string $col) => Schema::hasColumn('workspaces', $col));

            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
