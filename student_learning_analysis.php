<?php
include 'config.php';

// 定义学习项目映射
$projectsMap = [
    'game' => ['name' => '对战游戏', 'desc' => '通过游戏化答题巩固知识点', 'suggestion' => '保持高频次参与，注重错题复盘'],
    'quiz' => ['name' => '章节答题', 'desc' => '章节知识点掌握程度检测', 'suggestion' => '针对错误章节进行专项复习'],
    'answer' => ['name' => '回答问题', 'desc' => '课堂/课后主动答题表现', 'suggestion' => '主动拓展同类问题解题思路'],
    'wrong_question' => ['name' => '错题修炼', 'desc' => '错题重做巩固薄弱点', 'suggestion' => '建立错题本，定期回顾']
];

// 获取学生列表（用于选择学生）
$studentSql = "SELECT student_id, name, class FROM students ORDER BY class, name";
$studentResult = $conn->query($studentSql);
$students = [];
while ($row = $studentResult->fetch_assoc()) {
    $students[] = $row;
}

// 初始化选中学生
$selectedStudentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : ($students[0]['student_id'] ?? 0);

// 获取当前学生所在班级
$classSql = "SELECT class FROM students WHERE student_id = $selectedStudentId";
$classResult = $conn->query($classSql);
$currentClass = $classResult->fetch_assoc()['class'] ?? '';

// ========== 1. 获取学生基础表现数据（所有时间） ==========
$performanceSql = "
    SELECT 
        'total' as type, 
        SUM(points_change) as points,
        0 as project_key
    FROM point_logs 
    WHERE student_id = $selectedStudentId
    UNION ALL
    SELECT 
        'game' as type,
        SUM(CASE WHEN action LIKE '%对战游戏%' THEN points_change ELSE 0 END) as points,
        1 as project_key
    FROM point_logs 
    WHERE student_id = $selectedStudentId
    UNION ALL
    SELECT 
        'quiz' as type,
        SUM(CASE WHEN action LIKE '%章答题结束%' THEN points_change ELSE 0 END) as points,
        2 as project_key
    FROM point_logs 
    WHERE student_id = $selectedStudentId
    UNION ALL
    SELECT 
        'answer' as type,
        SUM(CASE WHEN action LIKE '%回答问题%' THEN points_change ELSE 0 END) as points,
        3 as project_key
    FROM point_logs 
    WHERE student_id = $selectedStudentId
    UNION ALL
    SELECT 
        'wrong_question' as type,
        SUM(CASE WHEN action LIKE '%错题重做%' THEN points_change ELSE 0 END) as points,
        4 as project_key
    FROM point_logs 
    WHERE student_id = $selectedStudentId
";
$performanceResult = $conn->query($performanceSql);
$performanceData = [];
$totalPoints = 0;
while ($row = $performanceResult->fetch_assoc()) {
    $performanceData[$row['type']] = $row['points'];
    if ($row['type'] == 'total') $totalPoints = $row['points'];
}

// ========== 2. 班级排名对比（所有时间） ==========
$rankSql = "
    SELECT 
        COUNT(*) + 1 as rank,
        (SELECT COUNT(*) FROM students WHERE class = '$currentClass') as class_total
    FROM students s
    JOIN (
        SELECT student_id, SUM(points_change) as total FROM point_logs GROUP BY student_id
    ) pl ON s.student_id = pl.student_id
    WHERE s.class = '$currentClass'
    AND pl.total > (SELECT SUM(points_change) FROM point_logs WHERE student_id = $selectedStudentId)
";
$rankResult = $conn->query($rankSql);
$rankData = $rankResult->fetch_assoc();
$classRank = $rankData['rank'] ?? '未上榜';
$classTotal = $rankData['class_total'] ?? 0;
$rankPercentile = $classTotal > 0 ? round(($classTotal - $classRank + 1) / $classTotal * 100, 1) : 0;

// ========== 3. 优势/改进方向分析（班级平均分：按学生人数计算） ==========
$strengths = [];
$improvements = [];
$projectScores = [
    'game' => $performanceData['game'] ?? 0,
    'quiz' => $performanceData['quiz'] ?? 0,
    'answer' => $performanceData['answer'] ?? 0,
    'wrong_question' => $performanceData['wrong_question'] ?? 0
];

