<?php
session_start();
if (isset($_GET['max'])) {
    $_SESSION['consecutive_correct_max'] = $_GET['max'];
    echo $_SESSION['consecutive_correct_max'];
}
?>