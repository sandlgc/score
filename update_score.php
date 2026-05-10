<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['addScore'])) {
    $addScore = floatval($_GET['addScore']);
    $_SESSION['score'] += $addScore;
    echo number_format($_SESSION['score'], 1);
} else {
    $_SESSION['score'] = 0.2;
    echo '0.2';
}
?>