// 计算班级各项目平均分（按学生人数计算：每个学生该项目总分 / 班级学生数）
$classAvgSql = "
    SELECT 
        -- 对战游戏平均分：班级所有学生该项目总分 / 班级学生数
        (SELECT COALESCE(SUM(student_game_total), 0) FROM (
            SELECT s.student_id, COALESCE(SUM(CASE WHEN pl.action LIKE '%对战游戏%' THEN pl.points_change ELSE 0 END), 0) as student_game_total
            FROM students s
            LEFT JOIN point_logs pl ON s.student_id = pl.student_id
            WHERE s.class = '$currentClass'
            GROUP BY s.student_id
        ) t) / (SELECT COUNT(*) FROM students WHERE class = '$currentClass') as game_avg,
        
        -- 章节答题平均分
        (SELECT COALESCE(SUM(student_quiz_total), 0) FROM (
            SELECT s.student_id, COALESCE(SUM(CASE WHEN pl.action LIKE '%章答题结束%' THEN pl.points_change ELSE 0 END), 0) as student_quiz_total
            FROM students s
            LEFT JOIN point_logs pl ON s.student_id = pl.student_id
            WHERE s.class = '$currentClass'
            GROUP BY s.student_id
        ) t) / (SELECT COUNT(*) FROM students WHERE class = '$currentClass') as quiz_avg,
        
        -- 回答问题平均分
        (SELECT COALESCE(SUM(student_answer_total), 0) FROM (
            SELECT s.student_id, COALESCE(SUM(CASE WHEN pl.action LIKE '%回答问题%' THEN pl.points_change ELSE 0 END), 0) as student_answer_total
            FROM students s
            LEFT JOIN point_logs pl ON s.student_id = pl.student_id
            WHERE s.class = '$currentClass'
            GROUP BY s.student_id
        ) t) / (SELECT COUNT(*) FROM students WHERE class = '$currentClass') as answer_avg,
        
        -- 错题修炼平均分
        (SELECT COALESCE(SUM(student_wq_total), 0) FROM (
            SELECT s.student_id, COALESCE(SUM(CASE WHEN pl.action LIKE '%错题重做%' THEN pl.points_change ELSE 0 END), 0) as student_wq_total
            FROM students s
            LEFT JOIN point_logs pl ON s.student_id = pl.student_id
            WHERE s.class = '$currentClass'
            GROUP BY s.student_id
        ) t) / (SELECT COUNT(*) FROM students WHERE class = '$currentClass') as wrong_question_avg
";
$classAvgResult = $conn->query($classAvgSql);
$classAverages = $classAvgResult->fetch_assoc();

// 分析优势（高于班级平均分1.5倍以上）和改进方向（低于班级平均分或0）
foreach ($projectScores as $key => $score) {
    $classAvg = $classAverages[$key . '_avg'] ?? 0;
    // 处理除数为0的情况（班级无学生数据）
    $classAvg = is_nan($classAvg) ? 0 : $classAvg;
    
    if ($score >= $classAvg * 1.5 && $score > 0) {
        $strengths[] = [
            'name' => $projectsMap[$key]['name'],
            'desc' => $projectsMap[$key]['desc'],
            'score' => $score,
            'class_avg' => round($classAvg, 1)
        ];
    } elseif ($score < $classAvg || $score == 0) {
        $improvements[] = [
            'name' => $projectsMap[$key]['name'],
            'desc' => $projectsMap[$key]['desc'],
            'score' => $score,
            'class_avg' => round($classAvg, 1),
            'suggestion' => $projectsMap[$key]['suggestion']
        ];
    }
}

// ========== 4. 网状图数据（所有时间） ==========
$netChartSql = "
    SELECT 
        -- 各项目参与频次
        SUM(CASE WHEN action LIKE '%对战游戏%' THEN 1 ELSE 0 END) as game_count,
        SUM(CASE WHEN action LIKE '%章答题结束%' THEN 1 ELSE 0 END) as quiz_count,
        SUM(CASE WHEN action LIKE '%回答问题%' THEN 1 ELSE 0 END) as answer_count,
        SUM(CASE WHEN action LIKE '%错题重做%' THEN 1 ELSE 0 END) as wrong_question_count,
        -- 各项目平均积分（代表完成质量）
        AVG(CASE WHEN action LIKE '%对战游戏%' THEN points_change ELSE NULL END) as game_quality,
        AVG(CASE WHEN action LIKE '%章答题结束%' THEN points_change ELSE NULL END) as quiz_quality,
        AVG(CASE WHEN action LIKE '%回答问题%' THEN points_change ELSE NULL END) as answer_quality,
        AVG(CASE WHEN action LIKE '%错题重做%' THEN points_change ELSE NULL END) as wrong_question_quality
    FROM point_logs
    WHERE student_id = $selectedStudentId
