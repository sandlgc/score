<?php
// 引入数据库连接配置
include 'config.php';

// 获取章节名称参数
$chapterName = isset($_GET['chapter_name']) ? $_GET['chapter_name'] : '';

// 防止 SQL 注入，对章节名称进行转义处理
$chapterName = $conn->real_escape_string($chapterName);

// 从数据库中随机选取 10 条题目
$sql = "SELECT * FROM questions WHERE chapter_name = '$chapterName' ORDER BY RAND() LIMIT 10";
$result = $conn->query($sql);

$questions = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $question = [
            "question" => $row["question"],
            "options" => [
                $row["option1"],
                $row["option2"],
                $row["option3"],
                $row["option4"]
            ],
            "answer" => $row["answer"]
        ];
        $questions[] = $question;
    }
}

// 关闭连接
$conn->close();

// 返回 JSON 数据
header('Content-Type: application/json');
echo json_encode($questions);
?>