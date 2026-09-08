<?php

session_start();

/**
 * Available storage units.
 */
$units = [
    'B'  => 1,
    'KB' => 1024,
    'MB' => 1024 ** 2,
    'GB' => 1024 ** 3,
    'TB' => 1024 ** 4,
    'PB' => 1024 ** 5,
];

/**
 * Convert bytes to the most suitable unit.
 */
function formatBytes($bytes, $precision = 2)
{
    if ($bytes < 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $index = 0;

    while ($bytes >= 1024 && $index < count($units) - 1) {
        $bytes /= 1024;
        $index++;
    }

    return round($bytes, $precision) . ' ' . $units[$index];
}

/**
 * Escape output safely.
 */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Store conversion in session history.
 */
function addToHistory($value, $from, $result, $to)
{
    if (!isset($_SESSION['conversion_history'])) {
        $_SESSION['conversion_history'] = [];
    }

    array_unshift($_SESSION['conversion_history'], [
        'value'       => $value,
        'from'        => $from,
        'result'      => $result,
        'to'          => $to,
        'created_at'  => date('d M Y, h:i A'),
    ]);

    // Keep only latest 20 records.
    $_SESSION['conversion_history'] = array_slice(
        $_SESSION['conversion_history'],
        0,
        20
    );
}

$message = '';
$messageType = '';

$conversionResult = null;

/**
 * Multi-unit conversion.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert'])) {

    $value = $_POST['value'] ?? '';
    $from = $_POST['from_unit'] ?? 'B';
    $to = $_POST['to_unit'] ?? 'KB';

    if (!is_numeric($value) || $value < 0) {

        $message = 'Please enter a valid positive number.';
        $messageType = 'error';

    } elseif (!isset($units[$from]) || !isset($units[$to])) {

        $message = 'Invalid conversion unit selected.';
        $messageType = 'error';

    } else {

        $bytes = (float) $value * $units[$from];
        $result = $bytes / $units[$to];

        $conversionResult = [
            'value' => $value,
            'from' => $from,
            'result' => round($result, 6),
            'to' => $to,
        ];

        addToHistory(
            $value,
            $from,
            round($result, 6),
            $to
        );
    }
}

/**
 * File upload / analysis.
 */
$fileResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analyze_file'])) {

    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {

        $message = 'Please select a file.';
        $messageType = 'error';

    } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {

        $message = 'Unable to upload the selected file.';
        $messageType = 'error';

    } else {

        $file = $_FILES['file'];

        // Maximum file size: 100 MB.
        $maxSize = 100 * 1024 * 1024;

        if ($file['size'] > $maxSize) {

            $message = 'File is too large. Maximum allowed size is 100 MB.';
            $messageType = 'error';

        } else {

            $fileSize = $file['size'];
            $fileName = basename($file['name']);

            $fileResult = [
                'name' => $fileName,
                'size_bytes' => $fileSize,
                'formatted' => formatBytes($fileSize),
                'kb' => round($fileSize / $units['KB'], 2),
                'mb' => round($fileSize / $units['MB'], 2),
                'gb' => round($fileSize / $units['GB'], 6),
                'tb' => round($fileSize / $units['TB'], 8),
                'type' => $file['type'] ?: 'Unknown',
            ];

            addToHistory(
                $fileSize,
                'B',
                formatBytes($fileSize),
                'Auto'
            );

            $message = 'File analyzed successfully.';
            $messageType = 'success';
        }
    }
}

$history = $_SESSION['conversion_history'] ?? [];

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>PHP Data Size Converter</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>

<body>

