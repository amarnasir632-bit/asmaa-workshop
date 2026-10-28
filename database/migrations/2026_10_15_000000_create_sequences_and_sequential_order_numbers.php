<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table): void {
            $table->string('name', 50)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        $orderIds = DB::table('orders')->orderBy('id')->pluck('id');
        $sequence = 0;
        foreach ($orderIds as $id) {
            $sequence++;
            DB::table('orders')->where('id', $id)->update(['number' => (string) $sequence]);
        }

        DB::table('sequences')->insert(['name' => 'orders', 'value' => $sequence]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
