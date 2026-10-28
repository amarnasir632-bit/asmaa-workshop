<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->dateTime('paid_at')->index();
            $table->enum('method', ['cash', 'bank_transfer']);
            $table->enum('verification_status', ['not_required', 'pending', 'verified'])->default('not_required')->index();
            $table->string('transaction_reference', 120)->nullable()->index();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'paid_at']);
        });

        Schema::create('payment_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('private_path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['payment_id', 'sha256']);
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('original_payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignId('returned_delivery_item_id')->nullable()->constrained('delivery_items')->restrictOnDelete();
            $table->decimal('approved_amount', 15, 2);
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->enum('method', ['cash', 'bank_transfer'])->nullable();
            $table->dateTime('refunded_at')->nullable()->index();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('cancellations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('production_stage_snapshot', 80);
            $table->text('reason');
            $table->decimal('cancellation_discount', 15, 2);
            $table->decimal('refund_due', 15, 2)->default(0);
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->index();
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('spent_on')->index();
            $table->string('description', 180);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('cancellations');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_evidence');
        Schema::dropIfExists('payments');
    }
};
