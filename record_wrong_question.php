<!--
	作者：offline
	时间：2025-05-06
	描述：当学生答错题目时，通过 AJAX 请求调用 record_wrong_question.php 记录错题号。
-->
<?php
session_start();
include 'config.php';

if (isset($_GET['questionId'])) {
    $questionId = $_GET['questionId'];
    if (!in_array($questionId, $_SESSION['wrong_question_ids'])) {
        $_SESSION['wrong_question_ids'][] = $questionId;
    }
}
?>    