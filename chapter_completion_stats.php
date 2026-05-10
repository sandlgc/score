<?php
session_start();
include 'config.php';

// 管理员权限验证
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit();
}

// 获取所有章节
$chapters = [];
$res = $conn->query("SELECT DISTINCT chapter FROM student_answer_records ORDER BY chapter");
while ($row = $res->fetch_assoc()) {
    $chapters[] = $row['chapter'];
}

// 获取所有班级
$classes = [];
$res = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");
while ($row = $res->fetch_assoc()) {
    $classes[] = $row['class'];
}

// 筛选条件
$selected_chapter = $_GET['chapter'] ?? '';
$selected_class = $_GET['class'] ?? '';
$show_type = $_GET['show_type'] ?? 'all'; // all=全部 completed=已完成 uncompleted=未完成

$students = [];
$completion_map = [];

// 必须选择章节和班级
if ($selected_chapter && $selected_class) {

    // 1. 获取该班级所有学生
    $student_res = $conn->query("
        SELECT student_id, name, class 
        FROM students 
        WHERE class = '".mysqli_real_escape_string($conn, $selected_class)."'
        ORDER BY name
    ");
    while ($row = $student_res->fetch_assoc()) {
        $students[] = $row;
    }

    // 2. 获取已完成该章节的学生
    $completed_res = $conn->query("
        SELECT DISTINCT sar.student_id
        FROM student_answer_records sar
        WHERE sar.chapter = '".mysqli_real_escape_string($conn, $selected_chapter)."'
    ");
    while ($row = $completed_res->fetch_assoc()) {
        $completion_map[$row['student_id']] = true;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>章节完成情况统计</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        body {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
            background: #f5f7fa;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .filter {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter select, .filter button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .filter button {
            background: #4285f4;
            color: white;
            border: none;
            cursor: pointer;
        }
        .export {
            background: #0d9e62 !important;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f2f2f2;
        }
        .uncompleted {
            background-color: #ffebee;
            color: #c62828;
        }
        .completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .empty {
            text-align: center; padding: 30px; color: #999;
        }
    </style>
</head>
<body>
    <h1>📊 章节答题完成统计（含未完成名单）</h1>

    <div class="filter">
        <form method="get" style="display:flex; gap:15px; flex-wrap:wrap; align-items:center;">
            <select name="chapter" required>
                <option value="">选择章节</option>
                <?php foreach ($chapters as $ch): ?>
                    <option value="<?= $ch ?>" <?= $selected_chapter == $ch ? 'selected' : '' ?>><?= $ch ?></option>
                <?php endforeach; ?>
            </select>

            <select name="class" required>
                <option value="">选择班级</option>
                <?php foreach ($classes as $cls): ?>
                    <option value="<?= $cls ?>" <?= $selected_class == $cls ? 'selected' : '' ?>><?= $cls ?></option>
                <?php endforeach; ?>
            </select>

            <select name="show_type">
                <option value="all" <?= $show_type == 'all' ? 'selected' : '' ?>>全部学生</option>
                <option value="completed" <?= $show_type == 'completed' ? 'selected' : '' ?>>已完成</option>
                <option value="uncompleted" <?= $show_type == 'uncompleted' ? 'selected' : '' ?>>未完成</option>
            </select>

            <button type="submit">查询</button>
            <button formaction="export_completion.php" class="export">导出名单</button>
        </form>
    </div>

    <?php if ($selected_chapter && $selected_class): ?>
        <table>
            <thead>
                <tr>
                    <th>序号</th>
                    <th>学号</th>
                    <th>姓名</th>
                    <th>班级</th>
                    <th>章节</th>
                    <th>状态</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = 1;
                foreach ($students as $stu):
                    $sid = $stu['student_id'];
                    $done = isset($completion_map[$sid]);

                    if ($show_type === 'completed' && !$done) continue;
                    if ($show_type === 'uncompleted' && $done) continue;
                ?>
                    <tr class="<?= $done ? 'completed' : 'uncompleted' ?>">
                        <td><?= $i++ ?></td>
                        <td><?= $stu['student_id'] ?></td>
                        <td><?= $stu['name'] ?></td>
                        <td><?= $stu['class'] ?></td>
                        <td><?= $selected_chapter ?></td>
                        <td><?= $done ? '✅ 已完成' : '❌ 未完成' ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($i == 1): ?>
                    <tr><td colspan="6" class="empty">暂无数据</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="background:white; padding:30px; border-radius:8px; text-align:center;">
            请选择 章节 + 班级 查看统计
        </div>
    <?php endif; ?>

</body>
</html>