<?php
session_start();
if (isset($_GET['correctCount'])) {
    $_SESSION['correct_count'] = $_GET['correctCount'];
}
?>