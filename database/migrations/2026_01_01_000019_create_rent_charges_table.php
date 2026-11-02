<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date')->index();
            // Money: DECIMAL(12,2), never FLOAT
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            // Prevent duplicate charges for the same billing period on the same lease
            $table->unique(['lease_id', 'period_start']);

            $table->index(['workspace_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_charges');
    }
};