";
$netChartResult = $conn->query($netChartSql);
$netChartData = $netChartResult->fetch_assoc();

// 数据标准化（0-100分制）
$maxCount = max(
    $netChartData['game_count'] ?? 0,
    $netChartData['quiz_count'] ?? 0,
    $netChartData['answer_count'] ?? 0,
    $netChartData['wrong_question_count'] ?? 0,
    1 // 避免除以0
);
$maxQuality = max(
    $netChartData['game_quality'] ?? 0,
    $netChartData['quiz_quality'] ?? 0,
    $netChartData['answer_quality'] ?? 0,
    $netChartData['wrong_question_quality'] ?? 0,
    1 // 避免除以0
);

// 构建网状图维度数据
$netChartDimensions = [
    ['name' => '对战游戏', 'count' => round(($netChartData['game_count'] ?? 0) / $maxCount * 100), 'quality' => round(($netChartData['game_quality'] ?? 0) / $maxQuality * 100)],
    ['name' => '章节答题', 'count' => round(($netChartData['quiz_count'] ?? 0) / $maxCount * 100), 'quality' => round(($netChartData['quiz_quality'] ?? 0) / $maxQuality * 100)],
    ['name' => '回答问题', 'count' => round(($netChartData['answer_count'] ?? 0) / $maxCount * 100), 'quality' => round(($netChartData['answer_quality'] ?? 0) / $maxQuality * 100)],
    ['name' => '错题修炼', 'count' => round(($netChartData['wrong_question_count'] ?? 0) / $maxCount * 100), 'quality' => round(($netChartData['wrong_question_quality'] ?? 0) / $maxQuality * 100)]
];

// ========== 5. 生成学习建议 ==========
$suggestions = [];
// 基于排名的建议
if ($rankPercentile < 30) {
    $suggestions[] = "你的总积分排名处于班级后30%，建议增加每日学习时长，优先完成章节答题和错题重做任务";
} elseif ($rankPercentile < 70) {
    $suggestions[] = "你的总积分排名处于班级中游，可针对性强化薄弱项目（如" . ($improvements[0]['name'] ?? '错题修炼') . "），提升排名";
} else {
    $suggestions[] = "你的总积分排名处于班级前30%，建议保持优势项目，尝试拓展难度更高的学习内容";
}

// 基于网状图的维度建议
$lowestDimension = array_reduce($netChartDimensions, function($carry, $item) {
    $avg = ($item['count'] + $item['quality']) / 2;
    if (!$carry || $avg < ($carry['avg'] ?? 100)) {
        return ['name' => $item['name'], 'avg' => $avg];
    }
    return $carry;
});
if ($lowestDimension['avg'] < 60) {
    $suggestions[] = "你在「{$lowestDimension['name']}」维度的表现较弱，建议提升参与频次和完成质量";
}

