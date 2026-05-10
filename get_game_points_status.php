<?php
// 引入数据库连接配置
include 'config.php';

// 查询 points_status 表中 function_name 为 "game_points" 的状态
$query = "SELECT status FROM points_status WHERE function_name = 'game_points'";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $status = $row['status'];
    echo json_encode(['status' => $status]);
} else {
    echo json_encode(['status' => 'disabled']);
}

$conn->close();
?>