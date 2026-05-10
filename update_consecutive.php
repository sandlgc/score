<?php
session_start();

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'increase') {
        if (!isset($_SESSION['consecutive_correct'])) {
            $_SESSION['consecutive_correct'] = 0;
        }
        $_SESSION['consecutive_correct']++;
        echo $_SESSION['consecutive_correct'];
    } elseif ($_GET['action'] === 'reset') {
        $_SESSION['consecutive_correct'] = 0;
        echo $_SESSION['consecutive_correct'];
    }
}
?>