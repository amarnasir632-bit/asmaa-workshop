<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->foreignId('cancellation_id')->nullable()->after('order_id')->constrained()->restrictOnDelete();
            $table->timestamp('returned_item_received_at')->nullable()->after('returned_delivery_item_id');
            $table->unsignedInteger('returned_quantity')->nullable()->after('returned_item_received_at');
            $table->enum('returned_item_condition', ['resalable', 'damaged', 'altered'])->nullable()->after('returned_item_received_at');
            $table->text('inspection_notes')->nullable()->after('returned_item_condition');
        });
        Schema::create('refund_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('refund_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('method', ['cash', 'bank_transfer']);
            $table->dateTime('refunded_at')->index();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_transactions');
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancellation_id');
            $table->dropColumn(['returned_item_received_at', 'returned_quantity', 'returned_item_condition', 'inspection_notes']);
        });
    }
};
