<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160)->index();
            $table->string('phone', 40)->index();
            $table->string('alternate_phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 180)->unique();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('children', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160)->index();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['guardian_id', 'name']);
        });

        Schema::create('current_measurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('child_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('shoulder_cm', 7, 2)->nullable();
            $table->decimal('chest_cm', 7, 2)->nullable();
            $table->decimal('sleeve_cm', 7, 2)->nullable();
            $table->decimal('waist_circumference_cm', 7, 2)->nullable();
            $table->decimal('length_cm', 7, 2)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('garment_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('requires_size')->default(true);
            $table->boolean('requires_color')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('inventory_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('garment_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->string('size', 60)->nullable();
            $table->string('color', 80)->nullable();
            $table->string('sku', 80)->nullable()->unique();
            $table->char('attributes_key', 64)->unique();
            $table->string('description', 180)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['school_id', 'garment_type_id', 'size', 'color'], 'inventory_variant_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_variants');
        Schema::dropIfExists('garment_types');
        Schema::dropIfExists('current_measurements');
        Schema::dropIfExists('children');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('schools');
        Schema::dropIfExists('guardians');
    }
};
