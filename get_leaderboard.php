<?php
// 临时禁用错误显示，将错误信息记录到日志文件
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 引入数据库连接配置
include 'config.php';

// 查询学生的积分排行榜
$sql = "SELECT s.name, s.class, SUM(pl.points_change) as total_points 
        FROM point_logs pl
        JOIN students s ON pl.student_id = s.student_id
        WHERE pl.action = '对战游戏' 
        GROUP BY pl.student_id, s.name, s.class
        ORDER BY total_points DESC";

// 打印 SQL 查询语句用于调试
error_log("SQL Query: " . $sql);

// 执行查询
$result = $conn->query($sql);

// 检查查询是否成功
if (!$result) {
    // 记录查询错误到日志
    error_log("Query Error: " . $conn->error);
    // 返回错误信息作为 JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'SQL 查询失败: ' . $conn->error, 'query' => $sql]);
    $conn->close();
    exit;
}

// 存储排行榜数据
$leaderboard = array();

if ($result->num_rows > 0) {
    // 输出数据
    while ($row = $result->fetch_assoc()) {
        $leaderboard[] = $row;
    }
}

// 以 JSON 格式返回排行榜数据
header('Content-Type: application/json');
echo json_encode($leaderboard);

// 关闭数据库连接
$conn->close();
?>    