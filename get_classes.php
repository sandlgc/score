<?php
// 引入数据库连接配置
include 'config.php';

// 查询不同的班级名称
$sql = "SELECT DISTINCT `class` FROM `students`";
$result = $conn->query($sql);

$classes = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row['class'];
    }
}

// 输出为 JSON 格式
header('Content-Type: application/json');
echo json_encode($classes);

// 关闭数据库连接
$conn->close();
?>    