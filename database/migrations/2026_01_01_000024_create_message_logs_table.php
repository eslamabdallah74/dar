<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('renter_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rent_charge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_phone', 20);
            $table->string('template_name', 100);
            $table->timestamp('scheduled_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'cancelled'])->default('pending')->index();
            $table->string('provider_message_id')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            // Provider cost per message (Decision 7 — tracked but not billed automatically)
            $table->decimal('provider_cost', 8, 6)->nullable();
            $table->char('provider_cost_currency', 3)->nullable();
            // Prevents the scheduler from creating duplicate reminders for the same obligation
            $table->string('deduplication_key')->unique();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
