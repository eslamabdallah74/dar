<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Security Deposit Payments (Collections)
        Schema::create('security_deposit_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at');
            $table->string('payment_method', 50)->default('cash');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('paid_at');
            $table->index(['workspace_id', 'lease_id']);
        });

        // 2. Security Deposit Settlements (Audit, deductions reconciliation and refund status)
        Schema::create('security_deposit_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('settled_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 50)->default('under_review')->index();
            $table->string('settlement_type', 50)->nullable();
            $table->text('retention_reason')->nullable();
            $table->decimal('original_deposit', 12, 2)->nullable();
            $table->string('inspection_category', 50)->default('no_issues');
            $table->text('inspection_notes')->nullable();
            $table->decimal('total_received', 12, 2)->default(0.00);
            $table->decimal('total_deductions', 12, 2)->default(0.00);
            $table->decimal('total_refunded', 12, 2)->default(0.00);
            $table->decimal('refundable_balance', 12, 2)->default(0.00);
            $table->string('refund_status', 50)->default('not_applicable');
            $table->string('refund_method', 50)->nullable();
            $table->date('refund_payment_date')->nullable();
            $table->string('refund_reference', 100)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Security Deposit Deductions (Damage, cleaning, maintenance, and unpaid dues justifications)
        Schema::create('security_deposit_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('security_deposit_settlements')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('category', 50)->default('other');
            $table->text('description');
            $table->string('attachment_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 50)->default('confirmed');
            $table->timestamp('deducted_at');
            $table->timestamps();

            $table->index('deducted_at');
            $table->index(['workspace_id', 'lease_id']);
        });

        // 4. Security Deposit Refunds (Returned amounts to renters)
        Schema::create('security_deposit_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('security_deposit_settlements')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('refunded_at');
            $table->string('payment_method', 50)->default('cash');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('refunded_at');
            $table->index(['workspace_id', 'lease_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_deposit_refunds');
        Schema::dropIfExists('security_deposit_deductions');
        Schema::dropIfExists('security_deposit_settlements');
        Schema::dropIfExists('security_deposit_payments');
    }
};
