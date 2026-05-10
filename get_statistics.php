<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

$selectedClass = isset($_GET['class']) ? $_GET['class'] : '';

// 统计本节课幸运排行榜（前 5 名）
$forty_five_minutes_ago = date('Y-m-d H:i:s', strtotime('-45 minutes'));
$top_5_this_class_sql = "SELECT student_id as id, name, COUNT(*) as count FROM roll_call_records WHERE class = '$selectedClass' AND time >= '$forty_five_minutes_ago' GROUP BY student_id, name ORDER BY count DESC LIMIT 5";
$top_5_this_class_result = $conn->query($top_5_this_class_sql);
$top_5_this_class_students = [];
while ($top_5_this_class_row = $top_5_this_class_result->fetch_assoc()) {
    $top_5_this_class_students[] = $top_5_this_class_row;
}

// 统计幸运排行总榜（前 5 名）
$top_10_total_sql = "SELECT student_id as id, name, COUNT(*) as count FROM roll_call_records WHERE class = '$selectedClass' GROUP BY student_id, name ORDER BY count DESC LIMIT 5";
$top_10_total_result = $conn->query($top_10_total_sql);
$top_10_total_students = [];
while ($top_10_total_row = $top_10_total_result->fetch_assoc()) {
    $top_10_total_students[] = $top_10_total_row;
}

$response = [
    'top5ThisClass' => $top_5_this_class_students,
    'top10Total' => $top_10_total_students
];

header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>