<?php
session_start(); // 启动会话
include 'config.php';

// ========== 身份验证 ==========
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php"); // 未登录/非教师跳转到登录页
    exit();
}

// 定义学习项目映射
$projectsMap = [
    'game' => ['name' => '对战游戏', 'desc' => '通过游戏化答题巩固知识点'],
    'quiz' => ['name' => '章节答题', 'desc' => '章节知识点掌握程度检测'],
    'answer' => ['name' => '回答问题', 'desc' => '课堂/课后主动答题表现'],
    'wrong_question' => ['name' => '错题修炼', 'desc' => '错题重做巩固薄弱点']
];

// ========== 1. 全校整体数据统计 ==========
// 基础统计
$schoolStatsSql = "
    SELECT 
        COUNT(DISTINCT s.student_id) as total_students,
        COUNT(DISTINCT s.class) as total_classes,
        SUM(pl.points_change) as total_points,
        COUNT(pl.id) as total_records,
        MIN(pl.created_at) as start_date,
        MAX(pl.created_at) as end_date,
        AVG(pl.points_change) as avg_points_per_record
    FROM students s
    LEFT JOIN point_logs pl ON s.student_id = pl.student_id
";
$schoolStatsResult = $conn->query($schoolStatsSql);
$schoolStats = $schoolStatsResult->fetch_assoc();

// 各项目全校积分统计
$projectStatsSql = "
    SELECT 
        'game' as type,
        SUM(CASE WHEN action LIKE '%对战游戏%' THEN points_change ELSE 0 END) as points,
        COUNT(CASE WHEN action LIKE '%对战游戏%' THEN 1 ELSE NULL END) as count
    FROM point_logs
    UNION ALL
    SELECT 
        'quiz' as type,
        SUM(CASE WHEN action LIKE '%章答题结束%' THEN points_change ELSE 0 END) as points,
        COUNT(CASE WHEN action LIKE '%章答题结束%' THEN 1 ELSE NULL END) as count
    FROM point_logs
    UNION ALL
    SELECT 
        'answer' as type,
        SUM(CASE WHEN action LIKE '%回答问题%' THEN points_change ELSE 0 END) as points,
        COUNT(CASE WHEN action LIKE '%回答问题%' THEN 1 ELSE NULL END) as count
    FROM point_logs
    UNION ALL
    SELECT 
        'wrong_question' as type,
        SUM(CASE WHEN action LIKE '%错题重做%' THEN points_change ELSE 0 END) as points,
        COUNT(CASE WHEN action LIKE '%错题重做%' THEN 1 ELSE NULL END) as count
    FROM point_logs
";
$projectStatsResult = $conn->query($projectStatsSql);
$projectStats = [];
while ($row = $projectStatsResult->fetch_assoc()) {
    $projectStats[$row['type']] = $row;
}

// ========== 2. 班级排名统计 ==========
$classRankSql = "
    SELECT 
        s.class,
        COUNT(DISTINCT s.student_id) as student_count,
        SUM(pl.points_change) as class_total_points,
        AVG(pl.points_change) as class_avg_points,
        COUNT(pl.id) as class_record_count
    FROM students s
    LEFT JOIN point_logs pl ON s.student_id = pl.student_id
    GROUP BY s.class
    ORDER BY class_total_points DESC
";
$classRankResult = $conn->query($classRankSql);
$classRankData = [];
while ($row = $classRankResult->fetch_assoc()) {
    $classRankData[] = $row;
}

// ========== 3. 学生表现分层统计 ==========
// 计算学生总积分分布
$studentPointsSql = "
    SELECT 
        student_id,
        SUM(points_change) as total_points
    FROM point_logs
    GROUP BY student_id
    ORDER BY total_points DESC
";
$studentPointsResult = $conn->query($studentPointsSql);
$studentPointsList = [];
$totalStudentPoints = 0;
while ($row = $studentPointsResult->fetch_assoc()) {
    $studentPointsList[] = $row['total_points'];
    $totalStudentPoints += $row['total_points'];
}

