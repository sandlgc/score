<?php
session_start();
include 'config.php';
include 'check_commitment.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION["user_id"];
// 检测承诺书
$commitment_check = checkStudentCommitment($conn, $student_id);
$commitment_warning = $commitment_check['warning'];

$studentId = $_SESSION["user_id"];
// 获取当天日期
$today = date('Y-m-d');

// 查询当天该学生的总积分
$totalScoreQuery = "SELECT SUM(points_change) as total_score FROM point_logs 
                    WHERE student_id = '$studentId' 
                    AND action LIKE '%章答题结束%'
                    AND DATE(action_time) = '$today'";
$totalScoreResult = mysqli_query($conn, $totalScoreQuery);
$totalScoreRow = mysqli_fetch_assoc($totalScoreResult);
$totalScore = floatval($totalScoreRow['total_score']);
$totalScore = is_null($totalScore)? 0 : $totalScore;

// 积分提醒信息
$reminderMessage = "";
if ($totalScore >= 8) {
    $reminderMessage = "您今天获取的答题积分已达上限 8 分，虽然不再记录积分，但您可以继续答题获取更多知识。";
}

// 查询所有章节
$chapter_sql = "SELECT DISTINCT chapter_name FROM questions";
$chapter_result = $conn->query($chapter_sql);
$chapters = [];
while ($chapter_row = $chapter_result->fetch_assoc()) {
    $chapters[] = $chapter_row;
}

// 查询答题排行榜，通过多表查询获取学生姓名，只取前12名
$rankQuery = "SELECT p.student_id, s.name, SUM(p.points_change) as total_score 
              FROM point_logs p
              JOIN students s ON p.student_id = s.student_id
              WHERE DATE(p.action_time) = '$today' 
              AND p.action LIKE '%章答题结束%'
              GROUP BY p.student_id 
              ORDER BY total_score DESC
              LIMIT 12";
$rankResult = mysqli_query($conn, $rankQuery);
$rankList = [];
while ($rankRow = mysqli_fetch_assoc($rankResult)) {
    $rankList[] = $rankRow;
}

// 定义称号数组
$titles = [
    '【富强号】经济列车·Lv.9999',
    '【民主先锋】Lv.90（投票最强）',
    '【文明6】Lv.999（文明重启版）',
    '【和谐号】超速列车·Lv.100',
    '【自由の翅膀】Lv.99（亚撒西）',
    '【平等竞技场】Lv.1V1真男人',
    '【公正之剑】Lv.斩妖除魔',
    '【法治之光】Lv.66（六六大顺）',
    '【爱国者导弹】S级·Lv.10086',
    '【敬业福】永久典藏版·Lv.99',
    '【诚信之光】Lv.+∞（无限）',
    '【友善光环】Lv.520（真爱护盾）'
];

