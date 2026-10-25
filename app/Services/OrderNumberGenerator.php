<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class OrderNumberGenerator
{
    public function next(): string
    {
        try {
            $this->ensureTableExists();

            return DB::transaction(function (): string {
                $current = DB::table('sequences')->where('name', 'orders')->lockForUpdate()->value('value');
                $next = (int) $current + 1;
                if ($current === null) {
                    DB::table('sequences')->insert(['name' => 'orders', 'value' => $next]);
                } else {
                    DB::table('sequences')->where('name', 'orders')->update(['value' => $next]);
                }

                return (string) $next;
            });
        } catch (Throwable) {
            $maxId = (int) DB::table('orders')->max('id');
            $next = $maxId + 1;
            while (DB::table('orders')->where('number', (string) $next)->exists()) {
                $next++;
            }

            return (string) $next;
        }
    }

    private function ensureTableExists(): void
    {
        if (! Schema::hasTable('sequences')) {
            Schema::create('sequences', function (Blueprint $table): void {
                $table->string('name', 50)->primary();
                $table->unsignedBigInteger('value')->default(0);
            });

            $count = DB::table('orders')->count();
            DB::table('sequences')->insert(['name' => 'orders', 'value' => $count]);
        }
    }
}
