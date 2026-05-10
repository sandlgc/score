<?php
session_start();
include 'config.php';

// 管理员权限验证
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit();
}

// 获取所有作业（带统计）
$homework_sql = "
    SELECT 
        h.*,
        COUNT(DISTINCT s.student_id) AS total_student,
        COUNT(DISTINCT hs.student_id) AS submit_count
    FROM homework h
    LEFT JOIN students s ON h.class_name = s.class
    LEFT JOIN homework_submit hs ON h.id = hs.homework_id
    GROUP BY h.id
    ORDER BY h.id DESC
";
$homework_res = $conn->query($homework_sql);

// 获取单作业详情
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$current_homework = null;
$submit_list = [];
$unsubmit_list = [];
$total_student = 0;
$submit_num = 0;
$unsubmit_num = 0;
$rate = 0;

if ($view_id > 0) {
    // 当前作业信息
    $current_homework = $conn->query("SELECT * FROM homework WHERE id = $view_id")->fetch_assoc();
    if (!$current_homework) {
        header("Location: admin_homework_stats.php");
        exit();
    }
    
    // 1. 计算该作业的应提交人数（该班级总人数）
    $class_name = $current_homework['class_name'];
    $total_stu_sql = "SELECT COUNT(*) AS cnt FROM students WHERE class = '$class_name'";
    $total_stu_res = $conn->query($total_stu_sql);
    $total_student = $total_stu_res->fetch_assoc()['cnt'];
    
    // 2. 已提交学生
    $submit_sql = "
        SELECT hs.*, s.class 
        FROM homework_submit hs
        JOIN students s ON hs.student_id = s.student_id
        WHERE hs.homework_id = $view_id
        ORDER BY hs.submit_time DESC
    ";
    $submit_list = $conn->query($submit_sql);
    $submit_num = $submit_list->num_rows;
    
    // 3. 未提交学生
    $unsubmit_sql = "
        SELECT * FROM students 
        WHERE class = '$class_name'
        AND student_id NOT IN (
            SELECT student_id FROM homework_submit WHERE homework_id = $view_id
        )
        ORDER BY name
    ";
    $unsubmit_list = $conn->query($unsubmit_sql);
    $unsubmit_num = $unsubmit_list->num_rows;
    
    // 4. 计算完成率
    $rate = $total_student > 0 ? round($submit_num / $total_student * 100, 1) : 0;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>作业统计 - 学习积分管理系统</title>
    <link rel="stylesheet" href="fontawesome-free-6.4.0-web/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Microsoft YaHei', sans-serif;
        }

        body {
            background: #f8fafc;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 28px;
            margin-bottom: 28px;
        }

        .card-title {
            font-size: 22px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back {
            margin-bottom: 20px;
        }

        .back a {
            color: #2563eb;
            text-decoration: none;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 8px;
        }

        .back a:hover {
            background: #eff6ff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
        }

        th {
            background: #f1f5f9;
            padding: 14px;
            text-align: left;
            font-weight: 600;
            color: #334155;
            font-size: 15px;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
        }

        .badge-green { background: #dcfce7; color: #166534; }
        .badge-orange { background: #fff7ed; color: #c2410c; }
        .badge-blue { background: #dbeafe; color: #1e40af; }

        .btn-view {
            background: #2563eb;
            color: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .stats-row {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .stat-box {
            flex: 1;
            min-width: 150px;
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-box .num {
            font-size: 28px;
            font-weight: bold;
            margin: 4px 0;
        }

        .stat-box .label {
            font-size: 14px;
            color: #64748b;
        }

        .tab {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab a {
            padding: 12px 24px;
            background: #f1f5f9;
            border-radius: 8px;
            text-decoration: none;
            color: #334155;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.2s;
        }

        .tab a.active {
            background: #2563eb;
            color: #fff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="back">
        <a href="admin_menu.php"><i class="fas fa-arrow-left"></i> 返回管理菜单</a>
    </div>

    <!-- 作业总统计 -->
    <div class="card">
        <div class="card-title">
            <i class="fas fa-chart-bar"></i> 作业完成情况统计
        </div>
        <table>
            <tr>
                <th>作业标题</th>
                <th>班级</th>
                <th>应提交人数</th>
                <th>已提交</th>
                <th>未提交</th>
                <th>完成率</th>
                <th>操作</th>
            </tr>
            <?php 
            // 重置指针，避免详情页影响列表
            $homework_res->data_seek(0);
            while($hw = $homework_res->fetch_assoc()): 
                $total = $hw['total_student'];
                $submit = $hw['submit_count'];
                $unsubmit = $total - $submit;
                $rate_item = $total > 0 ? round($submit / $total * 100, 1) : 0;
            ?>
            <tr>
                <td><?=htmlspecialchars($hw['title'])?></td>
                <td><?=$hw['class_name']?></td>
                <td><?=$total?></td>
                <td><?=$submit?></td>
                <td><?=$unsubmit?></td>
                <td>
                    <span class="badge <?= $rate_item >= 80 ? 'badge-green' : ($rate_item >= 50 ? 'badge-orange' : '') ?>">
                        <?=$rate_item?>%
                    </span>
                </td>
                <td>
                    <a href="?view=<?=$hw['id']?>" class="btn-view">
                        <i class="fas fa-eye"></i> 查看详情
                    </a>
                    <a href="admin_homework_check.php?id=<?=$hw['id']?>" class="btn-view" style="background:#f59e0b;margin-left:6px">
					    <i class="fas fa-pen"></i> 去批改
					</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <!-- 单作业详情 -->
    <?php if ($view_id > 0 && $current_homework): ?>
    <div class="card">
        <div class="card-title">
            <i class="fas fa-file-alt"></i> 
            作业详情：<?=htmlspecialchars($current_homework['title'])?>
        </div>

        <div class="stats-row">
            <div class="stat-box">
                <div class="num"><?=$total_student?></div>
                <div class="label">应提交人数</div>
            </div>
            <div class="stat-box">
                <div class="num" style="color:#16a34a"><?=$submit_num?></div>
                <div class="label">已提交</div>
            </div>
            <div class="stat-box">
                <div class="num" style="color:#dc2626"><?=$unsubmit_num?></div>
                <div class="label">未提交</div>
            </div>
            <div class="stat-box">
                <div class="num" style="color:#2563eb"><?=$rate?>%</div>
                <div class="label">完成率</div>
            </div>
        </div>

        <div class="tab">
            <a href="javascript:void(0)" class="tab-link active" data-tab="submit">已提交学生</a>
            <a href="javascript:void(0)" class="tab-link" data-tab="unsubmit">未提交学生</a>
        </div>

        <!-- 已提交 -->
        <div id="submit" class="tab-content active">
            <table>
                <tr>
                    <th>学号</th>
                    <th>姓名</th>
                    <th>班级</th>
                    <th>提交时间</th>
                    <th>提交格式</th>
                </tr>
                <?php if ($submit_num > 0): ?>
                    <?php while($item = $submit_list->fetch_assoc()): ?>
                    <tr>
                        <td><?=$item['student_id']?></td>
                        <td><?=$item['student_name']?></td>
                        <td><?=$item['class']?></td>
                        <td><?=$item['submit_time']?></td>
                        <td>
                            <span class="badge badge-blue">
                                <?=$item['submit_type'] == 'text' ? '文字' : '图片'?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;color:#64748b;padding:20px;">暂无已提交学生</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- 未提交 -->
        <div id="unsubmit" class="tab-content">
            <table>
                <tr>
                    <th>学号</th>
                    <th>姓名</th>
                    <th>班级</th>
                    <th>状态</th>
                </tr>
                <?php if ($unsubmit_num > 0): ?>
                    <?php while($u = $unsubmit_list->fetch_assoc()): ?>
                    <tr>
                        <td><?=$u['student_id']?></td>
                        <td><?=$u['name']?></td>
                        <td><?=$u['class']?></td>
                        <td><span class="badge badge-orange">未提交</span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center;color:#64748b;padding:20px;">全部学生已提交</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// 标签页切换
document.querySelectorAll('.tab-link').forEach(link => {
    link.addEventListener('click', function() {
        // 移除所有激活状态
        document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // 激活当前标签
        this.classList.add('active');
        const tabId = this.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
    });
});
</script>

</body>
</html>