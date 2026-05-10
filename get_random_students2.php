<?php
// 引入数据库连接配置
include 'config.php';


// 获取班级名称
$class_name = $_GET['class_name'];

// 防止 SQL 注入
$class_name = $conn->real_escape_string($class_name);

// 查询该班级的所有学生姓名，并随机排序，取前两名
$sql = "SELECT `name` FROM `students` WHERE `class` = '$class_name' ORDER BY RAND() LIMIT 4";
$result = $conn->query($sql);

$students = [];
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row['name'];
        }
    }
}

// 输出为 JSON 格式
header('Content-Type: application/json');
echo json_encode($students);

// 关闭数据库连接
$conn->close();
?>    