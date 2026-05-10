<!--
	作者：offline
	时间：2025-05-06
	描述：在答题结束时，通过 AJAX 请求调用 record_answer.php 将答题情况（包括错题号）存储到 student_answer_records 表中。
-->
<?php
session_start();
include 'config.php';

if (isset($_GET['student_id']) && isset($_GET['chapter']) && isset($_GET['answered_questions']) && isset($_GET['correct_questions']) && isset($_GET['consecutive_correct_max']) && isset($_GET['accuracy']) && isset($_GET['wrong_question_numbers'])) {
    $student_id = $_GET['student_id'];
    $chapter = $_GET['chapter'];
    $answered_questions = $_GET['answered_questions'];
    $correct_questions = $_GET['correct_questions'];
    $consecutive_correct_max = $_GET['consecutive_correct_max'];
    $accuracy = $_GET['accuracy'];
    $wrong_question_numbers = $_GET['wrong_question_numbers'];

    $sql = "INSERT INTO student_answer_records (student_id, chapter, answered_questions, correct_questions, consecutive_correct_max, accuracy, wrong_question_numbers) VALUES ('$student_id', '$chapter', '$answered_questions', '$correct_questions', '$consecutive_correct_max', '$accuracy', '$wrong_question_numbers')";

    if ($conn->query($sql) === TRUE) {
        // 插入成功
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>    