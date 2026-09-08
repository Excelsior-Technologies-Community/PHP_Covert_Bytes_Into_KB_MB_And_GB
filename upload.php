<?php

/**
 * File validation helper.
 *
 * This file contains reusable file-analysis functionality.
 */

function analyzeUploadedFile($file)
{
    if (!isset($file) || !is_array($file)) {
        throw new Exception('No file was provided.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed.');
    }

    // 100 MB limit.
    $maxSize = 100 * 1024 * 1024;

    if ($file['size'] > $maxSize) {
        throw new Exception('Maximum allowed file size is 100 MB.');
    }

    $size = (int) $file['size'];

    return [
        'name' => basename($file['name']),
        'size' => $size,
        'type' => $file['type'] ?? 'Unknown',
        'extension' => pathinfo(
            $file['name'],
            PATHINFO_EXTENSION
        ),
    ];
}


/**
 * Format bytes into the most suitable unit.
 */
function formatFileSize($bytes, $precision = 2)
{
    $units = [
        'B',
        'KB',
        'MB',
        'GB',
        'TB',
        'PB'
    ];

    $bytes = max(0, $bytes);

    $index = 0;

    while (
        $bytes >= 1024 &&
        $index < count($units) - 1
    ) {
        $bytes /= 1024;
        $index++;
    }

    return round($bytes, $precision) . ' ' . $units[$index];
}