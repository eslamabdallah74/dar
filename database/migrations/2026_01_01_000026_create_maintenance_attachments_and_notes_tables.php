<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_attachments')) {
            Schema::create('maintenance_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
                $table->foreignId('maintenance_request_id')->constrained('maintenance_requests')->cascadeOnDelete();
                $table->string('file_path', 500);
                $table->string('original_filename', 255);
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('file_size');
                $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->index(['workspace_id', 'maintenance_request_id'], 'maint_attach_ws_req_idx');
            });
        }

        if (! Schema::hasTable('maintenance_notes')) {
            Schema::create('maintenance_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
                $table->foreignId('maintenance_request_id')->constrained('maintenance_requests')->cascadeOnDelete();
                $table->text('note');
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->index(['workspace_id', 'maintenance_request_id'], 'maint_notes_ws_req_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_notes');
        Schema::dropIfExists('maintenance_attachments');
    }
};
