<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('origin', 50)->default('manual');
            $table->string('status', 50)->default('recorded');
            $table->string('payment_status', 50)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->string('category', 100);
            $table->string('title')->nullable();
            // Money: DECIMAL(12,2), never FLOAT. Nullable initially for pending maintenance expenses
            $table->decimal('amount', 12, 2)->nullable();
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'expense_date']);
            $table->index(['workspace_id', 'origin']);
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
