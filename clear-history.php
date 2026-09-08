<?php

session_start();

unset($_SESSION['conversion_history']);

header('Location: index.php');

exit;