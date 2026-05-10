<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit();
}

if (!isset($_POST['answer']) || !isset($_POST['current_index'])) {
    echo json_encode(['success' => false, 'message' => '无效的请求']);
    exit();
}

$current_index = intval($_POST['current_index']);
$selected_answer = $_POST['answer'];

// 确保当前题目索引有效
if (!isset($_SESSION['questions'][$current_index])) {
    echo json_encode(['success' => false, 'message' => '无效的题目索引']);
    exit();
}

$current_question = $_SESSION['questions'][$current_index];
$correct_answer_key = 'option' . $current_question['answer'];
$correct_answer_value = $current_question[$correct_answer_key];
$correct_answer_position = substr($correct_answer_key, 6); // 提取 '1', '2', '3', '4'

// 验证答案
$is_correct = ($selected_answer == $correct_answer_position);

// 更新分数
$current_score = $_SESSION['score'];
if ($is_correct) {
    $_SESSION['score'] += 0.2; // 假设每题0.2分
    $new_score = $_SESSION['score'];
} else {
    $new_score = $_SESSION['score'];
}

// 准备响应
$response = [
    'success' => true,
    'is_correct' => $is_correct,
    'newScore' => $new_score,
    'correctAnswer' => $correct_answer_position,
];

// 如果是最后一题，计算正确率和准确率
$total_questions = count($_SESSION['questions']);
if ($current_index == $total_questions - 1) {
    $correct_count = $_SESSION['score'] / 0.2; // 每题0.2分
    $accuracy = ($correct_count / $total_questions) * 100;
    $response['accuracy'] = $accuracy;
} else {
    $response['accuracy'] = null; // 非最后一题不需要
}

echo json_encode($response);
?>