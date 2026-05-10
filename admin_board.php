<?php
session_start(); // 启动会话
include 'config.php';

// ========== 身份验证 ==========
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php"); // 未登录/非管理员跳转到登录页
    exit();
}

// 设置默认用户名（防止未定义）
$adminUsername = isset($_SESSION['username']) ? $_SESSION['username'] : '管理员';

// ========== 核心数据概览 ==========
// 基础统计（简化版）
$summarySql = "
    SELECT 
        (SELECT COUNT(DISTINCT student_id) FROM students) as total_students,
        (SELECT COUNT(DISTINCT class) FROM students) as total_classes,
        (SELECT SUM(points_change) FROM point_logs) as total_points,
        (SELECT COUNT(id) FROM point_logs) as total_records,
        (SELECT COUNT(DISTINCT student_id) FROM point_logs WHERE points_change < 0 OR action LIKE '%睡觉%' OR action LIKE '%不交%') as penalty_students,
        (SELECT COUNT(DISTINCT student_id) FROM point_logs WHERE DATE(created_at) = CURDATE()) as today_active_students,
        (SELECT SUM(points_change) FROM point_logs WHERE DATE(created_at) = CURDATE()) as today_points
";
$summaryResult = $conn->query($summarySql);
$summary = $summaryResult->fetch_assoc();

// ========== 今日TOP班级 ==========
$todayClassSql = "
    SELECT 
        s.class,
        COUNT(DISTINCT s.student_id) as active_count,
        SUM(pl.points_change) as today_points
    FROM students s
    LEFT JOIN point_logs pl ON s.student_id = pl.student_id AND DATE(pl.created_at) = CURDATE()
    GROUP BY s.class
    ORDER BY today_points DESC
    LIMIT 5
";
$todayClassResult = $conn->query($todayClassSql);
$todayClassData = [];
while ($row = $todayClassResult->fetch_assoc()) {
    $todayClassData[] = $row;
}

// ========== 最近问题行为 ==========
$recentProblemSql = "
    SELECT 
        s.student_id,
        s.name,
        s.class,
        pl.action,
        pl.points_change,
        pl.created_at
    FROM students s
    JOIN point_logs pl ON s.student_id = pl.student_id
    WHERE pl.points_change < 0 OR pl.action LIKE '%睡觉%' OR pl.action LIKE '%不交%'
    ORDER BY pl.created_at DESC
    LIMIT 10
";
$recentProblemResult = $conn->query($recentProblemSql);
$recentProblemData = [];
while ($row = $recentProblemResult->fetch_assoc()) {
    $recentProblemData[] = $row;
}

// ========== 学习项目今日数据 ==========
$todayProjectSql = "
    SELECT 
        'game' as type,
        SUM(CASE WHEN action LIKE '%对战游戏%' THEN points_change ELSE 0 END) as points,
        COUNT(CASE WHEN action LIKE '%对战游戏%' THEN 1 ELSE NULL END) as count
    FROM point_logs WHERE DATE(created_at) = CURDATE()
    UNION ALL
    SELECT 
        'quiz' as type,
        SUM(CASE WHEN action LIKE '%章答题结束%' THEN points_change ELSE 0 END) as points,
        COUNT(CASE WHEN action LIKE '%章答题结束%' THEN 1 ELSE NULL END) as count
    FROM point_logs WHERE DATE(created_at) = CURDATE()
    UNION ALL
    SELECT 
        'answer' as type,
        SUM(CASE WHEN action LIKE '%回答问题%' THEN points_change ELSE 0 END) as points,
        COUNT(CASE WHEN action LIKE '%回答问题%' THEN 1 ELSE NULL END) as count
    FROM point_logs WHERE DATE(created_at) = CURDATE()
    UNION ALL
    SELECT 
        'wrong_question' as type,
        SUM(CASE WHEN action LIKE '%错题重做%' THEN points_change ELSE 0 END) as points,
        COUNT(CASE WHEN action LIKE '%错题重做%' THEN 1 ELSE NULL END) as count
    FROM point_logs WHERE DATE(created_at) = CURDATE()
";
$todayProjectResult = $conn->query($todayProjectSql);
$todayProjectData = [];
while ($row = $todayProjectResult->fetch_assoc()) {
    $todayProjectData[$row['type']] = $row;
}

