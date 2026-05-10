<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['active'])) {
        $_SESSION['bonus_active'] = $_GET['active'] === 'true';
        if ($_SESSION['bonus_active']) {
            $_SESSION['bonus_end_time'] = time() + 10; // 10秒后结束
        } else {
            $_SESSION['bonus_end_time'] = 0;
        }
    }
    
    if (isset($_GET['consecutive'])) {
        $_SESSION['consecutive_correct'] = intval($_GET['consecutive']);
    }
    
    echo json_encode(['status' => 'success']);
    exit();
}