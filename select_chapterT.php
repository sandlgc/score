<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$studentId = $_SESSION["user_id"];
// 获取当天日期
$today = date('Y-m-d');

// 查询当天该学生的总积分
$totalScoreQuery = "SELECT SUM(points_change) as total_score FROM point_logs 
                    WHERE student_id = '$studentId' 
                    AND DATE(action_time) = '$today'";
$totalScoreResult = mysqli_query($conn, $totalScoreQuery);
$totalScoreRow = mysqli_fetch_assoc($totalScoreResult);
$totalScore = floatval($totalScoreRow['total_score']);
$totalScore = is_null($totalScore)? 0 : $totalScore;

// 积分提醒信息
$reminderMessage = "";
if ($totalScore > 5) {
    $reminderMessage = "您今天的选择题答题积分已超过上限 5 分，虽然不再记录积分，但您可以继续答题获取更多知识。";
}

// 查询所有章节
$chapter_sql = "SELECT DISTINCT chapter_name FROM questions";
$chapter_result = $conn->query($chapter_sql);
$chapters = [];
while ($chapter_row = $chapter_result->fetch_assoc()) {
    $chapters[] = $chapter_row;
}

// 当用户选择章节并提交表单时
if (isset($_GET['chapter_name'])) {
    $_SESSION['selected_chapter'] = $_GET['chapter_name'];
    $_SESSION['score'] = 0; // 重置分数
    $_SESSION['current_question_index'] = 0; // 重置当前题目索引
    // 从数据库获取该章节的题目
    $question_sql = "SELECT * FROM questions WHERE chapter_name = '" . $_GET['chapter_name'] . "'";
    $question_result = $conn->query($question_sql);
    $questions = [];
    while ($question_row = $question_result->fetch_assoc()) {
        $questions[] = $question_row;
    }
    $_SESSION['questions'] = $questions;
    header("Location: question.php");
    exit();
}

// 检查是否有消息传递
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    echo '<div style="color: red; font-weight: bold; text-align: center;">' . $message . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>选择章节</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        h1 {
            font-size: 2.5em;
            color: #007BFF;
            margin-bottom: 10px;
        }

        .score-info {
            color: #666;
            margin-bottom: 10px;
        }

        .reminder {
            color: red;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .chapter-reminder {
            color: darkred;
            font-weight: bold;
            margin-bottom: 20px;
        }

        form {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 400px;
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
        }

        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }

        select:focus {
            border-color: #007BFF;
            outline: none;
        }

        input[type="submit"] {
            background-color: #007BFF;
            color: white;
            padding: 12px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-weight: 600;
        }

        input[type="submit"]:hover {
            background-color: #0056b3;
        }

        .links {
            display: flex;
            gap: 20px;
        }

        .links a {
            display: inline-block;
            background-color: #007BFF;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            transition: background-color 0.3s ease;
            font-weight: 600;
        }

        .links a:hover {
            background-color: #0056b3;
        }
    </style>
    <script>
        function checkChapterCount() {
            const selectedChapter = document.querySelector('select[name="chapter_name"]').value;
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `check_chapter_count.php?student_id=<?php echo $studentId; ?>&chapter_name=${selectedChapter}&date=<?php echo $today; ?>`, true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    const chapterReminder = document.getElementById('chapter-reminder');
                    if (response.count >= 3) {
                        chapterReminder.textContent = `您今天在章节《 ${selectedChapter}》 的答题记录次数已达到上限 3 次，虽然不再记录积分，但您可以继续作答。`;
                    } else {
                        chapterReminder.textContent = '';
                    }
                }
            };
            xhr.send();
        }
    </script>
</head>

<body>
    <h1>选择章节开始答题</h1>
    <div class="score-info">您今天做选择题的积分为: <?php echo $totalScore; ?></div>
    <?php if (!empty($reminderMessage)): ?>
        <div class="reminder"><?php echo $reminderMessage; ?></div>
    <?php endif; ?>
    <div id="chapter-reminder" class="chapter-reminder"></div>
    <form method="get" action="select_chapter.php">
        <select name="chapter_name" onchange="checkChapterCount()">
            <?php
            foreach ($chapters as $chapter) {
                echo "<option value='{$chapter['chapter_name']}'>{$chapter['chapter_name']}</option>";
            }
            ?>
        </select>
        <input type="submit" value="开始答题">
    </form>
    <div class="links">
        <a href="student_menu.php">返回学生菜单</a>
        <a href="logout.php">退出登录</a>
    </div>
</body>

</html>    