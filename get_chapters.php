<?php
// 引入数据库连接配置
include 'config.php';

// 从数据库中查询所有不同的章节名称
$sql = "SELECT DISTINCT chapter_name FROM questions";
$result = $conn->query($sql);

$chapters = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $chapters[] = $row["chapter_name"];
    }
}

// 关闭连接
$conn->close();

// 返回 JSON 数据
header('Content-Type: application/json');
echo json_encode($chapters);
?>