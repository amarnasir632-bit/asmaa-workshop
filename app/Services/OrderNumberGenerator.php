<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class OrderNumberGenerator
{
    public function next(): string
    {
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
    }
}
