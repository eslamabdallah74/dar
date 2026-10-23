<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('rent_charge_id')->constrained()->restrictOnDelete();
            // Money: DECIMAL(12,2), never FLOAT
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            // One allocation record per payment-charge pair
            $table->unique(['payment_id', 'rent_charge_id']);

            $table->index('workspace_id');
            $table->index('payment_id');
            $table->index('rent_charge_id');
        });
        // No hard deletes on payment_allocations — enforce at application layer
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
