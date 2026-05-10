<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit();
}

// 获取作业ID
$homework_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($homework_id <= 0) {
    header("Location: admin_homework_stats.php");
    exit();
}

// 获取作业信息
$homework = $conn->query("SELECT * FROM homework WHERE id = $homework_id")->fetch_assoc();
if (!$homework) exit("作业不存在");

// 获取提交列表（带批改信息）
$submits = $conn->query("
    SELECT hs.*, s.class
    FROM homework_submit hs
    JOIN students s ON hs.student_id = s.student_id
    WHERE hs.homework_id = $homework_id
    ORDER BY hs.submit_time DESC
");

// 处理批改保存 + 自动加分
if ($_POST) {
    $submit_id = (int)$_POST['submit_id'];
    $score = (float)$_POST['score'];
    $comment = trim($_POST['comment']);
    $checked = 1;

    // 获取提交记录（学生ID）
    $submit = $conn->query("SELECT * FROM homework_submit WHERE id = $submit_id")->fetch_assoc();
    $student_id = $submit['student_id'];
    $add_points = 0;

    // ====================== 自动加分规则 ======================
    if ($score >= 90) {
        $add_points = 5;
    } elseif ($score >= 80) {
        $add_points = 3;
    }

    // 开始事务（确保数据一致）
    $conn->begin_transaction();

    try {
        // 1. 更新批改信息
        $conn->query("
            UPDATE homework_submit SET
                score = '$score',
                teacher_comment = '$comment',
                checked_status = '$checked',
                checked_at = NOW()
            WHERE id = $submit_id
        ");

        // 2. 如果满足加分条件，增加学生积分
        if ($add_points > 0) {
            // 更新学生总分
            $conn->query("UPDATE students SET points = points + $add_points WHERE student_id = '$student_id'");

            // 写入积分日志
            $conn->query("
                INSERT INTO point_logs (student_id, action, points_change, action_time)
                VALUES ('$student_id', '作业批改奖励 +$add_points 分', '$add_points', NOW())
            ");
        }

        $conn->commit();
        echo "<script>alert('批改成功！" . ($add_points > 0 ? "奖励积分：+$add_points" : "无加分") . "');location.href='?id=$homework_id';</script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('批改失败');history.back();</script>";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>作业批改 - <?=$homework['title']?></title>
    <link rel="stylesheet" href="fontawesome-free-6.4.0-web/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:Microsoft YaHei,sans-serif}
        body{background:#f8fafc;padding:24px}
        .container{max-width:1100px;margin:0 auto}
        .card{background:#fff;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.05);padding:24px;margin-bottom:24px}
        .title{font-size:22px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px}
        .back{margin-bottom:16px}
        .back a{color:#2563eb;text-decoration:none;padding:8px 12px;border-radius:8px}
        .back a:hover{background:#eff6ff}

        table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
        th{background:#f1f5f9;padding:14px;text-align:left}
        td{padding:14px;border-bottom:1px solid #f1f5f9}
        tr:hover{background:#f8fafc}

        .badge{padding:4px 10px;border-radius:6px;font-size:13px}
        .wait{background:#fff7ed;color:#c2410c}
        .done{background:#dcfce7;color:#166534}
        .btn{background:#2563eb;color:#fff;padding:6px 12px;border-radius:6px;text-decoration:none;font-size:14px}

        /* 批改弹窗 */
        .modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);display:none;justify-content:center;align-items:center;z-index:999}
        .modal-body{background:#fff;width:90%;max-width:700px;border-radius:16px;padding:24px;max-height:90vh;overflow-y:auto}
        .close{float:right;font-size:20px;cursor:pointer}
        .content-box{background:#f8fafc;padding:16px;border-radius:12px;margin:12px 0}
        
        /* 图片居中 + 悬停放大 */
        .content-box img{
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            margin: 10px auto;
            display: block;
            cursor: zoom-in;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .content-box img:hover {
            transform: scale(1.8);
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            z-index: 9999;
            position: relative;
        }

        input,textarea{width:100%;padding:12px;margin:8px 0;border:1px solid #ddd;border-radius:8px}
        button{background:#2563eb;color:#fff;padding:12px;border:none;border-radius:8px;width:100%;margin-top:8px}
    </style>
</head>
<body>

<div class="container">
    <div class="back">
        <a href="admin_homework_stats.php"><i class="fas fa-arrow-left"></i> 返回作业统计</a>
    </div>

    <div class="card">
        <div class="title">
            <i class="fas fa-pen"></i> 作业批改：<?=$homework['title']?>
        </div>

        <table>
            <tr>
                <th>学号</th>
                <th>姓名</th>
                <th>班级</th>
                <th>提交时间</th>
                <th>类型</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
            <?php while($row = $submits->fetch_assoc()): ?>
            <tr>
                <td><?=$row['student_id']?></td>
                <td><?=$row['student_name']?></td>
                <td><?=$row['class']?></td>
                <td><?=$row['submit_time']?></td>
                <td><?=$row['submit_type']=='text'?'文字':'图片'?></td>
                <td>
                    <span class="badge <?=$row['checked_status']?'done':'wait'?>">
                        <?=$row['checked_status']?'已批改':'未批改'?>
                    </span>
                </td>
                <td>
                    <a href="javascript:openCheck(<?=htmlspecialchars(json_encode($row))?>)" class="btn">
                        批改
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<!-- 批改弹窗 -->
<div class="modal" id="modal">
    <div class="modal-body">
        <i class="fas fa-times close" onclick="closeModal()"></i>
        <h3 id="modal_title">作业批改</h3>
        <div class="content-box" id="submit_content"></div>
        <form method="post">
            <input type="hidden" id="submit_id" name="submit_id">
            <input type="number" step="0.5" name="score" placeholder="分数" required>
            <textarea name="comment" rows="4" placeholder="输入评语"></textarea>
            <button type="submit">保存批改</button>
        </form>
    </div>
</div>

<script>
let currentSubmit = null;
function openCheck(data) {
    currentSubmit = data;
    document.getElementById('submit_id').value = data.id;
    document.getElementById('modal_title').innerText = "批改：" + data.student_name;

    let content = "";
    if (data.submit_type == "text") {
        content = "<strong>提交内容：</strong><br>" + data.submit_content;
    } else {
        content = "<strong>提交图片（鼠标悬停放大）：</strong><br><img src='" + data.submit_content + "'>";
    }
    document.getElementById('submit_content').innerHTML = content;
    document.getElementById('modal').style.display = "flex";
}

function closeModal() {
    document.getElementById('modal').style.display = "none";
}
</script>

</body>
</html>