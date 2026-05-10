<?php
date_default_timezone_set('Asia/Shanghai'); // 设置时区	
	
session_start();
include 'config.php';

// 获取学生 ID
$student_id = $_SESSION["user_id"];
// 获取章节
$chapter = $_GET['chapter'];
// 获取正确率
$accuracy = $_GET['accuracy'];
// 获取连续答对最高记录
$consecutive_correct_max = $_GET['consecutive_correct_max'];
// 获取错题 ID
$wrong_question_ids = implode(',', json_decode($_GET['wrong_question_ids'], true));
// 获取回答题目数
$answered_questions = $_GET['answered_questions'];
// 获取答对题目数
$correct_questions = $_GET['correct_questions'];
// 获取答题时间（新增）
$answer_time = $_GET['answer_time'];
// 更新时间（字符串类型）
$actionTime = date('Y-m-d H:i:s');

// 使用预处理语句插入记录（更新SQL语句，包含新字段）
$sql = "INSERT INTO student_answer_records (student_id, chapter, accuracy, consecutive_correct_max, wrong_question_ids, answered_questions, correct_questions, answer_time, attempt_time) 
        VALUES (?,?,?,?,?,?,?,?,?)";
$stmt = $conn->prepare($sql);

// 修正：类型字符串调整为 "sssssiss"（5个字符串、3个整数、1个字符串）
$stmt->bind_param("sssssiiss", 
    $student_id,      // 字符串
    $chapter,         // 字符串
    $accuracy,        // 字符串
    $consecutive_correct_max, // 字符串
    $wrong_question_ids,      // 字符串
    $answered_questions,      // 整数
    $correct_questions,       // 整数
    $answer_time,            // 整数
    $actionTime             // 字符串（日期时间）
);

if ($stmt->execute()) {
    echo "Record inserted successfully";
} else {
    echo "Error: ". $stmt->error;
}

$stmt->close();
$conn->close();
?>