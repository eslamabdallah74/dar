<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('renter_id')->constrained()->restrictOnDelete();
            // Self-reference for renewal chain (Decision 4)
            $table->foreignId('renewed_from_lease_id')->nullable()->constrained('leases')->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            // Money: DECIMAL(12,2), never FLOAT
            $table->decimal('rent_amount', 12, 2);
            $table->decimal('security_deposit_amount', 12, 2)->default(0.00);
            $table->enum('security_deposit_status', [
                'not_required',
                'due',
                'partially_received',
                'held',
                'under_review',
                'settled',
            ])->default('not_required');
            $table->enum('billing_interval', ['monthly'])->default('monthly');
            // Anchor day 1–31 derived from starts_on at creation (Decision 2)
            $table->unsignedTinyInteger('billing_anchor_day');
            $table->date('first_due_date');
            $table->enum('status', ['draft', 'active', 'expired', 'terminated', 'cancelled'])->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'starts_on', 'ends_on']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'security_deposit_status']);
        });

        // Ensure ends_on is after starts_on
        DB::statement('ALTER TABLE leases ADD CONSTRAINT chk_leases_dates CHECK (ends_on > starts_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
