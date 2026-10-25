<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->char('currency', 3)->default('EGP');
            $table->string('cr_number', 50)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('timezone', 50)->default('Africa/Cairo');
            $table->string('status', 50)->default('active');
            $table->tinyInteger('rent_reminder_days_before')->unsigned()->default(2);
            $table->boolean('auto_whatsapp_reminders')->default(true);
            $table->boolean('auto_instant_receipts')->default(true);
            $table->unsignedTinyInteger('overdue_warning_days_after')->default(5);
            $table->boolean('weekly_summary_report')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