// 补齐12行数据
while (count($rankList) < 12) {
    $rankList[] = [
       'student_id' => '',
        'name' => '',
        'total_score' => 0
    ];
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

// 清除question.php页面加载标志，表示页面已完成加载
unset($_SESSION['page_loaded']);
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
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            gap: 20px;
        }

        /* 给选择章节内容的容器添加样式 */
       .select-chapter-container {
            margin-left: calc(33.33% - 1000px); /* 大概移到页面三分之一处，根据实际情况调整 200px 这个值 */
        }

        h1 {
            font-size: 2.5em;
            color: #007BFF;
            margin-bottom: 10px;
            text-align: center; /* 添加此样式使标题居中 */
        }

       .score-info {
            color: #666;
            margin-bottom: 10px;
            font-size: 25px;
            text-align: center; /* 添加此样式使积分信息居中 */
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

       .score-explanation {
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            width: 400px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin: 0 auto; /* 使该元素水平居中 */
        }

       .score-explanation h2 {
            font-size: 1.2em;
            color: #007BFF;
            margin-bottom: 10px;
        }

       .score-explanation p {
            margin-bottom: 5px;
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
            margin: 0 auto; /* 使该元素水平居中 */
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
            justify-content: center; /* 使链接部分水平居中 */
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

       .rules-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border: 1px solid #eee;
            width: 400px; /* 设置和表单一样的宽度 */
            margin: 0 auto; /* 使该元素水平居中 */
        }

       .rule-item {
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
            position: relative;
        }

       .rule-item:before {
            content: "●";
            position: absolute;
            left: 0;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

       .rank-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 450px; /* 增加排行榜宽度 */
            position: fixed;
            top: 20px;
            right: 20px;
            /* 添加以下属性以确保排行榜不会被其他内容遮挡*/
            z-index: 1000;
        }

       .rank-container h2 {
            font-size: 1.5em;
            color: #007BFF;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table, th, td {
            border: 1px solid #ccc;
        }

        th, td {
            padding: 8px;
            text-align: center; /* 让表格内容居中 */
        }

    </style>
    <script>
        function checkChapterCount() {
            const selectedChapter = document.querySelector('select[name="chapter_name"]').value;
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `check_chapter_count.php?student_id=<?php echo $studentId;?>&chapter_name=${selectedChapter}&date=<?php echo $today;?>`, true);
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

        function validateForm() {
            const selectedChapter = document.querySelector('select[name="chapter_name"]').value;
            if (selectedChapter === "") {
                alert("请选择一个章节！");
                return false;
            }
            return true;
        }
    </script>
</head>

<body>
	<?php if ($commitment_warning): ?>
    <div class="commitment-warning">
        <?php echo $commitment_warning; ?>
        <a href="student_commitment.php" style="color: #dc3545; margin-left: 10px;">点击重新签署</a>
    </div>
    <?php endif; ?>
	
    <!-- 给选择章节内容的容器添加 class -->
    <div class="select-chapter-container">
        <h1>选择章节开始答题</h1>
        <br>
        <div class="rules-container">
            <h2>📝 积分规则</h2>
            <div class="rule-item">
                <p>✅ 每答对1题获得0.2积分</p>
            </div>
            <div class="rule-item">
                <p>📌 每章单日最多累计3次积分</p>
            </div>
            <div class="rule-item">
                <p>⏳ 单日积分上限8分</p>
            </div>
            <div class="rule-item">
                <p>✌ 连续答对3题，开启：10秒内答对1题获得0.3积分</p>
            </div>
            <div class="rule-item">
                <p>♪ 连续答对6题，开启：15秒内答对1题获得0.3积分</p>
            </div>
            <div class="rule-item">
                <p>☀ 重复做熟悉题目积分打折，攻克新题能拿更多积分</p>
            </div>
        </div>
        <br>
        <div class="score-info">您今天已获得答题的积分为: <?php echo $totalScore; ?></div>
        <?php if (!empty($reminderMessage)): ?>
            <div class="reminder"><?php echo $reminderMessage; ?></div>
        <?php endif; ?>
        <div id="chapter-reminder" class="chapter-reminder"></div>
        
        <form method="get" action="select_chapter.php" onsubmit="return validateForm();"> <!-- 调用验证函数 -->
            <select name="chapter_name" onchange="checkChapterCount()">
                <option value="">请选择章节</option>
                <?php
                foreach ($chapters as $chapter) {
                    echo "<option value='{$chapter['chapter_name']}'>{$chapter['chapter_name']}</option>";
                }
                ?>
            </select>
            <input type="submit" value="开始答题">
        </form>
        <br>
        <div class="links">
            <a href="student_menu.php">返回学生菜单</a>
            <a href="logout.php">退出登录</a>
        </div>
    </div>
    <div class="rank-container">
        <h2>📊 今天答题排行榜</h2>
        <table>
            <thead>
                <tr>
                    <th>名次</th>
                    <th>称号</th>
                    <th>姓名</th>
                    <th>积分</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rank = 1;
                foreach ($rankList as $index => $item) {
                    $title = isset($titles[$index])? $titles[$index] : '空';
                    $name = empty($item['name'])? '空缺' : $item['name'];
                    echo "<tr>";
                    echo "<td>{$rank}</td>";
                    echo "<td>{$title}</td>";
                    echo "<td>{$name}</td>";
                    echo "<td>{$item['total_score']}</td>";
                    echo "</tr>";
                    $rank++;
                }
                ?>
            </tbody>
        </table>
    </div>
</body>

</html>