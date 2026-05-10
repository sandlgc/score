<?php
// 引入配置文件
require_once 'config.php';

// 查询每个学生的总积分变化
$sql = "SELECT student_id, SUM(points_change) as total_points FROM point_logs GROUP BY student_id";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $student_id = $row['student_id'];
        $total_points = $row['total_points'];

        // 更新学生表中的总积分
        $updateSql = "UPDATE students SET points = $total_points WHERE student_id = '$student_id'";
        if ($conn->query($updateSql) !== TRUE) {
            $response = [
                'error' => true,
                'message' => "更新学生 $student_id 积分时出错: " . $conn->error
            ];
            echo json_encode($response);
            exit;
        }
    }
    $response = [
        'error' => false,
        'message' => "所有学生的积分已成功更新。"
    ];
} else {
    $response = [
        'error' => false,
        'message' => "未找到积分记录。"
    ];
}

$conn->close();
echo json_encode($response);
?>
    