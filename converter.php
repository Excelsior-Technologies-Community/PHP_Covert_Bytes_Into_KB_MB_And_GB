<?php

/**
 * Convert a value from one storage unit to another.
 *
 * Supports SI (1000-based) and Binary (1024-based) modes,
 * plus bit conversion.
 *
 * @param string $value     Numeric value to convert
 * @param string $fromUnit  Source unit
 * @param string $toUnit    Target unit
 * @param int    $precision Decimal places (0-8)
 * @param bool   $isBinary  true = 1024-based, false = 1000-based
 *
 * @return float
 */
function convertDataSize(
    $value,
    $fromUnit,
    $toUnit,
    $precision = 6,
    $isBinary = true
) {
    $factor = $isBinary ? 1024 : 1000;

    $byteUnits = [
        'B'  => 1,
        'KB' => $factor,
        'MB' => $factor ** 2,
        'GB' => $factor ** 3,
        'TB' => $factor ** 4,
        'PB' => $factor ** 5,
    ];

    $bitUnits = [
        'b'  => 1,
        'Kb' => $factor,
        'Mb' => $factor ** 2,
        'Gb' => $factor ** 3,
        'Tb' => $factor ** 4,
        'Pb' => $factor ** 5,
    ];

    $allUnits = array_merge($byteUnits, $bitUnits);

    if (!isset($allUnits[$fromUnit]) || !isset($allUnits[$toUnit])) {
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

    $bytes = (float) $value * $allUnits[$fromUnit];

    return round($bytes / $allUnits[$toUnit], $precision);
}


/**
 * Get all supported units for a given mode.
 *
 * @param bool $isBinary
 *
 * @return array
 */
function getSupportedUnits($isBinary = true)
{
    $prefix = $isBinary ? '' : 'i';

    return [
        'B'  => 'B',
        'KB' => 'KB',
        'MB' => 'MB',
        'GB' => 'GB',
        'TB' => 'TB',
        'PB' => 'PB',
        'b'  => 'b',
        'Kb' => 'Kb',
        'Mb' => 'Mb',
        'Gb' => 'Gb',
        'Tb' => 'Tb',
        'Pb' => 'Pb',
    ];
}


/**
 * Convert value to all units and return array.
 *
 * @param mixed  $value
 * @param string $fromUnit
 * @param int    $precision
 * @param bool   $isBinary
 *
 * @return array
 */
function convertToAllUnits(
    $value,
    $fromUnit,
    $precision = 2,
    $isBinary = true
) {
    $units = getSupportedUnits($isBinary);

    $results = [];

    foreach ($units as $unit) {
        try {
            $results[$unit] = convertDataSize(
                $value,
                $fromUnit,
                $unit,
                $precision,
                $isBinary
            );
        } catch (Exception $e) {
            $results[$unit] = null;
        }
    }

    return $results;
}
