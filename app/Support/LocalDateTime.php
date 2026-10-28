<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class LocalDateTime
{
    public static function toUtc(string $value): Carbon
    {
        return Carbon::parse($value, config('app.display_timezone', 'Africa/Khartoum'))->utc();
    }
}
