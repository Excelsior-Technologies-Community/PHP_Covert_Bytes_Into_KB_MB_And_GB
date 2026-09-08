<?php

session_start();

/**
 * Return conversion history.
 */
function getConversionHistory()
{
    return $_SESSION['conversion_history'] ?? [];
}


/**
 * Add a record to conversion history.
 */
function saveConversionHistory(
    $value,
    $from,
    $result,
    $to
) {
    if (!isset($_SESSION['conversion_history'])) {
        $_SESSION['conversion_history'] = [];
    }

    array_unshift(
        $_SESSION['conversion_history'],
        [
            'value' => $value,
            'from' => $from,
            'result' => $result,
            'to' => $to,
            'created_at' => date('d M Y, h:i A'),
        ]
    );

    $_SESSION['conversion_history'] =
        array_slice(
            $_SESSION['conversion_history'],
            0,
            20
        );
}