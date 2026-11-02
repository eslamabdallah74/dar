<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renter_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('renter_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 50);
            $table->string('title', 255)->nullable();
            $table->string('original_filename', 255)->nullable();
            // PRIVATE storage path — never exposed as a public URL
            $table->string('storage_path', 500);
            $table->unsignedInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'renter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renter_documents');
    }
};
