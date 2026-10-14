<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unique('order_item_id');
            $table->unsignedInteger('quantity');
            $table->enum('stage', ['not_started', 'sewing', 'with_tailor', 'waiting_completion', 'completed'])->default('not_started')->index();
            $table->date('expected_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tailor_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_job_id')->constrained()->restrictOnDelete();
            $table->string('recipient_name', 160);
            $table->date('sent_on')->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('tailor_dispatch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tailor_dispatch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_sent');
            $table->decimal('agreed_cost', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('tailor_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tailor_dispatch_id')->constrained()->restrictOnDelete();
            $table->date('received_on')->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('tailor_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tailor_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tailor_dispatch_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_received');
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->dateTime('delivered_at')->index();
            $table->string('received_by_name', 160);
            $table->string('receipt_number', 80)->nullable()->unique();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_delivered');
            $table->timestamps();
            $table->unique(['delivery_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('tailor_receipt_items');
        Schema::dropIfExists('tailor_receipts');
        Schema::dropIfExists('tailor_dispatch_items');
        Schema::dropIfExists('tailor_dispatches');
        Schema::dropIfExists('production_jobs');
    }
};
