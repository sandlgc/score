<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 获取选择的章节
$chapter_name = $_GET['chapter_name'];

// 查询该章节的所有题目
$question_sql = "SELECT id, question, option1, option2, option3, option4, answer FROM questions WHERE chapter_name = '$chapter_name'";
$question_result = $conn->query($question_sql);
$questions = [];
while ($question_row = $question_result->fetch_assoc()) {
    $questions[] = $question_row;
}

// 将题目列表存储到会话中
$_SESSION['questions'] = $questions;
$_SESSION['current_question_index'] = 0;

// 跳转到第一道题
header("Location: question.php");
exit();
?>