<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_import_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('historical_import_batch_id')->constrained()->restrictOnDelete();
            $table->string('record_type', 60);
            $table->string('source_reference', 120)->nullable();
            $table->date('original_date')->nullable();
            $table->longText('transcription');
            $table->char('fingerprint', 64)->unique();
            $table->enum('confidence', ['confirmed', 'incomplete', 'conflicting', 'unverified'])->default('unverified');
            $table->enum('review_status', ['pending', 'reviewed', 'needs_correction', 'rejected'])->default('pending');
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->index(['historical_import_batch_id', 'record_type'], 'hir_batch_type_index');
            $table->index(['review_status', 'confidence']);
        });

        Schema::create('historical_record_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('historical_import_record_id')->constrained('historical_import_records', 'id', 'hrr_historical_import_record_id_foreign')->restrictOnDelete();
            $table->longText('previous_transcription');
            $table->longText('corrected_transcription');
            $table->text('reason');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('corrected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_record_revisions');
        Schema::dropIfExists('historical_import_records');
    }
};
