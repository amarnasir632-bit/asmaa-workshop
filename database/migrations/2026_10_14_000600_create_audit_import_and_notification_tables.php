<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 120)->index();
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('request_id', 80)->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80)->index();
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('historical_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('source_description', 180);
            $table->date('imported_on');
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('records_seen')->default(0);
            $table->unsignedInteger('records_imported')->default(0);
            $table->unsignedInteger('records_flagged')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('historical_record_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('historical_import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('record_type', 80);
            $table->string('source_reference', 120)->nullable();
            $table->enum('issue_type', ['missing', 'conflicting', 'unverified', 'duplicate_candidate']);
            $table->text('description');
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_record_issues');
        Schema::dropIfExists('historical_import_batches');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
    }
};
