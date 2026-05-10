<?php
session_start();
include 'config.php';

$studentId = $_GET['student_id'];
$chapterName = $_GET['chapter_name'];
$date = $_GET['date'];

$countQuery = "SELECT COUNT(*) as count FROM point_logs 
               WHERE student_id = '$studentId' 
               AND action LIKE '%第 $chapterName 章答题结束%' 
               AND DATE(action_time) = '$date'";
$countResult = mysqli_query($conn, $countQuery);
$countRow = mysqli_fetch_assoc($countResult);
$count = intval($countRow['count']);

echo json_encode(['count' => $count]);
?>    