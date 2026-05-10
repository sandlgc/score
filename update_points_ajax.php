<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    $response = array(
        'success' => false,
        'message' => '未授权访问'
    );
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_POST["student_id"];
    // 将 $points_change 当作小数处理，去掉 (int) 强制转换
    $points_change = $_POST["points_change"]; 
    $action = $_POST["action"];

    date_default_timezone_set('Asia/Shanghai');
    // 获取当前时间
    $action_time = date('Y-m-d H:i:s');

    // 更新学生积分
    $update_sql = "UPDATE students SET points = points + ? WHERE student_id = ?";
    $stmt = $conn->prepare($update_sql);
    // 将绑定参数类型从 "is" 改为 "ds"，因为 $points_change 现在是小数
    $stmt->bind_param("ds", $points_change, $student_id); 

    if ($stmt->execute()) {
        // 记录积分明细
        $log_sql = "INSERT INTO point_logs (student_id, action, points_change, action_time) VALUES (?,?,?,?)";
        $log_stmt = $conn->prepare($log_sql);
        // 将绑定参数类型从 "ssis" 改为 "ssds"
        $log_stmt->bind_param("ssds", $student_id, $action, $points_change, $action_time); 
        if ($log_stmt->execute()) {
            // 获取更新后的积分
            $select_sql = "SELECT points FROM students WHERE student_id = ?";
            $select_stmt = $conn->prepare($select_sql);
            $select_stmt->bind_param("s", $student_id);
            $select_stmt->execute();
            $result = $select_stmt->get_result();
            $row = $result->fetch_assoc();
            $new_points = $row['points'];

            $response = array(
                'success' => true,
                'new_points' => $new_points
            );
        } else {
            $response = array(
                'success' => false,
                'message' => '积分明细记录失败: ' . $log_stmt->error
            );
        }
    } else {
        $response = array(
            'success' => false,
            'message' => '积分更新失败: ' . $stmt->error
        );
    }
} else {
    $response = array(
        'success' => false,
        'message' => '无效的请求方法'
    );
}

echo json_encode($response);
?>