// 定义学习项目映射（复用）
$projectsMap = [
    'game' => ['name' => '对战游戏', 'desc' => '通过游戏化答题巩固知识点', 'icon' => 'gamepad'],
    'quiz' => ['name' => '章节答题', 'desc' => '章节知识点掌握程度检测', 'icon' => 'clipboard-check'],
    'answer' => ['name' => '回答问题', 'desc' => '课堂/课后主动答题表现', 'icon' => 'hand-sparkles'],
    'wrong_question' => ['name' => '错题修炼', 'desc' => '错题重做巩固薄弱点', 'icon' => 'pen-to-square']
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员仪表盘 - 学习积分管理系统</title>
    <link rel="stylesheet" href="fontawesome-free-6.4.0-web/css/all.min.css">
    <script src="js/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #1890ff;
            --primary-light: #e6f7ff;
            --success: #52c41a;
            --warning: #faad14;
            --danger: #ff4d4f;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --radius-lg: 12px;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Microsoft Yahei', sans-serif;
        }

        body {
            background-color: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.6;
        }

        .navbar {
            background-color: white;
            box-shadow: var(--shadow-md);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .nav-actions {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-600);
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: #096dd9;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #ff1f1f;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--gray-200);
            color: var(--gray-600);
        }

        .btn-outline:hover {
            background-color: var(--gray-50);
        }

        .container {
            max-width: 1400px;
            margin: 24px auto;
            padding: 0 24px;
        }

        .page-header {
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 0;
        }

        .page-desc {
            font-size: 14px;
            color: var(--gray-600);
            margin-top: 4px;
        }

        .card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 24px;
            margin-bottom: 24px;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-800);
        }

        .card-title i {
            color: var(--primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .stat-icon.primary {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .stat-icon.success {
            background-color: #f6ffed;
            color: var(--success);
        }

        .stat-icon.warning {
            background-color: #fffbe6;
            color: var(--warning);
        }

        .stat-icon.danger {
            background-color: #fff2f0;
            color: var(--danger);
        }

        .stat-content {
            flex: 1;
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray-600);
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-800);
            line-height: 1;
        }

        .stat-trend {
            font-size: 12px;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .trend-up {
            color: var(--success);
        }

        .trend-down {
            color: var(--danger);
        }

        .grid-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .chart-container {
            height: 280px;
            margin-top: 16px;
            position: relative;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 16px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .data-table th, .data-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .data-table th {
            background-color: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
        }

        .data-table tr:hover {
            background-color: var(--gray-50);
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background-color: #f6ffed;
            color: var(--success);
        }

        .badge-danger {
            background-color: #fff2f0;
            color: var(--danger);
        }

        .badge-warning {
            background-color: #fffbe6;
            color: var(--warning);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .action-card {
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }

        .action-card:hover {
            background-color: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        .action-card i {
            font-size: 24px;
            margin-bottom: 4px;
        }

        .action-card span {
            font-size: 14px;
            font-weight: 500;
        }

        @media (max-width: 992px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .chart-container {
                height: 240px;
            }
            .page-title {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .navbar {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            .nav-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <div class="navbar">
        <div class="logo">
            <i class="fas fa-school"></i>
            <span>学习积分管理系统</span>
        </div>
        <div class="nav-actions">
            <div class="user-info">
                <i class="fas fa-user-shield"></i>
                <span><?= $adminUsername ?></span>
            </div>
            <a href="admin_overall_analysis.php" class="btn btn-primary">
                <i class="fas fa-chart-pie"></i> 详细分析
            </a>
            <a href="logout.php" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i> 退出
            </a>
        </div>
    </div>

    <!-- 主内容区 -->
    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">管理员仪表盘</h1>
                <p class="page-desc">今日日期：<?= date('Y年m月d日') ?> | 最后更新：<?= date('H:i:s') ?></p>
            </div>
            <div>
                <button class="btn btn-outline">
                    <i class="fas fa-sync-alt"></i> 刷新数据
                </button>
                <button class="btn btn-primary">
                    <i class="fas fa-download"></i> 导出报表
                </button>
            </div>
        </div>

        <!-- 核心数据概览 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">总学生数</div>
                    <div class="stat-value"><?= $summary['total_students'] ?></div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 本学期新增 12 人
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-th-large"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">总班级数</div>
                    <div class="stat-value"><?= $summary['total_classes'] ?></div>
                    <div class="stat-trend">
                        <i class="fas fa-minus"></i> 无变化
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">今日总积分</div>
                    <div class="stat-value"><?= round($summary['today_points'], 2) ?></div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 较昨日 +12.5%
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">今日活跃学生</div>
                    <div class="stat-value"><?= $summary['today_active_students'] ?></div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 较昨日 +8 人
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">总行为记录</div>
                    <div class="stat-value"><?= $summary['total_records'] ?></div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 本周新增 248 条
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">问题行为学生</div>
                    <div class="stat-value"><?= $summary['penalty_students'] ?></div>
                    <div class="stat-trend trend-down">
                        <i class="fas fa-arrow-down"></i> 较上周 -3 人
                    </div>
                </div>
            </div>
        </div>

        <!-- 主要内容区域 -->
        <div class="grid-layout">
            <div class="left-column">
                <!-- 今日学习项目数据 -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-cubes"></i> 今日学习项目数据
                    </div>
                    <div class="chart-container">
                        <canvas id="todayProjectChart"></canvas>
                    </div>

                    <div class="table-container" style="margin-top: 24px;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>项目名称</th>
                                    <th>参与次数</th>
                                    <th>总积分</th>
                                    <th>平均积分</th>
                                    <th>占比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $todayTotalPoints = array_sum(array_column($todayProjectData, 'points'));
                                foreach ($todayProjectData as $type => $project): 
                                ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-<?= $projectsMap[$type]['icon'] ?>" style="margin-right: 8px;"></i>
                                        <?= $projectsMap[$type]['name'] ?>
                                    </td>
                                    <td><?= $project['count'] ?></td>
                                    <td><?= round($project['points'], 2) ?></td>
                                    <td><?= $project['count'] > 0 ? round($project['points'] / $project['count'], 2) : 0 ?></td>
                                    <td>
                                        <?= $todayTotalPoints > 0 ? round($project['points'] / $todayTotalPoints * 100, 1) : 0 ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 今日TOP班级 -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-trophy"></i> 今日积分TOP5班级
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>班级</th>
                                    <th>活跃学生数</th>
                                    <th>今日积分</th>
                                    <th>人均积分</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todayClassData as $index => $class): ?>
                                <tr>
                                    <td>
                                        <?php if ($index == 0): ?>
                                        <span class="badge badge-success">冠军</span>
                                        <?php elseif ($index == 1): ?>
                                        <span class="badge badge-warning">亚军</span>
                                        <?php elseif ($index == 2): ?>
                                        <span class="badge">季军</span>
                                        <?php else: ?>
                                        <?= $index + 1 ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $class['class'] ?></td>
                                    <td><?= $class['active_count'] ?></td>
                                    <td><?= round($class['today_points'], 2) ?></td>
                                    <td><?= $class['active_count'] > 0 ? round($class['today_points'] / $class['active_count'], 2) : 0 ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="right-column">
                <!-- 快速操作 -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-bolt"></i> 快速操作
                    </div>
                    <div class="quick-actions">
                        <a href="admin_overall_analysis.php" class="action-card">
                            <i class="fas fa-chart-line"></i>
                            <span>全校数据分析</span>
                        </a>
                        <a href="student_management.php" class="action-card">
                            <i class="fas fa-user-edit"></i>
                            <span>学生管理</span>
                        </a>
                        <a href="class_management.php" class="action-card">
                            <i class="fas fa-th-list"></i>
                            <span>班级管理</span>
                        </a>
                        <a href="points_management.php" class="action-card">
                            <i class="fas fa-coins"></i>
                            <span>积分管理</span>
                        </a>
                        <a href="system_settings.php" class="action-card">
                            <i class="fas fa-cog"></i>
                            <span>系统设置</span>
                        </a>
                        <a href="report_export.php" class="action-card">
                            <i class="fas fa-file-export"></i>
                            <span>报表导出</span>
                        </a>
                        <a href="admin_release_homework.php" class="action-card">
						    <i class="fas fa-homework"></i>
						    <span>发布作业</span>
						</a>
                    </div>
                </div>

                <!-- 最近问题行为 -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-exclamation-triangle"></i> 最近问题行为
                    </div>
                    <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>学生</th>
                                    <th>班级</th>
                                    <th>行为</th>
                                    <th>扣分</th>
                                    <th>时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentProblemData)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--gray-600);">暂无问题行为记录</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recentProblemData as $problem): ?>
                                <tr>
                                    <td><?= $problem['name'] ?> (<?= $problem['student_id'] ?>)</td>
                                    <td><?= $problem['class'] ?></td>
                                    <td>
                                        <span class="badge badge-danger"><?= $problem['action'] ?></span>
                                    </td>
                                    <td><?= round($problem['points_change'], 2) ?></td>
                                    <td><?= date('m-d H:i', strtotime($problem['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 16px; text-align: right;">
                        <a href="admin_problem_management.php" class="btn btn-outline">
                            <i class="fas fa-eye"></i> 查看全部
                        </a>
                    </div>
                </div>

                <!-- 系统状态 -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-server"></i> 系统状态
                    </div>
                    <div style="padding: 8px 0;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                            <span style="color: var(--gray-600);">数据库连接</span>
                            <span class="badge badge-success">正常</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                            <span style="color: var(--gray-600);">服务器负载</span>
                            <span class="badge badge-success">低 (18%)</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                            <span style="color: var(--gray-600);">数据备份</span>
                            <span class="badge badge-success">今日已备份</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--gray-600);">系统版本</span>
                            <span class="badge">v1.0.0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 待办事项 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-clipboard-list"></i> 管理员待办事项
            </div>
            <div style="padding: 16px; background-color: var(--gray-50); border-radius: 8px;">
                <ul style="list-style: none; padding-left: 24px;">
                    <li style="margin-bottom: 12px; position: relative;">
                        <i class="fas fa-circle" style="font-size: 6px; position: absolute; left: -12px; top: 8px; color: var(--primary);"></i>
                        审核 <?= count($recentProblemData) ?> 条问题行为记录
                    </li>
                    <li style="margin-bottom: 12px; position: relative;">
                        <i class="fas fa-circle" style="font-size: 6px; position: absolute; left: -12px; top: 8px; color: var(--primary);"></i>
                        生成本周学习情况分析报告
                    </li>
                    <li style="margin-bottom: 12px; position: relative;">
                        <i class="fas fa-circle" style="font-size: 6px; position: absolute; left: -12px; top: 8px; color: var(--primary);"></i>
                        处理 2 条学生积分申诉
                    </li>
                    <li style="position: relative;">
                        <i class="fas fa-circle" style="font-size: 6px; position: absolute; left: -12px; top: 8px; color: var(--warning);"></i>
                        提醒 3 个低活跃班级的班主任
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 图表脚本 -->
    <script>
        // 今日学习项目图表
        const todayProjectCtx = document.getElementById('todayProjectChart').getContext('2d');
        const todayProjectLabels = <?php 
            $labels = [];
            foreach ($todayProjectData as $type => $data) {
                $labels[] = $projectsMap[$type]['name'];
            }
            echo json_encode($labels);
        ?>;
        const todayProjectPoints = <?php 
            $points = [];
            foreach ($todayProjectData as $type => $data) {
                $points[] = round($data['points'], 2);
            }
            echo json_encode($points);
        ?>;
        const todayProjectCounts = <?php 
            $counts = [];
            foreach ($todayProjectData as $type => $data) {
                $counts[] = $data['count'];
            }
            echo json_encode($counts);
        ?>;

        new Chart(todayProjectCtx, {
            type: 'bar',
            data: {
                labels: todayProjectLabels,
                datasets: [
                    {
                        label: '今日积分',
                        data: todayProjectPoints,
                        backgroundColor: 'rgba(24, 144, 255, 0.7)',
                        borderColor: 'rgba(24, 144, 255, 1)',
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y'
                    },
                    {
                        label: '参与次数',
                        data: todayProjectCounts,
                        backgroundColor: 'rgba(82, 196, 26, 0.7)',
                        borderColor: 'rgba(82, 196, 26, 1)',
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: '今日各项目积分与参与次数' }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: '积分' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: '参与次数' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });

        // 刷新按钮功能
        document.querySelector('.btn-outline').addEventListener('click', function() {
            location.reload();
        });
    </script>
</body>
</html>