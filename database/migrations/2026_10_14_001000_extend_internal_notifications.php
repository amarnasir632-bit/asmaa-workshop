<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->timestamp('resolved_at')->nullable()->index();
            $table->unique(['user_id', 'type', 'subject_type', 'subject_id'], 'notifications_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropUnique('notifications_subject_unique');
            $table->dropColumn('resolved_at');
        });
    }
};
