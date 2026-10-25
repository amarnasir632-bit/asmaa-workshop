<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_inventory_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 80)->unique();
            $table->date('counted_on');
            $table->enum('status', ['draft', 'approved'])->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('opening_inventory_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opening_inventory_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_counted');
            $table->integer('previous_quantity')->nullable();
            $table->text('difference_reason')->nullable();
            $table->timestamps();
            $table->unique(['opening_inventory_batch_id', 'inventory_variant_id'], 'oie_batch_variant_unique');
        });

        Schema::create('inventory_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 80)->unique();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_variant_id')->constrained()->restrictOnDelete();
            $table->integer('quantity_delta');
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_variant_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_available')->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_variant_id')->constrained()->restrictOnDelete();
            $table->integer('quantity_change');
            $table->enum('movement_type', ['opening', 'ready_sale', 'ready_sale_cancel', 'customer_return', 'adjustment_in', 'adjustment_out']);
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('delivery_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('opening_inventory_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_adjustment_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['inventory_variant_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('inventory_adjustment_items');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('opening_inventory_entries');
        Schema::dropIfExists('opening_inventory_batches');
    }
};
