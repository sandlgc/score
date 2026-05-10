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

// ========== 获取学生原始数据并计算总评 ==========
// 查询学生所有积分数据（汇总原始分数）
$studentSql = "
    SELECT 
        s.student_id,
        s.name,
        s.class,
        COALESCE(SUM(pl.points_change), 0) as original_score
    FROM students s
    LEFT JOIN point_logs pl ON s.student_id = pl.student_id
    GROUP BY s.student_id
    ORDER BY original_score DESC
";
$studentResult = $conn->query($studentSql);
$studentList = [];
while ($row = $studentResult->fetch_assoc()) {
    $studentList[] = [
        'student_id' => $row['student_id'],
        'name' => $row['name'],
        'class' => $row['class'],
        'original_score' => (float)$row['original_score']
    ];
}

// ========== 动态获取原始最高分（替代固定值 636.06） ==========
$totalCount = count($studentList);
// 1. 提取所有学生的原始积分，组成数组
$originalScores = array_column($studentList, 'original_score');
// 2. 动态计算实际最高分（容错：无数据时设为100，避免除法报错）
$maxOriginalScore = $totalCount > 0 ? max($originalScores) : 100;
// 可选：如果希望最高分不低于某个基础值（比如防止最高分过低导致分数分布异常）
$maxOriginalScore = max($maxOriginalScore, 100);

// ========== 核心配置（沿用之前的分数转换逻辑） ==========
// 1. 总评计算参数
$passScore = 60; // 及格线
$top90PercentPass = true; // 开启90%学生及格
$top10Percent90Plus = true; // 开启10%学生90分以上
$maxScore = 98; // 总评最高分（整数）
$minScore = 0; // 最低分（负数计0，整数）

// 2. 导出配置
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=学生总评表_".date('YmdHis').".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// ========== 计算总评分数（分步实现需求逻辑，总评转为整数） ==========
$top10Count = ceil($totalCount * 0.1); // 90分以上人数
$top90Count = ceil($totalCount * 0.9); // 及格人数

// 步骤1：排序原始分数（降序）
usort($studentList, function($a, $b) {
    return $b['original_score'] - $a['original_score'];
});

// 步骤2：计算分段锚点
$anchor90 = $top10Count > 0 ? $studentList[$top10Count - 1]['original_score'] : $maxOriginalScore * 0.8; // 90分对应原始分
$anchor60 = $top90Count > 0 ? $studentList[$top90Count - 1]['original_score'] : $maxOriginalScore * 0.2; // 60分对应原始分

// 步骤3：逐个计算总评分数（核心：四舍五入为整数）
foreach ($studentList as &$student) {
    $original = $student['original_score'];
    
    // 负数计0
    if ($original < 0) {
        $student['total_score'] = $minScore;
        $student['pass_status'] = '不及格';
        continue;
    }
    
    // 分段计算总评分数
    if ($original >= $maxOriginalScore) {
        // 最高分对应98分（整数）
        $totalScore = $maxScore;
    } elseif ($original >= $anchor90) {
        // 90-98分段（高分段）
        $totalScore = 90 + (($maxScore - 90) / ($maxOriginalScore - $anchor90)) * ($original - $anchor90);
    } elseif ($original >= $anchor60) {
        // 60-90分段（及格段）
        $totalScore = 60 + ((90 - 60) / ($anchor90 - $anchor60)) * ($original - $anchor60);
    } else {
        // 低于60分段（不及格段）
        $totalScore = (($passScore - $minScore) / $anchor60) * $original;
    }
    
    // 核心修改：四舍五入为整数，无小数位
    $student['total_score'] = round($totalScore);
    $student['pass_status'] = $student['total_score'] >= $passScore ? '及格' : '不及格';
}
unset($student); // 释放引用

// ========== 导出判断（Excel格式） ==========
$isExport = isset($_GET['export']) && $_GET['export'] == 'excel';
?>

