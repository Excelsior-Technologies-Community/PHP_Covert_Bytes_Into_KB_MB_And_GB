<?php

session_start();

/*
|--------------------------------------------------------------------------
| Clear conversion history
|--------------------------------------------------------------------------
*/

$_SESSION['conversion_history'] = [];

header('Location: index.php');

exit;