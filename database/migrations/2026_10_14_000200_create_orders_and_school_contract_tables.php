<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->enum('type', ['custom', 'ready', 'alteration']);
            $table->foreignId('guardian_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('customer_name_snapshot', 160)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('created_on')->index();
            $table->date('due_on')->nullable()->index();
            $table->enum('operational_status', ['new', 'in_progress', 'partially_received', 'fully_received', 'ready', 'delivered', 'cancelled'])->default('new')->index();
            $table->text('notes')->nullable();
            $table->decimal('items_total', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('approved_total', 15, 2)->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('school_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('contract_number', 80)->nullable()->unique();
            $table->string('contact_name', 160)->nullable();
            $table->text('terms')->nullable();
            $table->timestamps();
        });

        Schema::create('beneficiaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_snapshot', 160);
            $table->string('grade_snapshot', 100)->nullable();
            $table->string('source_reference', 100)->nullable();
            $table->timestamps();
            $table->index(['school_contract_id', 'name_snapshot']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('child_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('garment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description_snapshot', 180);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->index(['order_id', 'beneficiary_id']);
        });

        Schema::create('measurement_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('shoulder_cm', 7, 2)->nullable();
            $table->decimal('chest_cm', 7, 2)->nullable();
            $table->decimal('sleeve_cm', 7, 2)->nullable();
            $table->decimal('waist_circumference_cm', 7, 2)->nullable();
            $table->decimal('length_cm', 7, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_snapshots');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('beneficiaries');
        Schema::dropIfExists('school_contracts');
        Schema::dropIfExists('orders');
    }
};
