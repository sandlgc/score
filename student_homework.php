<?php
// 引入你系统的公共文件
include 'student_common.php';

// 初始化登录验证
$conn = studentInit();
$student_id = $_SESSION["user_id"];
$student_name = getStudentInfo($conn, $student_id);

// 获取班级
$student = $conn->query("SELECT class FROM students WHERE student_id = '$student_id'")->fetch_assoc();
$class = $student['class'];

// 获取作业列表
$homework_list = $conn->query("
    SELECT id, title, created_at, is_published 
    FROM homework 
    WHERE class_name='$class' AND is_published=1 
    ORDER BY id ASC
");

// 获取【我的提交记录 + 批改状态】
$my_submits = $conn->query("
    SELECT homework_id, checked_status 
    FROM homework_submit 
    WHERE student_id='$student_id'
");

$submit_status = [];
while ($s = $my_submits->fetch_assoc()) {
    $submit_status[ $s['homework_id'] ] = $s['checked_status'];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的作业</title>
    <style>
        /* 完全和你学生菜单风格统一 */
        :root {
            --primary: #007BFF;
            --primary-hover: #0056b3;
            --light-bg: #f4f4f9;
            --white: #ffffff;
            --text-dark: #333333;
            --text-muted: #666666;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --radius: 10px;
            --success: #28a745;
            --warning: #ffc107;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Microsoft YaHei', sans-serif;
        }

        body {
            background-color: var(--light-bg);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding: 20px;
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .header h1 {
            font-size: 22px;
            color: var(--primary);
        }

        .back {
            margin-bottom: 15px;
        }

        .back a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }

        .hw-card {
            background: var(--white);
            padding: 18px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .hw-info {
            flex: 1;
        }

        .hw-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--text-dark);
        }

        .hw-time {
            font-size: 12px;
            color: var(--text-muted);
        }

        .hw-status {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-done {
            background: #d4edda;
            color: #155724;
        }

        .status-wait {
            background: #fff3cd;
            color: #856404;
        }

        .status-checked {
            background: #cfe2ff;
            color: #084298;
        }

        .hw-btn {
            background: var(--primary);
            color: #fff;
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            margin-left: 10px;
        }

        .hw-btn:hover {
            background: var(--primary-hover);
        }

        .empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="back">
        <a href="student_menu.php">← 返回学生中心</a>
    </div>

    <div class="header">
        <h1>📚 我的作业</h1>
    </div>

    <?php while ($hw = $homework_list->fetch_assoc()) {
        $hid = $hw['id'];
        $is_submitted = isset($submit_status[$hid]);
        $is_checked = $is_submitted ? $submit_status[$hid] : 0;
        $time = date('Y-m-d H:i', strtotime($hw['created_at']));
    ?>
        <div class="hw-card">
            <div class="hw-info">
                <div class="hw-title"><?php echo $hw['title']; ?></div>
                <div class="hw-time">发布时间：<?php echo $time; ?></div>
            </div>

            <div style="display:flex;align-items:center;gap:10px;">
                <?php if (!$is_submitted): ?>
                    <span class="hw-status status-wait">未提交</span>
                    <a href="student_submit.php?id=<?php echo $hid; ?>" class="hw-btn">去提交</a>
                <?php elseif ($is_checked == 1): ?>
                    <span class="hw-status status-checked">已批改</span>
                    <a href="student_submit.php?id=<?php echo $hid; ?>" class="hw-btn">查看批改</a>
                <?php else: ?>
                    <span class="hw-status status-done">已完成</span>
                    <a href="student_submit.php?id=<?php echo $hid; ?>" class="hw-btn">查看</a>
                <?php endif; ?>
            </div>
        </div>
    <?php } ?>

    <?php if ($homework_list->num_rows == 0): ?>
        <div class="empty">暂无作业</div>
    <?php endif; ?>
</div>

</body>
</html>