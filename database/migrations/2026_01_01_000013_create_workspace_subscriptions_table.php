<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('activated_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['active', 'suspended', 'cancelled', 'expired']);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('provider_reference')->nullable()->comment('Reserved for future payment gateway');
            $table->timestamps();

            $table->index('workspace_id');
            $table->index(['workspace_id', 'status']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_subscriptions');
    }
};