// 基于项目的建议
if (empty($improvements)) {
    $suggestions[] = "各学习项目表现均衡，建议尝试跨项目融合学习（如做完章节答题后立即进行错题重做）";
} else {
    $suggestions[] = "重点改进方向：{$improvements[0]['name']}，具体建议：{$improvements[0]['suggestion']}";
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生学习情况分析报告</title>
    <!-- 引入图标库 -->
    <link rel="fontawesome-free-6.4.0-web/css/all.min.css">
    <!-- 引入图表库 -->
    <script src="js/chart.umd.min.js"></script>
    <script src="js/echarts.min.js"></script>
    <style>
        :root {
            /* 主题色 */
            --primary: #4096ff;
            --primary-light: #e8f3ff;
            --success: #52c41a;
            --success-light: #f6ffed;
            --warning: #faad14;
            --warning-light: #fffbe6;
            --danger: #ff4d4f;
            --danger-light: #fff2f0;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            /* 圆角和阴影 */
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            /* 过渡动画 */
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
            padding: 0;
            line-height: 1.6;
        }

        /* 顶部导航栏 */
        .navbar {
            background-color: white;
            box-shadow: var(--shadow-sm);
            padding: 16px 24px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .logo i {
            font-size: 24px;
        }

        /* 主容器 */
        .container {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 24px;
        }

        /* 学生选择器 */
        .student-select-wrapper {
            background: white;
            padding: 20px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        .student-select {
            padding: 8px 16px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-sm);
            min-width: 220px;
            background: white;
            color: var(--gray-700);
            font-size: 14px;
            transition: var(--transition);
            cursor: pointer;
        }

        .student-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(64, 150, 255, 0.2);
        }

        /* 卡片样式 */
        .card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 24px;
            margin-bottom: 24px;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: var(--primary);
            font-size: 18px;
        }

        /* 概览数据 */
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .summary-item {
            text-align: center;
            padding: 20px 16px;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-100);
            transition: var(--transition);
        }

        .summary-item:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            transform: translateY(-3px);
        }

        .summary-item .label {
            font-size: 14px;
            color: var(--gray-600);
            margin-bottom: 8px;
        }

        .summary-item .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin: 4px 0;
            line-height: 1.2;
        }

        .summary-item .desc {
            font-size: 12px;
            color: var(--gray-600);
        }

        /* 分析网格 */
        .analysis-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 16px;
        }

        @media (max-width: 768px) {
            .analysis-grid {
                grid-template-columns: 1fr;
            }
            .summary {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* 优势/改进项 */
        .strength-item, .improve-item {
            padding: 16px;
            margin-bottom: 12px;
            border-radius: var(--radius-md);
            transition: var(--transition);
        }

        .strength-item {
            background: var(--success-light);
            border: 1px solid var(--success);
            border-left: 4px solid var(--success);
        }

        .strength-item:hover {
            box-shadow: 0 2px 8px rgba(82, 196, 26, 0.15);
        }

        .improve-item {
            background: var(--warning-light);
            border: 1px solid var(--warning);
            border-left: 4px solid var(--warning);
        }

        .improve-item:hover {
            box-shadow: 0 2px 8px rgba(250, 173, 20, 0.15);
        }

        .item-title {
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .item-title i {
            font-size: 14px;
        }

        .item-desc {
            font-size: 14px;
            color: var(--gray-600);
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .item-metric {
            font-size: 13px;
            color: var(--gray-600);
            background: white;
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            display: inline-block;
        }

        /* 建议列表 */
        .suggestion-list {
            list-style: none;
            margin-top: 16px;
        }

        .suggestion-list li {
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-100);
            position: relative;
            padding-left: 32px;
            background: var(--gray-50);
            margin-bottom: 8px;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .suggestion-list li:hover {
            background: var(--primary-light);
            transform: translateX(4px);
        }

        .suggestion-list li:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .suggestion-list li:before {
            content: '\f00c';
            font-family: 'FontAwesome';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 14px;
        }

        /* 图表容器 */
        .chart-container {
            height: 320px;
            margin-top: 16px;
            position: relative;
        }

        .net-chart-container {
            width: 100%;
            height: 420px;
            margin-top: 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-100);
            padding: 8px;
        }

        /* 空数据样式 */
        .empty-data {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-600);
            font-size: 14px;
        }

        .empty-data i {
            font-size: 48px;
            margin-bottom: 16px;
            color: var(--gray-300);
        }

        /* 响应式调整 */
        @media (max-width: 768px) {
            .navbar-container {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .student-select-wrapper {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .page-title {
                font-size: 20px;
            }

            .summary {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 280px;
            }

            .net-chart-container {
                height: 360px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 16px;
            }

            .card {
                padding: 16px;
            }

            .chart-container {
                height: 240px;
            }

            .net-chart-container {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <div class="navbar">
        <div class="navbar-container">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <span>学生学习分析系统</span>
            </div>
        </div>
    </div>

    <!-- 主内容区 -->
    <div class="container">
        <!-- 学生选择器 -->
        <div class="student-select-wrapper">
            <h1 class="page-title">学生学习情况分析报告</h1>
            <select class="student-select" onchange="window.location.href='?student_id='+this.value">
                <?php foreach ($students as $student): ?>
                <option value="<?= $student['student_id'] ?>" 
                    <?= $student['student_id'] == $selectedStudentId ? 'selected' : '' ?>>
                    <?= $student['class'] ?> - <?= $student['name'] ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 1. 学习表现概览 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-chart-pie"></i>
                学习表现概览
            </div>
            <div class="summary">
                <div class="summary-item">
                    <div class="label">总积分</div>
                    <div class="value"><?= $totalPoints ?></div>
                    <div class="desc">累计学习积分</div>
                </div>
                <div class="summary-item">
                    <div class="label">班级排名</div>
                    <div class="value"><?= $classRank ?>/<?= $classTotal ?></div>
                    <div class="desc">前<?= $rankPercentile ?>%</div>
                </div>
                <div class="summary-item">
                    <div class="label">优势项目数</div>
                    <div class="value"><?= count($strengths) ?></div>
                    <div class="desc">高于班级平均水平</div>
                </div>
                <div class="summary-item">
                    <div class="label">待改进项目数</div>
                    <div class="value"><?= count($improvements) ?></div>
                    <div class="desc">需重点提升</div>
                </div>
            </div>

            <!-- 各项目积分对比图表 -->
            <div class="chart-container">
                <canvas id="projectChart"></canvas>
            </div>
        </div>

        <!-- 2. 优势与改进方向 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-balance-scale"></i>
                学习优势 & 改进方向
            </div>
            <div class="analysis-grid">
                <!-- 优势分析 -->
                <div>
                    <h3 style="font-size: 16px; margin-bottom: 16px; color: var(--success); display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-star"></i> 学习优势
                    </h3>
                    <?php if (!empty($strengths)): ?>
                        <?php foreach ($strengths as $item): ?>
                        <div class="strength-item">
                            <div class="item-title">
                                <i class="fas fa-check-circle"></i>
                                <?= $item['name'] ?>
                            </div>
                            <div class="item-desc"><?= $item['desc'] ?></div>
                            <div class="item-metric">
                                你的积分：<?= $item['score'] ?> | 班级平均：<?= $item['class_avg'] ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-data">
                            <i class="fas fa-search"></i>
                            <p>暂未发现明显优势项目</p>
                            <p style="margin-top: 8px;">建议全面提升各项目参与度</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 改进方向 -->
                <div>
                    <h3 style="font-size: 16px; margin-bottom: 16px; color: var(--warning); display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-chart-line"></i> 改进方向
                    </h3>
                    <?php if (!empty($improvements)): ?>
                        <?php foreach ($improvements as $item): ?>
                        <div class="improve-item">
                            <div class="item-title">
                                <i class="fas fa-arrow-up"></i>
                                <?= $item['name'] ?>
                            </div>
                            <div class="item-desc"><?= $item['desc'] ?></div>
                            <div class="item-metric">
                                你的积分：<?= $item['score'] ?> | 班级平均：<?= $item['class_avg'] ?>
                            </div>
                            <div style="font-size: 14px; color: var(--warning); margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--gray-200);">
                                <i class="fas fa-lightbulb"></i> 建议：<?= $item['suggestion'] ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-data">
                            <i class="fas fa-trophy"></i>
                            <p>各项目表现均优于班级平均</p>
                            <p style="margin-top: 8px;">建议挑战更高难度内容</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 3. 个性化学习建议 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-lightbulb"></i>
                个性化学习建议
            </div>
            <ul class="suggestion-list">
                <?php foreach ($suggestions as $suggestion): ?>
                <li><?= $suggestion ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- 4. 学习表现网状图（替代热力图） -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-project-diagram"></i>
                学习表现多维度分析（网状图）
            </div>
            <p style="font-size: 14px; color: var(--gray-600); margin-bottom: 16px; line-height: 1.6;">
                网状图展示各学习项目的参与频次（蓝色）和完成质量（橙色），数值为0-100分制标准化结果
            </p>
            <div class="net-chart-container" id="netChart"></div>
        </div>
    </div>

    <script>
        // ========== 1. 项目积分对比图表 ==========
        const projectCtx = document.getElementById('projectChart').getContext('2d');
        const projectData = {
            labels: [
                <?php foreach ($projectsMap as $key => $proj): ?>
                '<?= $proj['name'] ?>',
                <?php endforeach; ?>
            ],
            datasets: [
                {
                    label: '你的积分',
                    data: [
                        <?php foreach (['game', 'quiz', 'answer', 'wrong_question'] as $key): ?>
                        <?= $performanceData[$key] ?? 0 ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(64, 150, 255, 0.7)',
                    borderColor: 'rgba(64, 150, 255, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    barThickness: 28,
                },
                {
                    label: '班级平均分（按人数）',
                    data: [
                        <?php foreach (['game', 'quiz', 'answer', 'wrong_question'] as $key): ?>
                        <?= round($classAverages[$key . '_avg'] ?? 0, 1) ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(255, 77, 79, 0.7)',
                    borderColor: 'rgba(255, 77, 79, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    barThickness: 28,
                }
            ]
        };
        new Chart(projectCtx, {
            type: 'bar',
            data: projectData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'top',
                        labels: {
                            font: { size: 13 },
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    title: { 
                        display: true, 
                        text: '各学习项目积分对比（vs 班级平均）',
                        font: { size: 15, weight: '600' },
                        padding: { top: 10, bottom: 20 }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(31, 41, 55, 0.9)',
                        padding: 12,
                        cornerRadius: 6,
                        boxPadding: 4,
                        usePointStyle: true,
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        title: { 
                            display: true, 
                            text: '积分',
                            font: { size: 13, weight: '500' }
                        },
                        grid: {
                            color: 'rgba(229, 231, 235, 0.5)'
                        },
                        ticks: {
                            font: { size: 12 }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: { size: 12 }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                animation: {
                    duration: 800,
                    easing: 'easeOutQuart'
                }
            }
        });

        // ========== 2. 学习表现网状图（雷达图） ==========
        const netChartDom = document.getElementById('netChart');
        const netChart = echarts.init(netChartDom);
        
        // 构造网状图数据
        const netChartData = <?= json_encode($netChartDimensions) ?>;
        const categories = netChartData.map(item => item.name);
        const countData = netChartData.map(item => item.count);
        const qualityData = netChartData.map(item => item.quality);

        // 网状图配置
        const netChartOption = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'shadow'
                },
                backgroundColor: 'rgba(31, 41, 55, 0.9)',
                padding: 12,
                borderRadius: 6,
                textStyle: {
                    fontSize: 12
                },
                formatter: function(params) {
                    const index = params[0].dataIndex;
                    return `
                        <div style="font-weight: 600; margin-bottom: 4px;">${categories[index]}</div>
                        <div>参与频次：${countData[index]}分</div>
                        <div>完成质量：${qualityData[index]}分</div>
                    `;
                }
            },
            legend: {
                data: ['参与频次', '完成质量'],
                top: 'top',
                textStyle: {
                    fontSize: 13
                },
                icon: 'circle',
                itemWidth: 10,
                itemHeight: 10,
                itemGap: 20
            },
            radar: {
                indicator: categories.map(name => ({ name, max: 100 })),
                shape: 'polygon',
                splitNumber: 5,
                name: {
                    textStyle: {
                        fontSize: 12,
                        color: '#374151'
                    }
                },
                splitLine: {
                    lineStyle: {
                        color: 'rgba(229, 231, 235, 0.5)'
                    }
                },
                splitArea: {
                    areaStyle: {
                        color: ['#f9fafb', '#f3f4f6']
                    }
                },
                axisLine: {
                    lineStyle: {
                        color: '#e5e7eb'
                    }
                }
            },
            series: [
                {
                    name: '学习表现',
                    type: 'radar',
                    data: [
                        {
                            value: countData,
                            name: '参与频次',
                            type: 'radar',
                            areaStyle: {
                                color: 'rgba(64, 150, 255, 0.2)'
                            },
                            lineStyle: {
                                color: '#4096ff',
                                width: 2
                            },
                            itemStyle: {
                                color: '#4096ff',
                                borderRadius: 4
                            },
                            symbol: 'circle',
                            symbolSize: 6
                        },
                        {
                            value: qualityData,
                            name: '完成质量',
                            type: 'radar',
                            areaStyle: {
                                color: 'rgba(250, 173, 20, 0.2)'
                            },
                            lineStyle: {
                                color: '#faad14',
                                width: 2
                            },
                            itemStyle: {
                                color: '#faad14',
                                borderRadius: 4
                            },
                            symbol: 'circle',
                            symbolSize: 6
                        }
                    ]
                }
            ],
            backgroundColor: 'transparent',
            animation: {
                duration: 1000,
                easing: 'easeOutCubic'
            }
        };

        netChart.setOption(netChartOption);
        window.addEventListener('resize', () => {
            netChart.resize();
            projectCtx.canvas.parentNode.style.height = '320px';
        });

        // 页面加载动画
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
    </script>
</body>
</html>