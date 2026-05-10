<?php
session_start();
include 'config.php';

$selectedAnswer = $_GET['answer'];
$current_index = $_SESSION['current_question_index'];
$current_question = $_SESSION['questions'][$current_index];

// 检查 $_SESSION['shuffled_options'] 中是否存在当前索引的元素
if (!isset($_SESSION['shuffled_options'][$current_index])) {
    // 若不存在，返回错误信息
    echo json_encode([
        'isCorrect' => false,
        'error' => 'Shuffled options not found for current question.'
    ]);
    exit;
}

// 从 $_SESSION 中获取之前存储的打乱后的选项顺序
$validOptions = $_SESSION['shuffled_options'][$current_index];

// 获取正确答案的原始键名
$correctAnswerKey = 'option' . $current_question['answer'];
// 找到正确答案在打乱后数组中的索引
$correctAnswerIndex = array_search($current_question[$correctAnswerKey], $validOptions);

if ($correctAnswerIndex === false) {
    // 如果没找到正确答案，返回错误信息
    echo json_encode([
        'isCorrect' => false,
        'error' => 'Correct answer not found in shuffled options.'
    ]);
    exit;
}

// 输出当前问题的所有选项内容，用于调试
error_log('Current question options: '. print_r($validOptions, true));

// 输出正确答案的内容
error_log('Correct answer content: '. $current_question[$correctAnswerKey]);

// 输出选中答案的索引和内容
error_log('Selected answer index: '. $selectedAnswer);
$selectedOptionContent = $validOptions[$selectedAnswer]?? 'Unknown';
error_log('Selected answer content: '. $selectedOptionContent);

$isCorrect = (string)$selectedAnswer === (string)$correctAnswerIndex;

echo json_encode([
    'isCorrect' => $isCorrect
]);
?>