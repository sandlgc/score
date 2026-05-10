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
    $logId = $_POST['log_id'];

    // 开启事务
    $conn->begin_transaction();

    try {
        // 获取该积分记录的积分变化值
        $getPointsChangeSql = "SELECT points_change, student_id FROM point_logs WHERE id = ?";
        $getPointsChangeStmt = $conn->prepare($getPointsChangeSql);
        $getPointsChangeStmt->bind_param("i", $logId);
        $getPointsChangeStmt->execute();
        $pointsChangeResult = $getPointsChangeStmt->get_result();
        $pointsChangeRow = $pointsChangeResult->fetch_assoc();

        if ($pointsChangeRow) {
            $pointsChange = $pointsChangeRow['points_change'];
            $studentId = $pointsChangeRow['student_id'];

            // 更新学生的总积分
            $updatePointsSql = "UPDATE students SET points = points - ? WHERE student_id = ?";
            $updatePointsStmt = $conn->prepare($updatePointsSql);
            $updatePointsStmt->bind_param("ds", $pointsChange, $studentId); // 假设 points_change 可能是小数，使用 "ds"

            if (!$updatePointsStmt->execute()) {
                throw new Exception('更新总积分失败: ' . $updatePointsStmt->error);
            }

            // 删除积分记录
            $deleteSql = "DELETE FROM point_logs WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param("i", $logId);

            if (!$deleteStmt->execute()) {
                throw new Exception('积分记录删除失败: ' . $deleteStmt->error);
            }

            // 提交事务
            $conn->commit();

            $response = array(
                'success' => true,
                'message' => '积分记录删除成功，总积分已更新'
            );
        } else {
            throw new Exception('未找到该积分记录');
        }
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();

        $response = array(
            'success' => false,
            'message' => $e->getMessage()
        );
    }

    if (isset($getPointsChangeStmt)) {
        $getPointsChangeStmt->close();
    }
    if (isset($updatePointsStmt)) {
        $updatePointsStmt->close();
    }
    if (isset($deleteStmt)) {
        $deleteStmt->close();
    }
} else {
    $response = array(
        'success' => false,
        'message' => '无效的请求方法'
    );
}

echo json_encode($response);
?>