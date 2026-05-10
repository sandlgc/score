<?php
// 设置时区，这里以中国的时区（Asia/Shanghai）为例，你可按需修改
date_default_timezone_set('Asia/Shanghai');

// 引入数据库连接配置
include 'config.php';

// 获取前端发送的数据
$data = json_decode(file_get_contents('php://input'), true);
$winnerName = $data['winnerName'];
$winnerPoints = $data['winnerPoints'];
$loserName = $data['loserName'];
$loserPoints = $data['loserPoints'];

// 获取当前时间
$actionTime = date('Y-m-d H:i:s');

// 获取胜利者和失败者的 student_id
$winnerQuery = "SELECT student_id FROM students WHERE name = '$winnerName'";
$winnerResult = $conn->query($winnerQuery);
$winnerRow = $winnerResult->fetch_assoc();
$winnerId = $winnerRow['student_id'];

$loserQuery = "SELECT student_id FROM students WHERE name = '$loserName'";
$loserResult = $conn->query($loserQuery);
$loserRow = $loserResult->fetch_assoc();
$loserId = $loserRow['student_id'];

// 检查游戏积分功能是否开启
$checkStatusQuery = "SELECT status FROM points_status WHERE function_name = 'game_points'";
$statusResult = $conn->query($checkStatusQuery);
if ($statusRow = $statusResult->fetch_assoc()) {
    $status = $statusRow['status'];
    if ($status != 'enabled') {
        echo "游戏积分功能未开启，积分更新失败";
        $conn->close();
        exit;
    }
} else {
    echo "未找到游戏积分功能状态记录，积分更新失败";
    $conn->close();
    exit;
}

// 开始事务
$conn->begin_transaction();

try {
    // 更新胜利者的积分
    $updateWinnerPoints = "UPDATE students SET points = points + $winnerPoints WHERE student_id = '$winnerId'";
    $conn->query($updateWinnerPoints);

    // 插入胜利者的积分记录，包含 action_time
    $insertWinnerLog = "INSERT INTO point_logs (student_id, action, points_change, action_time) VALUES ('$winnerId', '对战游戏', $winnerPoints, '$actionTime')";
    $conn->query($insertWinnerLog);

    // 更新失败者的积分
    $updateLoserPoints = "UPDATE students SET points = points + $loserPoints WHERE student_id = '$loserId'";
    $conn->query($updateLoserPoints);

    // 插入失败者的积分记录，包含 action_time
    $insertLoserLog = "INSERT INTO point_logs (student_id, action, points_change, action_time) VALUES ('$loserId', '对战游戏', $loserPoints, '$actionTime')";
    $conn->query($insertLoserLog);

    // 提交事务
    $conn->commit();
    echo "积分更新成功";
} catch (Exception $e) {
    // 回滚事务
    $conn->rollback();
    echo "积分更新失败: " . $e->getMessage();
}

$conn->close();
?>    