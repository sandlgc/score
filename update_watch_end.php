<?php
session_start();
include 'config.php';

// 验证登录状态
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "student") {
    echo json_encode(['code' => 401, 'msg' => '未登录或非学生身份']);
    exit();
}

// 获取参数
$log_id = intval($_POST['log_id'] ?? 0);
if ($log_id <= 0) {
    echo json_encode(['code' => 400, 'msg' => '日志ID无效']);
    exit();
}

// 更新结束时间和时长
$watch_end_time = date('Y-m-d H:i:s');

// 先查询开始时间
$sql = "SELECT watch_start_time FROM student_video_watch_log WHERE id = ? AND student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $log_id, $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['code' => 404, 'msg' => '日志记录不存在']);
    $stmt->close();
    $conn->close();
    exit();
}

$row = $result->fetch_assoc();
$start_time = strtotime($row['watch_start_time']);
$end_time = strtotime($watch_end_time);
$watch_duration = max(0, $end_time - $start_time);

// 更新记录
$update_sql = "UPDATE student_video_watch_log 
               SET watch_end_time = ?, watch_duration = ? 
               WHERE id = ? AND student_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("sisi", $watch_end_time, $watch_duration, $log_id, $_SESSION["user_id"]);
$update_stmt->execute();

if ($update_stmt->affected_rows > 0) {
    echo json_encode(['code' => 200, 'msg' => '更新成功']);
} else {
    echo json_encode(['code' => 500, 'msg' => '更新失败']);
}

$stmt->close();
$update_stmt->close();
$conn->close();
?>