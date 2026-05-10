<!--
	作者：offline
	时间：2025-05-08
	描述：update_wrong_question_ids.php 来更新 PHP 会话中的错题 ID 数组
-->
<?php
session_start();
if (isset($_GET['wrongQuestionIds'])) {
    $_SESSION['wrong_question_ids'] = json_decode($_GET['wrongQuestionIds'], true);
}
?>