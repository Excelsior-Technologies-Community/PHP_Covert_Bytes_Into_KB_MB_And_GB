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
    $to,
    $precision = 6
) {
    if (!isset($_SESSION['conversion_history'])) {
        $_SESSION['conversion_history'] = [];
    }

    array_unshift(
        $_SESSION['conversion_history'],
        [
            'id' => uniqid('conversion_', true),
            'value' => $value,
            'from' => $from,
            'result' => $result,
            'to' => $to,
            'precision' => $precision,
            'created_at' => date('d M Y, h:i A'),
        ]
    );

    $_SESSION['conversion_history'] =
        array_slice(
            $_SESSION['conversion_history'],
            0,
            50
        );
}


/**
 * Delete a history record by ID.
 */
function deleteHistoryRecord($id)
{
    if (!isset($_SESSION['conversion_history'])) {
        return false;
    }

    $_SESSION['conversion_history'] =
        array_values(
            array_filter(
                $_SESSION['conversion_history'],
                function ($item) use ($id) {
                    return ($item['id'] ?? '') !== $id;
                }
            )
        );

    return true;
}


/**
 * Bulk delete history records.
 */
function bulkDeleteHistory($ids)
{
    if (!isset($_SESSION['conversion_history']) || empty($ids)) {
        return false;
    }

    $_SESSION['conversion_history'] =
        array_values(
            array_filter(
                $_SESSION['conversion_history'],
                function ($item) use ($ids) {
                    return !in_array(
                        $item['id'] ?? '',
                        $ids,
                        true
                    );
                }
            )
        );

    return true;
}


/**
 * Export history as JSON.
 */
function exportHistoryAsJson()
{
    $history = getConversionHistory();

    header('Content-Type: application/json; charset=utf-8');

    header(
        'Content-Disposition: attachment; filename="conversion-history.json"'
    );

    echo json_encode(
        [
            'exported_at' => date('c'),
            'total_records' => count($history),
            'history' => $history,
        ],
        JSON_PRETTY_PRINT
    );

    exit;
}


/**
 * Export history as CSV.
 */
function exportHistoryAsCsv()
{
    $history = getConversionHistory();

    header('Content-Type: text/csv; charset=utf-8');

    header(
        'Content-Disposition: attachment; filename="conversion-history.csv"'
    );

    $output = fopen('php://output', 'w');

    fputcsv(
        $output,
        [
            'Value',
            'From',
            'Result',
            'To',
            'Precision',
            'Date & Time'
        ]
    );

    foreach ($history as $item) {
        fputcsv(
            $output,
            [
                $item['value'] ?? '',
                $item['from'] ?? '',
                $item['result'] ?? '',
                $item['to'] ?? '',
                $item['precision'] ?? '',
                $item['created_at'] ?? ''
            ]
        );
    }

    fclose($output);

    exit;
}
