<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_POST['student_id'];
    $points_change = $_POST['points_change'];
    $action = $_POST['action'];
    // 获取当前时间
    $action_time = date('Y-m-d H:i:s'); 

    // 插入积分记录
    $insert_sql = "INSERT INTO point_logs (student_id, points_change, action, action_time) VALUES (?,?,?,?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("sdss", $student_id, $points_change, $action, $action_time);

    if ($stmt->execute()) {
        // 更新学生总积分
        $update_sql = "UPDATE students SET points = points + ? WHERE student_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ds", $points_change, $student_id);

        if ($update_stmt->execute()) {
            echo "success";
        } else {
            http_response_code(500);
            echo "Error updating student points: " . $update_stmt->error;
        }
        $update_stmt->close();
    } else {
        http_response_code(500);
        echo "Error inserting point log: " . $stmt->error;
    }
    $stmt->close();
}

$conn->close();
?>