<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->enum('billing_interval', ['monthly'])->default('monthly');
            // Anchor day 1–31 derived from starts_on at creation (Decision 2)
            $table->unsignedTinyInteger('billing_anchor_day');
            $table->date('first_due_date');
            $table->enum('status', ['draft', 'active', 'expired', 'terminated', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
            $table->index('unit_id');
            $table->index('renter_id');
            $table->index('status');
            $table->index(['unit_id', 'starts_on', 'ends_on']);
            $table->index(['workspace_id', 'status']);
        });

        // Ensure ends_on is after starts_on
        DB::statement('ALTER TABLE leases ADD CONSTRAINT chk_leases_dates CHECK (ends_on > starts_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