<div class="container">

    <!-- Header -->

    <header class="header">

        <div class="badge">
            PHP Utility
        </div>

        <h1>
            Data Size Converter
        </h1>

        <p>
            Convert bytes between B, KB, MB, GB, TB and PB,
            analyze files and keep track of your conversions.
        </p>

    </header>


    <!-- Message -->

    <?php if ($message): ?>

        <div class="alert <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <!-- Statistics -->

    <div class="stats">

        <div class="stat-card">
            <span>Supported Units</span>
            <strong>6</strong>
        </div>

        <div class="stat-card">
            <span>History Records</span>
            <strong><?= count($history) ?></strong>
        </div>

        <div class="stat-card">
            <span>Max File Size</span>
            <strong>100 MB</strong>
        </div>

    </div>


    <!-- Multi Unit Converter -->

    <section class="card">

        <div class="section-title">

            <div class="icon">
                🔄
            </div>

            <div>
                <h2>Multi-Unit Converter</h2>
                <p>Convert data sizes between different units.</p>
            </div>

        </div>


        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label for="value">
                        Value
                    </label>

                    <input
                        type="number"
                        id="value"
                        name="value"
                        min="0"
                        step="any"
                        placeholder="Enter value"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="from_unit">
                        From
                    </label>

                    <select
                        name="from_unit"
                        id="from_unit"
                    >

                        <?php foreach ($units as $unit => $multiplier): ?>

                            <option value="<?= e($unit) ?>">
                                <?= e($unit) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="swap-box">
                    ⇄
                </div>


                <div class="form-group">

                    <label for="to_unit">
                        To
                    </label>

                    <select
                        name="to_unit"
                        id="to_unit"
                    >

                        <?php foreach ($units as $unit => $multiplier): ?>

                            <option
                                value="<?= e($unit) ?>"
                                <?= $unit === 'KB' ? 'selected' : '' ?>
                            >
                                <?= e($unit) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <button
                type="submit"
                name="convert"
                class="primary-btn"
            >
                Convert Data Size
            </button>

        </form>


        <?php if ($conversionResult): ?>

            <div class="result-box">

                <span>Conversion Result</span>

                <strong>

                    <?= e($conversionResult['value']) ?>

                    <?= e($conversionResult['from']) ?>

                    =

                    <?= e($conversionResult['result']) ?>

                    <?= e($conversionResult['to']) ?>

                </strong>

            </div>

        <?php endif; ?>

    </section>


    <!-- File Analyzer -->

    <section class="card">

        <div class="section-title">

            <div class="icon">
                📁
            </div>

            <div>
                <h2>File Size Analyzer</h2>
                <p>Upload a file and automatically calculate its size.</p>
            </div>

        </div>


        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="upload-area">

                <div class="upload-icon">
                    📤
                </div>

                <h3>
                    Select a file
                </h3>

                <p>
                    Maximum allowed size: 100 MB
                </p>

                <input
                    type="file"
                    name="file"
                    id="file"
                    required
                >

            </div>


            <button
                type="submit"
                name="analyze_file"
                class="primary-btn"
            >
                Analyze File
            </button>

        </form>


        <?php if ($fileResult): ?>

            <div class="file-result">

                <div class="file-header">

                    <div>

                        <span class="small-label">
                            File Name
                        </span>

                        <h3>
                            <?= e($fileResult['name']) ?>
                        </h3>

                    </div>

                    <div class="file-size">

                        <?= e($fileResult['formatted']) ?>

                    </div>

                </div>


                <div class="file-grid">

                    <div>
                        <span>Bytes</span>
                        <strong>
                            <?= e($fileResult['size_bytes']) ?> B
                        </strong>
                    </div>

                    <div>
                        <span>Kilobytes</span>
                        <strong>
                            <?= e($fileResult['kb']) ?> KB
                        </strong>
                    </div>

                    <div>
                        <span>Megabytes</span>
                        <strong>
                            <?= e($fileResult['mb']) ?> MB
                        </strong>
                    </div>

                    <div>
                        <span>Gigabytes</span>
                        <strong>
                            <?= e($fileResult['gb']) ?> GB
                        </strong>
                    </div>

                    <div>
                        <span>Terabytes</span>
                        <strong>
                            <?= e($fileResult['tb']) ?> TB
                        </strong>
                    </div>

                    <div>
                        <span>MIME Type</span>
                        <strong>
                            <?= e($fileResult['type']) ?>
                        </strong>
                    </div>

                </div>

            </div>

        <?php endif; ?>

    </section>


    <!-- History -->

    <section class="card">

        <div class="section-title history-heading">

            <div class="icon">
                📊
            </div>

            <div>

                <h2>Conversion History</h2>

                <p>
                    Your latest conversion and file analysis records.
                </p>

            </div>

            <?php if (!empty($history)): ?>

                <a
                    href="clear-history.php"
                    class="clear-btn"
                    onclick="return confirm('Clear all conversion history?');"
                >
                    Clear History
                </a>

            <?php endif; ?>

        </div>


        <?php if (empty($history)): ?>

            <div class="empty-state">

                <div>
                    📭
                </div>

                <p>
                    No conversion history yet.
                </p>

            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                    <tr>

                        <th>#</th>
                        <th>Value</th>
                        <th>Result</th>
                        <th>Date & Time</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($history as $index => $item): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>

                                <strong>
                                    <?= e($item['value']) ?>
                                </strong>

                                <?= e($item['from']) ?>

                            </td>

                            <td>

                                <span class="result-pill">

                                    <?= e($item['result']) ?>

                                    <?= e($item['to']) ?>

                                </span>

                            </td>

                            <td>
                                <?= e($item['created_at']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>


    <footer>

        <p>
            PHP Data Size Converter
        </p>

        <span>
            1024-based binary conversion • Pure PHP • XAMPP
        </span>

    </footer>

</div>

</body>

</html>