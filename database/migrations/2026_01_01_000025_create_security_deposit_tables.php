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
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'other'])->default('cash');
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

        // 2. Security Deposit Settlements (Audit and inspection review)
        Schema::create('security_deposit_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('settled_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['under_review', 'settled'])->default('under_review')->index();
            $table->enum('inspection_category', [
                'no_issues',
                'furniture_damage',
                'fixture_damage',
                'missing_items',
                'other_damage',
            ])->default('no_issues');
            $table->text('inspection_notes')->nullable();
            $table->decimal('total_received', 12, 2)->default(0.00);
            $table->decimal('total_deductions', 12, 2)->default(0.00);
            $table->decimal('total_refunded', 12, 2)->default(0.00);
            $table->decimal('refundable_balance', 12, 2)->default(0.00);
            $table->timestamp('settled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Security Deposit Deductions (Damage and repair justifications)
        Schema::create('security_deposit_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('security_deposit_settlements')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('category', [
                'furniture_damage',
                'fixture_damage',
                'missing_items',
                'other_damage',
            ])->default('other_damage');
            $table->text('description');
            $table->enum('status', ['proposed', 'confirmed'])->default('confirmed');
            $table->timestamp('deducted_at');
            $table->timestamps();

            $table->index('deducted_at');
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
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'other'])->default('cash');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('refunded_at');
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
