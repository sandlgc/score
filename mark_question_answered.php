<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"])) {
    exit("未登录");
}

if (isset($_GET['question_id'])) {
    $questionId = $_GET['question_id'];
    
    // 查找题目并标记为已回答
    foreach ($_SESSION['questions'] as &$question) {
        if ($question['id'] == $questionId) {
            $question['answered'] = true;
            break;
        }
    }
}
?>    