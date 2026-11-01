<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->decimal('monthly_price', 12, 2);
            $unlimitedComment = 'NULL = unlimited';
            $table->unsignedInteger('unit_limit')->nullable()->comment($unlimitedComment);
            $table->unsignedInteger('parent_unit_limit')->nullable()->comment($unlimitedComment);
            $table->unsignedInteger('active_rental_limit')->nullable()->comment($unlimitedComment);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
