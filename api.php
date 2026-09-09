<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/converter.php';

$method = $_SERVER['REQUEST_METHOD'];

$value = isset($_REQUEST['value']) ? (string) $_REQUEST['value'] : '';
$from = isset($_REQUEST['from']) ? (string) $_REQUEST['from'] : '';
$to = isset($_REQUEST['to']) ? (string) $_REQUEST['to'] : '';
$precision = isset($_REQUEST['precision']) ? (int) $_REQUEST['precision'] : 6;
$mode = isset($_REQUEST['mode']) ? (string) $_REQUEST['mode'] : 'binary';
$all = isset($_REQUEST['all']) ? (bool) $_REQUEST['all'] : false;

if ($precision < 0) {
    $precision = 0;
}

if ($precision > 8) {
    $precision = 8;
}

$isBinary = ($mode !== 'si');

if ($value === '' || !is_numeric($value) || (float) $value < 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => 'Please provide a valid positive numeric value.',
    ]);

    exit;
}

if ($from === '' || $to === '') {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => 'Missing from or to unit.',
    ]);

    exit;
}

try {
    $result = convertDataSize(
        $value,
        $from,
        $to,
        $precision,
        $isBinary
    );

    $response = [
        'success' => true,
        'data' => [
            'value' => (float) $value,
            'from' => $from,
            'result' => $result,
            'to' => $to,
            'precision' => $precision,
            'mode' => $isBinary ? 'binary' : 'si',
        ],
    ];

    if ($all) {
        $response['data']['all_units'] = convertToAllUnits(
            $value,
            $from,
            $precision,
            $isBinary
        );
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
