<?php

/**
 * Convert a value from one storage unit to another.
 *
 * Uses 1024-based conversion.
 */
function convertDataSize($value, $fromUnit, $toUnit, $precision = 6)
{
    $units = [
        'B'  => 1,
        'KB' => 1024,
        'MB' => 1024 ** 2,
        'GB' => 1024 ** 3,
        'TB' => 1024 ** 4,
        'PB' => 1024 ** 5,
    ];

    if (!isset($units[$fromUnit]) || !isset($units[$toUnit])) {
        throw new InvalidArgumentException('Invalid storage unit.');
    }

    if (!is_numeric($value) || $value < 0) {
        throw new InvalidArgumentException('Invalid value.');
    }

    $precision = (int) $precision;

    if ($precision < 0) {
        $precision = 0;
    }

    if ($precision > 8) {
        $precision = 8;
    }

    $bytes = (float) $value * $units[$fromUnit];

    return round($bytes / $units[$toUnit], $precision);
}
