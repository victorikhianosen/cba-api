<?php

declare(strict_types=1);

namespace App\Support;

class ReferenceGenerator
{
    // Prefix + microsecond timestamp + 4-digit random suffix — collision-resistant without a DB lookup, so any caller can generate a reference without coordinating on a shared column/table.
    public static function generate(string $prefix): string
    {
        $microtime = microtime(true);
        $seconds = (int) $microtime;
        $dateTime = date('ymdHis', $seconds);
        $microseconds = sprintf(
            '%06d',
            (int) (($microtime - $seconds) * 1000000)
        );

        $random = sprintf('%04d', random_int(0, 9999));

        return $prefix . $dateTime . $microseconds . $random;
    }
}
