<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toMinorUnits(string|int $amount): int
    {
        $value = trim((string) $amount);
        if (! preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('المبلغ يجب أن يكون رقماً موجباً بمنزلتين عشريتين كحد أقصى.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function fromMinorUnits(int $amount): string
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('المبلغ لا يمكن أن يكون سالباً.');
        }

        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