// 分层统计（优秀：前20%，良好：20%-50%，中等：50%-80%，待改进：后20%）
$studentCount = count($studentPointsList);
$excellentThreshold = $studentCount > 0 ? $studentPointsList[max(0, floor($studentCount * 0.2) - 1)] : 0;
$goodThreshold = $studentCount > 0 ? $studentPointsList[max(0, floor($studentCount * 0.5) - 1)] : 0;
$averageThreshold = $studentCount > 0 ? $studentPointsList[max(0, floor($studentCount * 0.8) - 1)] : 0;

$levelStatsSql = "
    SELECT 
        'excellent' as level,
        COUNT(*) as count,
        AVG(total_points) as avg_points
    FROM (SELECT student_id, SUM(points_change) as total_points FROM point_logs GROUP BY student_id) t
    WHERE total_points >= $excellentThreshold
    UNION ALL
    SELECT 
        'good' as level,
        COUNT(*) as count,
        AVG(total_points) as avg_points
    FROM (SELECT student_id, SUM(points_change) as total_points FROM point_logs GROUP BY student_id) t
    WHERE total_points >= $goodThreshold AND total_points < $excellentThreshold
    UNION ALL
    SELECT 
        'average' as level,
        COUNT(*) as count,
        AVG(total_points) as avg_points
    FROM (SELECT student_id, SUM(points_change) as total_points FROM point_logs GROUP BY student_id) t
    WHERE total_points >= $averageThreshold AND total_points < $goodThreshold
    UNION ALL
    SELECT 
        'improve' as level,
        COUNT(*) as count,
        AVG(total_points) as avg_points
    FROM (SELECT student_id, SUM(points_change) as total_points FROM point_logs GROUP BY student_id) t
    WHERE total_points < $averageThreshold
";
$levelStatsResult = $conn->query($levelStatsSql);
$levelStats = [];
$levelNames = ['excellent' => '优秀', 'good' => '良好', 'average' => '中等', 'improve' => '待改进'];
while ($row = $levelStatsResult->fetch_assoc()) {
    $row['level_name'] = $levelNames[$row['level']];
    $levelStats[] = $row;
}

// ========== 4. 时间趋势统计 ==========
$trendSql = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(points_change) as monthly_points,
        COUNT(DISTINCT student_id) as active_students,
        COUNT(id) as monthly_records
    FROM point_logs
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
";
$trendResult = $conn->query($trendSql);
$trendData = [];
while ($row = $trendResult->fetch_assoc()) {
    $trendData[] = $row;
}

// ========== 5. 问题行为统计 ==========
$problemStatsSql = "
    SELECT 
        COUNT(*) as penalty_count,
        SUM(points_change) as penalty_points,
        COUNT(DISTINCT student_id) as penalty_students
    FROM point_logs
    WHERE points_change < 0 OR action LIKE '%睡觉%' OR action LIKE '%不交%'
