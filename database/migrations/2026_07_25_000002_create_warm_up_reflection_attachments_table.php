<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warm_up_reflection_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('warm_up_reflection_id')
                ->constrained('warm_up_reflections')
                ->cascadeOnDelete();
            $table->enum('kind', ['voice_recording', 'audio_file', 'video_file']);
            $table->string('disk', 40);
            $table->string('storage_path');
            $table->string('original_name');
            $table->string('detected_mime', 128);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->enum('scan_status', ['pending', 'clean', 'infected', 'error', 'unavailable'])->default('pending');
            $table->unsignedBigInteger('upload_security_record_id')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->index('warm_up_reflection_id', 'reflection_attachment_reflection_idx');
            $table->unique(['warm_up_reflection_id', 'sha256'], 'reflection_attachment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warm_up_reflection_attachments');
    }
};
