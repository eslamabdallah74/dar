<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('id_card_front', 500)->nullable();
            $table->string('id_card_back', 500)->nullable();
            // WhatsApp consent fields (Decision 7)
            $table->timestamp('whatsapp_opt_in_at')->nullable();
            $table->string('whatsapp_opt_in_source', 100)->nullable();
            $table->foreignId('whatsapp_opt_in_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('whatsapp_opted_out_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renters');
    }
};