";
$problemStatsResult = $conn->query($problemStatsSql);
$problemStats = $problemStatsResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>全校学习情况分析报告（教师端）</title>
    <link rel="stylesheet" href="fontawesome-free-6.4.0-web/css/all.min.css">
    <script src="js/chart.umd.min.js"></script>
    <script src="js/echarts.min.js"></script>
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

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .container {
            max-width: 1400px;
            margin: 24px auto;
            padding: 0 24px;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .page-desc {
            font-size: 14px;
            color: var(--gray-600);
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-item {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 20px 16px;
            text-align: center;
            border: 1px solid var(--gray-100);
        }

        .stat-item .label {
            font-size: 14px;
            color: var(--gray-600);
            margin-bottom: 8px;
        }

        .stat-item .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .stat-item .unit {
            font-size: 12px;
            color: var(--gray-600);
        }

        .chart-container {
            height: 360px;
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

        .badge-excellent {
            background-color: #e6f7ff;
            color: var(--primary);
        }

        .badge-good {
            background-color: #f6ffed;
            color: var(--success);
        }

        .badge-average {
            background-color: #fffbe6;
            color: var(--warning);
        }

        .badge-improve {
            background-color: #fff2f0;
            color: var(--danger);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 992px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .chart-container {
                height: 300px;
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
            }
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <div class="navbar">
        <div class="logo">
            <i class="fas fa-chalkboard-teacher"></i>
            <span>教师分析系统</span>
        </div>
        <div class="nav-actions">
            <a href="admin_board.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i> 仪表盘
            </a>
            <a href="logout.php" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i> 退出登录
            </a>
        </div>
    </div>

    <!-- 主内容区 -->
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">全校学习情况综合分析报告</h1>
            <p class="page-desc">数据时间范围：<?= $schoolStats['start_date'] ?> 至 <?= $schoolStats['end_date'] ?></p>
        </div>

        <!-- 1. 全校概览统计 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-globe-asia"></i> 全校基础数据概览
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="label">学生总数</div>
                    <div class="value"><?= $schoolStats['total_students'] ?></div>
                    <div class="unit">人</div>
                </div>
                <div class="stat-item">
                    <div class="label">班级总数</div>
                    <div class="value"><?= $schoolStats['total_classes'] ?></div>
                    <div class="unit">个</div>
                </div>
                <div class="stat-item">
                    <div class="label">总积分</div>
                    <div class="value"><?= round($schoolStats['total_points'], 2) ?></div>
                    <div class="unit">分</div>
                </div>
                <div class="stat-item">
                    <div class="label">行为记录总数</div>
                    <div class="value"><?= $schoolStats['total_records'] ?></div>
                    <div class="unit">条</div>
                </div>
                <div class="stat-item">
                    <div class="label">平均单次积分</div>
                    <div class="value"><?= round($schoolStats['avg_points_per_record'], 2) ?></div>
                    <div class="unit">分/次</div>
                </div>
                <div class="stat-item">
                    <div class="label">问题行为学生数</div>
                    <div class="value"><?= $problemStats['penalty_students'] ?></div>
                    <div class="unit">人</div>
                </div>
            </div>
        </div>

        <!-- 2. 班级排名与项目统计 -->
        <div class="grid-2">
            <!-- 班级排名 -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-ranking-star"></i> 班级积分排名
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>班级</th>
                                <th>学生数</th>
                                <th>总积分</th>
                                <th>平均积分</th>
                                <th>记录数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classRankData as $index => $class): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= $class['class'] ?></td>
                                <td><?= $class['student_count'] ?></td>
                                <td><?= round($class['class_total_points'], 2) ?></td>
                                <td><?= round($class['class_avg_points'], 2) ?></td>
                                <td><?= $class['class_record_count'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 项目统计 -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-cubes"></i> 各学习项目统计
                </div>
                <div class="chart-container">
                    <canvas id="projectChart"></canvas>
                </div>
                <div class="table-container" style="margin-top: 24px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>项目名称</th>
                                <th>总积分</th>
                                <th>参与次数</th>
                                <th>平均积分</th>
                                <th>占比</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projectStats as $type => $project): ?>
                            <tr>
                                <td><?= $projectsMap[$type]['name'] ?></td>
                                <td><?= round($project['points'], 2) ?></td>
                                <td><?= $project['count'] ?></td>
                                <td><?= $project['count'] > 0 ? round($project['points'] / $project['count'], 2) : 0 ?></td>
                                <td><?= round($project['points'] / $schoolStats['total_points'] * 100, 1) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. 学生分层与时间趋势 -->
        <div class="grid-2">
            <!-- 学生分层统计 -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-users-viewfinder"></i> 学生表现分层统计
                </div>
                <div class="chart-container">
                    <canvas id="levelChart"></canvas>
                </div>
                <div class="table-container" style="margin-top: 24px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>表现等级</th>
                                <th>学生数</th>
                                <th>占比</th>
                                <th>平均积分</th>
                                <th>积分门槛</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($levelStats as $level): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-<?= $level['level'] ?>">
                                        <?= $level['level_name'] ?>
                                    </span>
                                </td>
                                <td><?= $level['count'] ?></td>
                                <td><?= round($level['count'] / $studentCount * 100, 1) ?>%</td>
                                <td><?= round($level['avg_points'], 2) ?></td>
                                <td>
                                    <?php if ($level['level'] == 'excellent'): ?>
                                    ≥ <?= round($excellentThreshold, 2) ?>
                                    <?php elseif ($level['level'] == 'good'): ?>
                                    <?= round($goodThreshold, 2) ?> - <?= round($excellentThreshold - 0.01, 2) ?>
                                    <?php elseif ($level['level'] == 'average'): ?>
                                    <?= round($averageThreshold, 2) ?> - <?= round($goodThreshold - 0.01, 2) ?>
                                    <?php else: ?>
                                    < <?= round($averageThreshold, 2) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 时间趋势 -->
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-chart-line"></i> 月度学习趋势
                </div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 4. 问题行为分析 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-exclamation-triangle"></i> 问题行为分析
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="label">问题行为总数</div>
                    <div class="value"><?= $problemStats['penalty_count'] ?></div>
                    <div class="unit">次</div>
                </div>
                <div class="stat-item">
                    <div class="label">问题行为总扣分</div>
                    <div class="value"><?= round($problemStats['penalty_points'], 2) ?></div>
                    <div class="unit">分</div>
                </div>
                <div class="stat-item">
                    <div class="label">问题行为学生占比</div>
                    <div class="value"><?= round($problemStats['penalty_students'] / $schoolStats['total_students'] * 100, 1) ?></div>
                    <div class="unit">%</div>
                </div>
                <div class="stat-item">
                    <div class="label">平均单次扣分值</div>
                    <div class="value"><?= $problemStats['penalty_count'] > 0 ? round($problemStats['penalty_points'] / $problemStats['penalty_count'], 2) : 0 ?></div>
                    <div class="unit">分/次</div>
                </div>
            </div>

            <!-- 问题行为详情 -->
            <div class="table-container" style="margin-top: 24px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>学生ID</th>
                            <th>学生姓名</th>
                            <th>班级</th>
                            <th>问题行为次数</th>
                            <th>总扣分数</th>
                            <th>最近问题时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $problemStudentSql = "
                            SELECT 
                                s.student_id,
                                s.name,
                                s.class,
                                COUNT(pl.id) as penalty_count,
                                SUM(pl.points_change) as penalty_points,
                                MAX(pl.created_at) as last_penalty_time
                            FROM students s
                            JOIN point_logs pl ON s.student_id = pl.student_id
                            WHERE pl.points_change < 0 OR pl.action LIKE '%睡觉%' OR pl.action LIKE '%不交%'
                            GROUP BY s.student_id, s.name, s.class
                            ORDER BY penalty_points ASC
                        ";
                        $problemStudentResult = $conn->query($problemStudentSql);
                        while ($student = $problemStudentResult->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?= $student['student_id'] ?></td>
                            <td><?= $student['name'] ?></td>
                            <td><?= $student['class'] ?></td>
                            <td><?= $student['penalty_count'] ?></td>
                            <td><?= round($student['penalty_points'], 2) ?></td>
                            <td><?= $student['last_penalty_time'] ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 5. 教学建议 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-lightbulb"></i> 教学改进建议
            </div>
            <div style="padding: 16px 0;">
                <ul style="list-style: none; padding-left: 24px;">
                    <li style="margin-bottom: 16px; position: relative;">
                        <i class="fas fa-check-circle" style="color: var(--primary); position: absolute; left: -24px; top: 4px;"></i>
                        <strong>班级均衡发展：</strong> 重点关注积分排名后30%的班级，分析其薄弱环节，提供针对性指导
                    </li>
                    <li style="margin-bottom: 16px; position: relative;">
                        <i class="fas fa-check-circle" style="color: var(--primary); position: absolute; left: -24px; top: 4px;"></i>
                        <strong>学习项目优化：</strong> "回答问题"参与度较低（<?= $projectStats['answer']['count'] ?>次），建议设计更多课堂互动环节
                    </li>
                    <li style="margin-bottom: 16px; position: relative;">
                        <i class="fas fa-check-circle" style="color: var(--primary); position: absolute; left: -24px; top: 4px;"></i>
                        <strong>问题学生干预：</strong> 对<?= $problemStats['penalty_students'] ?>名有问题行为的学生建立一对一帮扶机制
                    </li>
                    <li style="margin-bottom: 16px; position: relative;">
                        <i class="fas fa-check-circle" style="color: var(--primary); position: absolute; left: -24px; top: 4px;"></i>
                        <strong>时间趋势利用：</strong> 根据月度趋势，在积分高峰期（<?= $trendData[array_search(max(array_column($trendData, 'monthly_points')), array_column($trendData, 'monthly_points'))]['month'] ?>）加强教学强度
                    </li>
                    <li style="margin-bottom: 16px; position: relative;">
                        <i class="fas fa-check-circle" style="color: var(--primary); position: absolute; left: -24px; top: 4px;"></i>
                        <strong>分层教学实施：</strong> 对优秀学生提供拓展内容，对20%待改进学生制定基础提升计划
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 图表脚本 -->
    <script>
        // 1. 项目统计图表
        const projectCtx = document.getElementById('projectChart').getContext('2d');
        const projectLabels = <?php echo json_encode(array_column($projectsMap, 'name')); ?>;
        const projectPoints = <?php 
            $points = [];
            foreach (['game', 'quiz', 'answer', 'wrong_question'] as $type) {
                $points[] = round($projectStats[$type]['points'], 2);
            }
            echo json_encode($points);
        ?>;
        const projectCounts = <?php 
            $counts = [];
            foreach (['game', 'quiz', 'answer', 'wrong_question'] as $type) {
                $counts[] = $projectStats[$type]['count'];
            }
            echo json_encode($counts);
        ?>;

        new Chart(projectCtx, {
            type: 'bar',
            data: {
                labels: projectLabels,
                datasets: [
                    {
                        label: '总积分',
                        data: projectPoints,
                        backgroundColor: 'rgba(24, 144, 255, 0.7)',
                        borderColor: 'rgba(24, 144, 255, 1)',
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y'
                    },
                    {
                        label: '参与次数',
                        data: projectCounts,
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
                    title: { display: true, text: '各项目积分与参与次数对比' }
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

        // 2. 学生分层图表
        const levelCtx = document.getElementById('levelChart').getContext('2d');
        const levelLabels = <?php echo json_encode(array_column($levelStats, 'level_name')); ?>;
        const levelCounts = <?php echo json_encode(array_column($levelStats, 'count')); ?>;
        const levelColors = ['#1890ff', '#52c41a', '#faad14', '#ff4d4f'];

        new Chart(levelCtx, {
            type: 'doughnut',
            data: {
                labels: levelLabels,
                datasets: [{
                    data: levelCounts,
                    backgroundColor: levelColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' },
                    title: { display: true, text: '学生表现分布' }
                },
                cutout: '60%'
            }
        });

        // 3. 时间趋势图表
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendLabels = <?php echo json_encode(array_column($trendData, 'month')); ?>;
        const trendPoints = <?php echo json_encode(array_column($trendData, 'monthly_points')); ?>;
        const trendStudents = <?php echo json_encode(array_column($trendData, 'active_students')); ?>;

        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: '月度总积分',
                        data: trendPoints,
                        borderColor: 'rgba(24, 144, 255, 1)',
                        backgroundColor: 'rgba(24, 144, 255, 0.1)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: '活跃学生数',
                        data: trendStudents,
                        borderColor: 'rgba(82, 196, 26, 1)',
                        backgroundColor: 'rgba(82, 196, 26, 0.1)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: '月度积分与活跃学生趋势' }
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
                        title: { display: true, text: '活跃学生数' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    </script>
</body>
</html>