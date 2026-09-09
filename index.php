<?php

session_start();

require_once __DIR__ . '/converter.php';
require_once __DIR__ . '/upload.php';

/*
|--------------------------------------------------------------------------
| Available storage units
|--------------------------------------------------------------------------
*/

$units = [
    'B'  => 1,
    'KB' => 1024,
    'MB' => 1024 ** 2,
    'GB' => 1024 ** 3,
    'TB' => 1024 ** 4,
    'PB' => 1024 ** 5,
    'b'  => 1,
    'Kb' => 1024,
    'Mb' => 1024 ** 2,
    'Gb' => 1024 ** 3,
    'Tb' => 1024 ** 4,
    'Pb' => 1024 ** 5,
];

$byteUnits = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
$bitUnits = ['b', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb'];


/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function addToHistory(
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


function getConversionHistory()
{
    return $_SESSION['conversion_history'] ?? [];
}


function formatBytes($bytes, $precision = 2)
{
    $units = [
        'B',
        'KB',
        'MB',
        'GB',
        'TB',
        'PB'
    ];

    $bytes = max(0, (float) $bytes);

    $index = 0;

    while (
        $bytes >= 1024 &&
        $index < count($units) - 1
    ) {
        $bytes /= 1024;
        $index++;
    }

    return round(
        $bytes,
        $precision
    ) . ' ' . $units[$index];
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$message = '';

$messageType = '';

$conversionResult = null;

$fileResult = null;

$precision = 6;

$conversionMode = 'binary';

$isBinary = true;

$allUnitsResult = null;


/*
|--------------------------------------------------------------------------
| Delete History
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_history'])
) {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        $message = 'Invalid security token.';
        $messageType = 'error';
    } else {

        $deleteId =
            $_POST['history_id'] ?? '';

        if (isset($_SESSION['conversion_history'])) {

            $_SESSION['conversion_history'] =
                array_values(
                    array_filter(
                        $_SESSION['conversion_history'],
                        function ($item) use ($deleteId) {

                            return ($item['id'] ?? '') !== $deleteId;
                        }
                    )
                );
        }

        $message =
            'History record deleted.';

        $messageType =
            'success';
    }
}


/*
|--------------------------------------------------------------------------
| Bulk Delete History
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['bulk_delete_history'])
) {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        $message = 'Invalid security token.';
        $messageType = 'error';
    } else {
        $deleteIds = $_POST['history_ids'] ?? [];

        if (!empty($deleteIds) && isset($_SESSION['conversion_history'])) {
            $_SESSION['conversion_history'] =
                array_values(
                    array_filter(
                        $_SESSION['conversion_history'],
                        function ($item) use ($deleteIds) {
                            return !in_array(
                                $item['id'] ?? '',
                                $deleteIds,
                                true
                            );
                        }
                    )
                );
        }

        $message = 'Selected history records deleted.';
        $messageType = 'success';
    }
}


/*
|--------------------------------------------------------------------------
| Download CSV
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['download_csv'])
) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        die('Invalid security token.');
    }

    $history =
        getConversionHistory();

    header(
        'Content-Type: text/csv; charset=utf-8'
    );

    header(
        'Content-Disposition: attachment; filename="conversion-history.csv"'
    );

    $output =
        fopen('php://output', 'w');

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


/*
|--------------------------------------------------------------------------
| Download JSON
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['download_json'])
) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        die('Invalid security token.');
    }

    $history =
        getConversionHistory();

    header(
        'Content-Type: application/json; charset=utf-8'
    );

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


/*
|--------------------------------------------------------------------------
| Normal Conversion
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['convert'])
) {

    $value =
        $_POST['value'] ?? '';

    $from =
        $_POST['from_unit'] ?? 'B';

    $to =
        $_POST['to_unit'] ?? 'KB';

    $precision =
        (int) (
            $_POST['precision'] ?? 6
        );

    $conversionMode =
        $_POST['conversion_mode'] ?? 'binary';

    $isBinary = ($conversionMode !== 'si');

    if ($precision < 0) {
        $precision = 0;
    }

    if ($precision > 8) {
        $precision = 8;
    }

    if (
        $value === '' ||
        !is_numeric($value) ||
        (float) $value < 0
    ) {

        $message =
            'Please enter a valid positive number.';

        $messageType =
            'error';
    } elseif (
        !isset($units[$from]) ||
        !isset($units[$to])
    ) {

        $message =
            'Invalid conversion unit selected.';

        $messageType =
            'error';
    } else {

        try {

            $result =
                convertDataSize(
                    $value,
                    $from,
                    $to,
                    $precision,
                    $isBinary
                );

            $conversionResult = [
                'value' => $value,
                'from' => $from,
                'result' => $result,
                'to' => $to,
            ];

            $allUnitsResult =
                convertToAllUnits(
                    $value,
                    $from,
                    $precision,
                    $isBinary
                );

            addToHistory(
                $value,
                $from,
                $result,
                $to,
                $precision
            );

            $message =
                'Conversion completed successfully.';

            $messageType =
                'success';
        } catch (Exception $exception) {

            $message =
                $exception->getMessage();

            $messageType =
                'error';
        }
    }
}


/*
|--------------------------------------------------------------------------
| File Analyzer
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['analyze_file'])
) {

    try {

        if (
            !isset($_FILES['file']) ||
            $_FILES['file']['error'] ===
            UPLOAD_ERR_NO_FILE
        ) {

            throw new Exception(
                'Please select a file.'
            );
        }

        $file =
            analyzeUploadedFile(
                $_FILES['file']
            );

        $fileSize =
            $file['size'];

        $fileResult = [

            'name' =>
            $file['name'],

            'size_bytes' =>
            $fileSize,

            'formatted' =>
            formatFileSize(
                $fileSize
            ),

            'kb' =>
            round(
                $fileSize /
                    $units['KB'],
                2
            ),

            'mb' =>
            round(
                $fileSize /
                    $units['MB'],
                4
            ),

            'gb' =>
            round(
                $fileSize /
                    $units['GB'],
                6
            ),

            'tb' =>
            round(
                $fileSize /
                    $units['TB'],
                8
            ),

            'pb' =>
            round(
                $fileSize /
                    $units['PB'],
                10
            ),

            'bits' =>
            $fileSize * 8,

            'Kb' =>
            round(
                $fileSize * 8 /
                    $units['Kb'],
                2
            ),

            'Mb' =>
            round(
                $fileSize * 8 /
                    $units['Mb'],
                4
            ),

            'Gb' =>
            round(
                $fileSize * 8 /
                    $units['Gb'],
                6
            ),

            'type' =>
            $file['type'],

            'extension' =>
            strtoupper(
                $file['extension']
            ),
        ];

        addToHistory(
            $fileSize,
            'B',
            formatFileSize(
                $fileSize
            ),
            'Auto',
            2
        );

        $message =
            'File analyzed successfully.';

        $messageType =
            'success';
    } catch (Exception $exception) {

        $message =
            $exception->getMessage();

        $messageType =
            'error';
    }
}


/*
|--------------------------------------------------------------------------
| History
|--------------------------------------------------------------------------
*/

$history =
    getConversionHistory();


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search =
    trim(
        $_GET['search'] ?? ''
    );

$filteredHistory =
    $history;

if ($search !== '') {

    $filteredHistory =
        array_filter(
            $history,
            function ($item) use ($search) {

                $text =
                    implode(
                        ' ',
                        [
                            $item['value'] ?? '',
                            $item['from'] ?? '',
                            $item['result'] ?? '',
                            $item['to'] ?? '',
                            $item['created_at'] ?? ''
                        ]
                    );

                return stripos(
                    $text,
                    $search
                ) !== false;
            }
        );
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalConversions =
    count($history);


/*
|--------------------------------------------------------------------------
| Theme
|--------------------------------------------------------------------------
*/

$theme =
    $_COOKIE['converter_theme'] ?? 'light';

if (
    !in_array(
        $theme,
        ['light', 'dark'],
        true
    )
) {
    $theme = 'light';
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        PHP Data Size Converter
    </title>

    <link
        rel="stylesheet"
        href="style.css">

</head>


<body class="<?= e($theme) ?>">

    <div class="container">


        <!-- HEADER -->

        <header class="header">

            <div class="header-top">

                <div class="brand-area">

                    <div class="brand-icon">
                        ⚡
                    </div>

                    <div>

                        <div class="badge">
                            PHP UTILITY
                        </div>

                        <div class="brand-name">
                            Data Tools
                        </div>

                    </div>

                </div>


                <button
                    type="button"
                    class="theme-btn"
                    onclick="toggleTheme()"
                    id="themeButton">

                    <span class="theme-icon">
                        <?= $theme === 'dark'
                            ? '☀️'
                            : '🌙'
                        ?>
                    </span>

                    <span class="theme-text">

                        <?= $theme === 'dark'
                            ? 'Light'
                            : 'Dark'
                        ?>

                    </span>

                </button>

            </div>


            <div class="hero-content">

                <div class="hero-icon">
                    🔄
                </div>

                <h1>
                    Data Size Converter
                </h1>

                <p>
                    Convert data between B, KB, MB, GB, TB and PB,
                    analyze files and manage your conversion history.
                </p>

            </div>

        </header>


        <!-- MESSAGE -->

        <?php if ($message): ?>

            <div
                class="alert <?= e($messageType) ?>">

                <span class="alert-icon">

                    <?= $messageType === 'success'
                        ? '✓'
                        : '!'
                    ?>

                </span>

                <span>
                    <?= e($message) ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- STATISTICS -->

        <div class="stats">

            <div class="stat-card">

                <div class="stat-icon purple">
                    📐
                </div>

                <div>

                    <span>
                        Supported Units
                    </span>

                    <strong>
                        6
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon blue">
                    📊
                </div>

                <div>

                    <span>
                        History Records
                    </span>

                    <strong>
                        <?= count($history) ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon green">
                    📁
                </div>

                <div>

                    <span>
                        Max File Size
                    </span>

                    <strong>
                        100 MB
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon orange">
                    ⚡
                </div>

                <div>

                    <span>
                        Total Conversions
                    </span>

                    <strong>
                        <?= $totalConversions ?>
                    </strong>

                </div>

            </div>

        </div>


        <!-- CONVERTER -->

        <section class="card converter-card">

            <div class="section-title">

                <div class="section-icon purple-bg">
                    🔄
                </div>

                <div>

                    <h2>
                        Multi-Unit Converter
                    </h2>

                    <p>
                        Convert data sizes between different units.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                id="converterForm">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>">

                <div class="converter-grid">


                    <!-- VALUE -->

                    <div class="form-group value-group">

                        <label for="value">
                            Value
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon">
                                #
                            </span>

                            <input
                                type="number"
                                id="value"
                                name="value"
                                min="0"
                                step="any"
                                placeholder="Enter value"
                                required>

                        </div>

                    </div>


                    <!-- MODE -->

                    <div class="form-group mode-group">

                        <label>
                            Mode
                        </label>

                        <div class="mode-toggle">

                            <button
                                type="button"
                                class="mode-btn active"
                                data-mode="binary"
                                onclick="setMode('binary')">

                                Binary (1024)

                            </button>

                            <button
                                type="button"
                                class="mode-btn"
                                data-mode="si"
                                onclick="setMode('si')">

                                SI (1000)

                            </button>

                        </div>

                        <input
                            type="hidden"
                            name="conversion_mode"
                            id="conversion_mode"
                            value="binary">

                    </div>


                    <!-- FROM -->

                    <div class="form-group">

                        <label for="from_unit">
                            From
                        </label>

                        <select
                            name="from_unit"
                            id="from_unit">

                            <?php foreach (
                                $units as $unit => $multiplier
                            ): ?>

                                <option
                                    value="<?= e($unit) ?>">

                                    <?= e($unit) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SWAP -->

                    <div class="swap-wrapper">

                        <button
                            type="button"
                            class="swap-button"
                            onclick="swapUnits()"
                            title="Swap From and To">

                            <span>
                                ⇄
                            </span>

                        </button>

                    </div>


                    <!-- TO -->

                    <div class="form-group">

                        <label for="to_unit">
                            To
                        </label>

                        <select
                            name="to_unit"
                            id="to_unit">

                            <?php foreach (
                                $units as $unit => $multiplier
                            ): ?>

                                <option
                                    value="<?= e($unit) ?>"
                                    <?= $unit === 'KB'
                                        ? 'selected'
                                        : ''
                                    ?>>

                                    <?= e($unit) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- PRESETS -->

                    <div class="form-group presets-group">

                        <label>
                            Quick Presets
                        </label>

                        <div class="presets-list">

                            <button
                                type="button"
                                class="preset-btn"
                                onclick="applyPreset(1, 'GB', 'MB')">

                                1 GB = ?

                            </button>

                            <button
                                type="button"
                                class="preset-btn"
                                onclick="applyPreset(100, 'MB', 'KB')">

                                100 MB = ?

                            </button>

                            <button
                                type="button"
                                class="preset-btn"
                                onclick="applyPreset(1, 'TB', 'GB')">

                                1 TB = ?

                            </button>

                            <button
                                type="button"
                                class="preset-btn"
                                onclick="applyPreset(500, 'MB', 'B')">

                                500 MB = ?

                            </button>

                        </div>

                    </div>


                    <!-- PRECISION -->

                    <div class="form-group precision-group">

                        <label for="precision">
                            Decimal Precision
                        </label>

                        <select
                            name="precision"
                            id="precision">

                            <option value="2">
                                2 decimals
                            </option>

                            <option value="4">
                                4 decimals
                            </option>

                            <option
                                value="6"
                                selected>

                                6 decimals
                            </option>

                            <option value="8">
                                8 decimals
                            </option>

                        </select>

                    </div>

                </div>


                <button
                    type="submit"
                    name="convert"
                    class="primary-btn">

                    <span>
                        ⚡
                    </span>

                    Convert Data Size

                </button>

            </form>


            <?php if ($conversionResult): ?>

                <div
                    class="result-box"
                    id="conversionResult">

                    <div class="result-left">

                        <span class="result-label">
                            CONVERSION RESULT
                        </span>

                        <strong id="resultText">

                            <?= e(
                                $conversionResult['value']
                            ) ?>

                            <?= e(
                                $conversionResult['from']
                            ) ?>

                            <span class="result-equals">
                                =
                            </span>

                            <?= e(
                                $conversionResult['result']
                            ) ?>

                            <?= e(
                                $conversionResult['to']
                            ) ?>

                        </strong>

                    </div>


                    <button
                        type="button"
                        class="copy-btn"
                        onclick="copyResult()">

                        <span>
                            📋
                        </span>

                        <span class="copy-text">
                            Copy Result
                        </span>

                    </button>

                </div>


                <?php if ($allUnitsResult): ?>

                    <div class="all-units-panel" id="allUnitsPanel">

                        <h3>
                            All Units
                        </h3>

                        <div class="all-units-grid">

                            <?php foreach (
                                $allUnitsResult
                                as $unit => $val
                            ): ?>

                                <div class="unit-chip">

                                    <span class="unit-label">
                                        <?= e($unit) ?>
                                    </span>

                                    <strong>
                                        <?= e(
                                            $val !== null
                                                ? number_format(
                                                    $val,
                                                    $precision
                                                )
                                                : '—'
                                        ) ?>
                                    </strong>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </section>


        <!-- COMPARISON TOOL -->

        <section class="card compare-card">

            <div class="section-title">

                <div class="section-icon orange-bg">
                    ⚖️
                </div>

                <div>

                    <h2>
                        Size Comparison
                    </h2>

                    <p>
                        Compare two data sizes side-by-side.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                id="compareForm">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>">

                <div class="compare-grid">

                    <div class="form-group">

                        <label for="compare_value_a">
                            Size A
                        </label>

                        <div class="compare-inputs">

                            <input
                                type="number"
                                id="compare_value_a"
                                name="compare_value_a"
                                min="0"
                                step="any"
                                placeholder="Value"
                                required>

                            <select
                                name="compare_unit_a"
                                id="compare_unit_a">

                                <?php foreach (
                                    $units as $unit => $multiplier
                                ): ?>

                                    <option
                                        value="<?= e($unit) ?>">

                                        <?= e($unit) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <div class="compare-divider">
                        <span>VS</span>
                    </div>


                    <div class="form-group">

                        <label for="compare_value_b">
                            Size B
                        </label>

                        <div class="compare-inputs">

                            <input
                                type="number"
                                id="compare_value_b"
                                name="compare_value_b"
                                min="0"
                                step="any"
                                placeholder="Value"
                                required>

                            <select
                                name="compare_unit_b"
                                id="compare_unit_b">

                                <?php foreach (
                                    $units as $unit => $multiplier
                                ): ?>

                                    <option
                                        value="<?= e($unit) ?>">

                                        <?= e($unit) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>

                </div>


                <button
                    type="submit"
                    name="compare"
                    class="primary-btn compare-btn">

                    <span>
                        ⚖️
                    </span>

                    Compare Sizes

                </button>

            </form>


            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['compare'])): ?>

                <?php
                $compareA = $_POST['compare_value_a'] ?? '';
                $compareUnitA = $_POST['compare_unit_a'] ?? 'B';
                $compareB = $_POST['compare_value_b'] ?? '';
                $compareUnitB = $_POST['compare_unit_b'] ?? 'B';

                $compareResultA = null;
                $compareResultB = null;
                $compareWinner = '';

                if (
                    is_numeric($compareA) &&
                    is_numeric($compareB) &&
                    (float) $compareA >= 0 &&
                    (float) $compareB >= 0
                ) {
                    $compareResultA = convertDataSize(
                        $compareA,
                        $compareUnitA,
                        'B',
                        2,
                        $isBinary
                    );

                    $compareResultB = convertDataSize(
                        $compareB,
                        $compareUnitB,
                        'B',
                        2,
                        $isBinary
                    );

                    if ($compareResultA > $compareResultB) {
                        $compareWinner = 'A';
                    } elseif ($compareResultB > $compareResultA) {
                        $compareWinner = 'B';
                    } else {
                        $compareWinner = 'tie';
                    }
                }
                ?>


                <?php if ($compareResultA !== null && $compareResultB !== null): ?>

                    <div class="compare-result">

                        <div class="compare-item <?= $compareWinner === 'A' ? 'winner' : '' ?>">

                            <span class="compare-label">
                                Size A
                            </span>

                            <strong>
                                <?= e($compareA) ?> <?= e($compareUnitA) ?>
                            </strong>

                            <span class="compare-bytes">
                                <?= e(number_format($compareResultA, 2)) ?> B
                            </span>

                        </div>


                        <div class="compare-vs">
                            <span>VS</span>
                        </div>


                        <div class="compare-item <?= $compareWinner === 'B' ? 'winner' : '' ?>">

                            <span class="compare-label">
                                Size B
                            </span>

                            <strong>
                                <?= e($compareB) ?> <?= e($compareUnitB) ?>
                            </strong>

                            <span class="compare-bytes">
                                <?= e(number_format($compareResultB, 2)) ?> B
                            </span>

                        </div>

                    </div>

                    <?php if ($compareWinner === 'tie'): ?>

                        <div class="compare-tie">
                            Both sizes are equal.
                        </div>

                    <?php elseif ($compareWinner === 'A'): ?>

                        <div class="compare-conclusion">
                            Size A is larger.
                        </div>

                    <?php else: ?>

                        <div class="compare-conclusion">
                            Size B is larger.
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            <?php endif; ?>

        </section>


        <!-- FILE ANALYZER -->

        <section class="card">

            <div class="section-title">

                <div class="section-icon blue-bg">
                    📁
                </div>

                <div>

                    <h2>
                        File Size Analyzer
                    </h2>

                    <p>
                        Upload a file and automatically calculate its size.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>">


                <div class="upload-area" id="uploadArea">

                    <div class="upload-icon">
                        📤
                    </div>

                    <h3>
                        Select or drop a file
                    </h3>

                    <p>
                        Maximum allowed size: <strong>100 MB</strong>
                    </p>

                    <input
                        type="file"
                        name="file"
                        id="file"
                        required
                        onchange="handleFileSelect(this)">

                </div>


                <button
                    type="submit"
                    name="analyze_file"
                    class="primary-btn analyzer-btn">

                    <span>
                        🔍
                    </span>

                    Analyze File

                </button>

            </form>


            <?php if ($fileResult): ?>

                <div class="file-result">

                    <div class="file-header">

                        <div>

                            <span class="small-label">
                                FILE NAME
                            </span>

                            <h3>
                                <?= e(
                                    $fileResult['name']
                                ) ?>
                            </h3>

                        </div>

                        <div class="file-size">
                            <?= e(
                                $fileResult['formatted']
                            ) ?>
                        </div>

                    </div>


                    <div class="file-grid">

                        <div>
                            <span>Bytes</span>
                            <strong>
                                <?= e(
                                    $fileResult['size_bytes']
                                ) ?>
                                B
                            </strong>
                        </div>

                        <div>
                            <span>Kilobytes</span>
                            <strong>
                                <?= e(
                                    $fileResult['kb']
                                ) ?>
                                KB
                            </strong>
                        </div>

                        <div>
                            <span>Megabytes</span>
                            <strong>
                                <?= e(
                                    $fileResult['mb']
                                ) ?>
                                MB
                            </strong>
                        </div>

                        <div>
                            <span>Gigabytes</span>
                            <strong>
                                <?= e(
                                    $fileResult['gb']
                                ) ?>
                                GB
                            </strong>
                        </div>

                        <div>
                            <span>Terabytes</span>
                            <strong>
                                <?= e(
                                    $fileResult['tb']
                                ) ?>
                                TB
                            </strong>
                        </div>

                        <div>
                            <span>Petabytes</span>
                            <strong>
                                <?= e(
                                    $fileResult['pb']
                                ) ?>
                                PB
                            </strong>
                        </div>

                        <div>
                            <span>Bits</span>
                            <strong>
                                <?= e(
                                    $fileResult['bits']
                                ) ?>
                                b
                            </strong>
                        </div>

                        <div>
                            <span>Kilobits</span>
                            <strong>
                                <?= e(
                                    $fileResult['Kb']
                                ) ?>
                                Kb
                            </strong>
                        </div>

                        <div>
                            <span>Megabits</span>
                            <strong>
                                <?= e(
                                    $fileResult['Mb']
                                ) ?>
                                Mb
                            </strong>
                        </div>

                        <div>
                            <span>Gigabits</span>
                            <strong>
                                <?= e(
                                    $fileResult['Gb']
                                ) ?>
                                Gb
                            </strong>
                        </div>

                        <div>
                            <span>File Extension</span>
                            <strong>
                                <?= e(
                                    $fileResult['extension']
                                ) ?>
                            </strong>
                        </div>

                        <div>
                            <span>MIME Type</span>
                            <strong>
                                <?= e(
                                    $fileResult['type']
                                ) ?>
                            </strong>
                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </section>


        <!-- HISTORY -->

        <section class="card history-card">

            <div class="history-top">

                <div class="section-title history-title">

                    <div class="section-icon green-bg">
                        📊
                    </div>

                    <div>

                        <h2>
                            Conversion History
                        </h2>

                        <p>
                            Latest conversion and file analysis records.
                        </p>

                    </div>

                </div>


                <?php if (!empty($history)): ?>

                    <div class="history-actions">

                        <!-- SELECT ALL -->

                        <label class="select-all-label">

                            <input
                                type="checkbox"
                                id="selectAllHistory"
                                onchange="toggleSelectAll(this)">

                            <span>
                                Select All
                            </span>

                        </label>


                        <!-- JSON -->

                        <form
                            method="POST"
                            class="csv-form"
                            id="jsonExportForm">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(
                                            $_SESSION['csrf_token']
                                        ) ?>">

                            <input
                                type="hidden"
                                name="download_json"
                                value="1">

                            <button
                                type="submit"
                                name="download_json"
                                class="csv-btn json-btn">

                                <span>
                                    📥
                                </span>

                                <span>
                                    Download JSON
                                </span>

                            </button>

                        </form>


                        <!-- CSV -->

                        <form
                            method="POST"
                            class="csv-form">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(
                                            $_SESSION['csrf_token']
                                        ) ?>">

                            <button
                                type="submit"
                                name="download_csv"
                                class="csv-btn">

                                <span>
                                    📥
                                </span>

                                <span>
                                    Download CSV
                                </span>

                            </button>

                        </form>


                        <!-- BULK DELETE -->

                        <form
                            method="POST"
                            id="bulkDeleteForm"
                            onsubmit="return confirmBulkDelete();">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(
                                            $_SESSION['csrf_token']
                                        ) ?>">

                            <input
                                type="hidden"
                                name="bulk_delete_history"
                                value="1">

                            <button
                                type="submit"
                                class="clear-btn bulk-delete-btn"
                                id="bulkDeleteBtn"
                                disabled>

                                <span>
                                    🗑️
                                </span>

                                <span>
                                    Delete Selected
                                </span>

                            </button>

                        </form>


                        <!-- CLEAR -->

                        <a
                            href="clear-history.php"
                            class="clear-btn"
                            onclick="return confirm('Clear all conversion history?');">

                            <span>
                                🗑️
                            </span>

                            <span>
                                Clear History
                            </span>

                        </a>

                    </div>

                <?php endif; ?>

            </div>


            <?php if (!empty($history)): ?>

                <!-- SEARCH -->

                <div class="history-toolbar">

                    <form
                        method="GET"
                        class="search-form">

                        <div class="search-input-wrapper">

                            <span class="search-icon">
                                🔎
                            </span>

                            <input
                                type="search"
                                name="search"
                                value="<?= e($search) ?>"
                                placeholder="Search history...">

                        </div>


                        <button
                            type="submit"
                            class="search-btn">

                            Search

                        </button>


                        <?php if ($search !== ''): ?>

                            <a
                                href="index.php"
                                class="reset-btn">

                                ✕ Reset

                            </a>

                        <?php endif; ?>

                    </form>

                </div>

            <?php endif; ?>


            <?php if (empty($filteredHistory)): ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        📭
                    </div>

                    <h3>
                        No history found
                    </h3>

                    <p>

                        <?php if ($search !== ''): ?>

                            No matching history found.

                        <?php else: ?>

                            No conversion history yet.

                        <?php endif; ?>

                    </p>

                </div>


            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                                <tr>

                                    <th>

                                        <input
                                            type="checkbox"
                                            id="historySelectAll"
                                            onchange="toggleSelectAll(this)">

                                    </th>

                                    <th>
                                        #
                                    </th>

                                <th>
                                    Value
                                </th>

                                <th>
                                    Result
                                </th>

                                <th>
                                    Date & Time
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody class="history-table-body">

                            <?php
                            $rowNumber = 1;
                            ?>

                            <?php foreach (
                                $filteredHistory
                                as $item
                            ): ?>

                            <tr>

                                <td>

                                    <input
                                        type="checkbox"
                                        class="history-checkbox"
                                        name="history_ids[]"
                                        value="<?= e(
                                                    $item['id'] ?? ''
                                                ) ?>"
                                        onchange="updateBulkDeleteBtn()">

                                </td>

                                <td>

                                    <span class="number-badge">

                                        <?= $rowNumber++ ?>

                                    </span>

                                </td>


                                    <td>

                                        <strong>
                                            <?= e(
                                                $item['value'] ?? ''
                                            ) ?>
                                        </strong>

                                        <span class="unit-text">

                                            <?= e(
                                                $item['from'] ?? ''
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="result-pill">

                                            <?= e(
                                                $item['result'] ?? ''
                                            ) ?>

                                            <?= e(
                                                $item['to'] ?? ''
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="date-text">

                                            🕒

                                            <?= e(
                                                $item['created_at'] ?? ''
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Delete this record?');">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e(
                                                            $_SESSION['csrf_token']
                                                        ) ?>">

                                            <input
                                                type="hidden"
                                                name="history_id"
                                                value="<?= e(
                                                            $item['id'] ?? ''
                                                        ) ?>">

                                            <button
                                                type="submit"
                                                name="delete_history"
                                                class="delete-btn"
                                                title="Delete record">

                                                🗑️

                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>


        <!-- FOOTER -->

        <footer>

            <div class="footer-logo">
                ⚡ PHP Data Size Converter
            </div>

            <span>
                1024-based binary conversion
                • Pure PHP
                • XAMPP
            </span>

        </footer>


    </div>


    <script>
        /*
        |--------------------------------------------------------------------------
        | Mode Toggle
        |--------------------------------------------------------------------------
        */

        function setMode(mode) {
            const hidden =
                document.getElementById('conversion_mode');

            if (hidden) {
                hidden.value = mode;
            }

            document.querySelectorAll('.mode-btn').forEach(function(btn) {
                btn.classList.toggle(
                    'active',
                    btn.dataset.mode === mode
                );
            });
        }


        /*
        |--------------------------------------------------------------------------
        | Swap Units
        |--------------------------------------------------------------------------
        */

        function swapUnits() {
            const from =
                document.getElementById('from_unit');

            const to =
                document.getElementById('to_unit');

            const temporary =
                from.value;

            from.value =
                to.value;

            to.value =
                temporary;

            const button =
                document.querySelector('.swap-button');

            if (button) {

                button.classList.add('swapping');

                setTimeout(function() {

                    button.classList.remove(
                        'swapping'
                    );

                }, 350);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Presets
        |--------------------------------------------------------------------------
        */

        function applyPreset(value, from, to) {
            const valueInput =
                document.getElementById('value');

            const fromSelect =
                document.getElementById('from_unit');

            const toSelect =
                document.getElementById('to_unit');

            if (valueInput) {
                valueInput.value = value;
            }

            if (fromSelect) {
                fromSelect.value = from;
            }

            if (toSelect) {
                toSelect.value = to;
            }

            const form =
                document.getElementById('converterForm');

            if (form) {
                form.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Copy Result
        |--------------------------------------------------------------------------
        */

        function copyResult() {
            const result =
                document.getElementById(
                    'resultText'
                );

            if (!result) {
                return;
            }

            const text =
                result.innerText
                .replace(/\s+/g, ' ')
                .trim();

            const button =
                document.querySelector(
                    '.copy-btn'
                );

            navigator.clipboard.writeText(text)
                .then(function() {

                    if (button) {

                        const original =
                            button.innerHTML;

                        button.innerHTML =
                            '✓ Copied!';

                        button.classList.add(
                            'copied'
                        );

                        setTimeout(function() {

                            button.innerHTML =
                                original;

                            button.classList.remove(
                                'copied'
                            );

                        }, 1800);
                    }

                })
                .catch(function() {

                    alert(
                        'Unable to copy result.'
                    );

                });
        }


        function handleFileSelect(input) {
            if (input.files.length > 0) {
                updateUploadLabel(input.files[0].name);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Drag and Drop File Upload
        |--------------------------------------------------------------------------
        */

        function setupDragDrop() {
            const area =
                document.getElementById('uploadArea');

            if (!area) {
                return;
            }

            const fileInput =
                document.getElementById('file');

            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                area.classList.add('drag-over');
            });

            area.addEventListener('dragleave', function(e) {
                e.preventDefault();
                area.classList.remove('drag-over');
            });

            area.addEventListener('drop', function(e) {
                e.preventDefault();
                area.classList.remove('drag-over');

                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    updateUploadLabel(e.dataTransfer.files[0].name);
                }
            });

            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        updateUploadLabel(this.files[0].name);
                    }
                });
            }
        }

        function updateUploadLabel(name) {
            const area =
                document.getElementById('uploadArea');

            if (!area) {
                return;
            }

            const h3 = area.querySelector('h3');

            if (h3) {
                h3.textContent = name;
            }
        }

        function handleFileSelect(input) {
            if (input.files.length > 0) {
                updateUploadLabel(input.files[0].name);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Bulk Delete
        |--------------------------------------------------------------------------
        */

        function toggleSelectAll(checkbox) {
            const checkboxes =
                document.querySelectorAll('.history-checkbox');

            checkboxes.forEach(function(cb) {
                cb.checked = checkbox.checked;
            });

            updateBulkDeleteBtn();
        }

        function updateBulkDeleteBtn() {
            const btn =
                document.getElementById('bulkDeleteBtn');

            const checkboxes =
                document.querySelectorAll('.history-checkbox:checked');

            if (btn) {
                btn.disabled = checkboxes.length === 0;
            }
        }

        function confirmBulkDelete() {
            const checkboxes =
                document.querySelectorAll('.history-checkbox:checked');

            if (checkboxes.length === 0) {
                return false;
            }

            return confirm(
                'Delete ' + checkboxes.length + ' selected record(s)?'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LocalStorage History Sync
        |--------------------------------------------------------------------------
        */

        function syncHistoryToStorage() {
            const data = <?= json_encode($history ?? []) ?>;

            try {
                localStorage.setItem(
                    'conversion_history',
                    JSON.stringify(data)
                );
            } catch (e) {
                // ignore storage errors
            }
        }

        function loadHistoryFromStorage() {
            try {
                const raw =
                    localStorage.getItem('conversion_history');

                if (!raw) {
                    return [];
                }

                return JSON.parse(raw);
            } catch (e) {
                return [];
            }
        }

        function renderLocalStorageHistory() {
            const history =
                loadHistoryFromStorage();

            const tbody =
                document.querySelector('.history-table-body');

            if (!tbody || history.length === 0) {
                return;
            }

            const existingRows =
                tbody.querySelectorAll('tr');

            if (existingRows.length > 0) {
                return;
            }

            const emptyState =
                document.querySelector('.empty-state');

            if (emptyState) {
                emptyState.style.display = 'none';
            }

            tbody.innerHTML = '';

            history.forEach(function(item, index) {
                const row = document.createElement('tr');

                row.innerHTML = '\
                        <td>\
                            <input\
                                type="checkbox"\
                                class="history-checkbox"\
                                name="history_ids[]"\
                                value="' + escapeHtml(item.id || '') + '"\
                                onchange="updateBulkDeleteBtn()">\
                        </td>\
                        <td>\
                            <span class="number-badge">' + (index + 1) + '</span>\
                        </td>\
                        <td>\
                            <strong>' + escapeHtml(item.value || '') + '</strong>\
                            <span class="unit-text">' + escapeHtml(item.from || '') + '</span>\
                        </td>\
                        <td>\
                            <span class="result-pill">' + escapeHtml(item.result || '') + ' ' + escapeHtml(item.to || '') + '</span>\
                        </td>\
                        <td>\
                            <span class="date-text">\
                                🕒\
                                ' + escapeHtml(item.created_at || '') + '\
                            </span>\
                        </td>\
                        <td>\
                            <form\
                                method="POST"\
                                onsubmit="return confirm(\'Delete this record?\');">\
                                <input\
                                    type="hidden"\
                                    name="csrf_token"\
                                    value="<?= e($_SESSION['csrf_token']) ?>">\
                                <input\
                                    type="hidden"\
                                    name="history_id"\
                                    value="' + escapeHtml(item.id || '') + '">\
                                <button\
                                    type="submit"\
                                    name="delete_history"\
                                    class="delete-btn"\
                                    title="Delete record">\
                                    🗑️\
                                </button>\
                            </form>\
                        </td>\
                    ';

                tbody.appendChild(row);
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }


        /*
        |--------------------------------------------------------------------------
        | Dark Mode
        |--------------------------------------------------------------------------
        */

        function toggleTheme() {
            const body =
                document.body;

            const isDark =
                body.classList.contains(
                    'dark'
                );

            const newTheme =
                isDark ?
                'light' :
                'dark';

            body.classList.remove(
                'light',
                'dark'
            );

            body.classList.add(
                newTheme
            );

            document.cookie =
                'converter_theme=' +
                newTheme +
                '; path=/; max-age=31536000';

            const icon =
                document.querySelector(
                    '.theme-icon'
                );

            const text =
                document.querySelector(
                    '.theme-text'
                );

            if (icon) {

                icon.innerText =
                    newTheme === 'dark' ?
                    '☀️' :
                    '🌙';
            }

            if (text) {

                text.innerText =
                    newTheme === 'dark' ?
                    'Light' :
                    'Dark';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Init
        |--------------------------------------------------------------------------
        */

        document.addEventListener('DOMContentLoaded', function() {
            setupDragDrop();
            syncHistoryToStorage();
            renderLocalStorageHistory();
        });
    </script>


</body>

</html>