<?php if (!$isExport): ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生总评管理 - 学习积分管理系统</title>
    <link rel="stylesheet" href="fontawesome-free-6.4.0-web/css/all.min.css">
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

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #41a813;
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

        .stats-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
            padding: 16px;
            background-color: var(--primary-light);
            border-radius: var(--radius-lg);
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-label {
            color: var(--gray-600);
            margin-right: 4px;
        }

        .stat-value {
            font-weight: 600;
            color: var(--primary);
        }

        .rank-1 { background-color: #fff3cd; }
        .rank-2 { background-color: #e2e3e5; }
        .rank-3 { background-color: #ffe4b5; }
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
            <a href="admin_board.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i> 返回仪表盘
            </a>
            <a href="?export=excel" class="btn btn-success">
                <i class="fas fa-file-excel"></i> 导出WPS表格
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
                <h1 class="page-title">学生总评管理</h1>
                <p class="page-desc">总评规则：100分制（最高98分，整数）| 负数计0分 | 90%学生及格 | 10%学生90分以上</p>
            </div>
        </div>

        <!-- 总评统计概览 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-chart-bar"></i> 总评数据概览
            </div>
            <?php
            // 统计数据计算（平均分数也转为整数，保持格式统一）
            $totalPass = count(array_filter($studentList, function($item) { return $item['pass_status'] == '及格'; }));
            $total90Plus = count(array_filter($studentList, function($item) { return $item['total_score'] >= 90; }));
            $avgScore = $totalCount > 0 ? round(array_sum(array_column($studentList, 'total_score')) / $totalCount) : 0;
            $highestScore = $studentList[0]['total_score'] ?? 0;
            $highestStudent = $studentList[0]['name'] ?? '无';
            ?>
            <div class="stats-summary">
                <div class="stat-item">
                    <span class="stat-label">总学生数：</span>
                    <span class="stat-value"><?= $totalCount ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">及格人数：</span>
                    <span class="stat-value"><?= $totalPass ?>（<?= round($totalPass/$totalCount*100, 2) ?>%）</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">90分以上人数：</span>
                    <span class="stat-value"><?= $total90Plus ?>（<?= round($total90Plus/$totalCount*100, 2) ?>%）</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">平均总评分数：</span>
                    <span class="stat-value"><?= $avgScore ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">最高分：</span>
                    <span class="stat-value"><?= $highestScore ?>（<?= $highestStudent ?>）</span>
                </div>
            </div>
        </div>

        <!-- 学生总评列表 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-list-ol"></i> 学生总评详细列表
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>排名</th>
                            <th>学生ID</th>
                            <th>姓名</th>
                            <th>班级</th>
                            <th>原始积分</th>
                            <th>总评分数（100分制）</th>
                            <th>及格状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($studentList)): ?>
                            <?php foreach ($studentList as $index => $student): ?>
                                <tr class="rank-<?= $index < 3 ? $index+1 : '' ?>">
                                    <td><?= $index + 1 ?></td>
                                    <td><?= $student['student_id'] ?></td>
                                    <td><?= $student['name'] ?></td>
                                    <td><?= $student['class'] ?></td>
                                    <td><?= round($student['original_score'], 2) ?></td>
                                    <td><?= $student['total_score'] ?></td>
                                    <td>
                                        <?php if ($student['pass_status'] == '及格'): ?>
                                            <span class="badge badge-success">及格</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">不及格</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--gray-600);">暂无学生数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
<?php else: ?>
<!-- Excel导出内容（WPS兼容格式，总评分为整数） -->
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="utf-8">
    <title>学生总评表</title>
</head>
<body>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr style="background-color: #e6f7ff; font-weight: bold;">
                <th>排名</th>
                <th>学生ID</th>
                <th>姓名</th>
                <th>班级</th>
                <th>原始积分</th>
                <th>总评分数（100分制）</th>
                <th>及格状态</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($studentList)): ?>
                <?php foreach ($studentList as $index => $student): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= $student['student_id'] ?></td>
                        <td><?= $student['name'] ?></td>
                        <td><?= $student['class'] ?></td>
                        <td><?= round($student['original_score'], 2) ?></td>
                        <td><?= $student['total_score'] ?></td>
                        <td><?= $student['pass_status'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" align="center">暂无学生数据</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php endif; ?>