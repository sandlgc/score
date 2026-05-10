<?php
include 'config.php';

// 获取当前日期和星期几
$today = new DateTime();
$currentDay = $today->format('N'); // 1(周一)到7(周日)

// 计算上周日期范围
$lastSunday = clone $today;
$lastSunday->modify('last Sunday');
$lastMonday = clone $lastSunday;
$lastMonday->modify('-6 days');
$startDate = $lastMonday->format('Y-m-d');
$endDate = $lastSunday->format('Y-m-d');
$startTimestamp = $lastMonday->format('Y-m-d 00:00:00');
$endTimestamp = $lastSunday->format('Y-m-d 23:59:59');

// 缓存表名
$cacheTable = 'weekly_ranking_cache';
$metaTable = 'ranking_cache_metadata';

// 错误日志函数
function logError($message) {
    error_log("积分排行榜错误: $message", 3, __DIR__ . '/ranking_errors.log');
}

// 缓存更新函数
function updateRankingCache($conn, $projectKey, $projectFilter, $startTimestamp, $endTimestamp, $cacheTable) {
    try {
        logError("开始更新项目缓存: $projectKey, 周期: $startTimestamp 至 $endTimestamp");
        
        // 清空当前项目的缓存
        $deleteSql = "DELETE FROM $cacheTable WHERE project_key = '$projectKey'";
        if (!$conn->query($deleteSql)) {
            throw new Exception("清空缓存失败: " . $conn->error);
        }
        
        // 更新每周之星缓存（包含所有类型积分：答题、游戏、回答问题、作业奖励、提交作业、操作练习）
        $weeklySql = "SELECT s.student_id, s.name, s.class, SUM(points_change) as total_points, 
                      ROW_NUMBER() OVER (ORDER BY SUM(points_change) DESC) as rank
                      FROM students s 
                      JOIN point_logs pl ON s.student_id = pl.student_id
                      WHERE action_time BETWEEN '$startTimestamp' AND '$endTimestamp'
                      AND (action LIKE '$projectFilter' OR action LIKE '%作业批改奖励%' OR action LIKE '%提交作业%' OR action LIKE '%操作练习%')
                      GROUP BY s.student_id 
                      ORDER BY total_points DESC LIMIT 3";
        
        $weeklyResult = $conn->query($weeklySql);
        if (!$weeklyResult) {
            throw new Exception("查询每周之星失败: " . $conn->error);
        }
        
        $weeklyCount = 0;
        while ($row = $weeklyResult->fetch_assoc()) {
            $studentId = $conn->real_escape_string($row['student_id']);
            $name = $conn->real_escape_string($row['name']);
            $class = $conn->real_escape_string($row['class']);
            
            $insertSql = "INSERT INTO $cacheTable 
                          (project_key, ranking_type, student_id, name, class, points, rank, cache_date)
                          VALUES ('$projectKey', 'weekly', '$studentId', '$name', '$class', 
                                  {$row['total_points']}, {$row['rank']}, CURDATE())";
            
            if (!$conn->query($insertSql)) {
                throw new Exception("插入每周之星缓存失败: " . $conn->error);
            }
            $weeklyCount++;
        }
        
        // 更新进步之星缓存
        $progressSql = "SELECT 
		    s.student_id, 
		    s.name, 
		    s.class,
		    CURRENT.points as current_points,
		    PREV.points as prev_points,
		    CASE 
		        WHEN IFNULL(PREV.points, 0) = 0 THEN CURRENT.points
		        ELSE (CURRENT.points - PREV.points) * LOG(PREV.points + 1)
		    END as adjusted_growth,
		    CASE 
		        WHEN IFNULL(PREV.points, 0) = 0 THEN '从零开始'
		        WHEN (CURRENT.points / IFNULL(PREV.points, 1) - 1) > 3 THEN '显著提升'
		        ELSE CONCAT(ROUND((CURRENT.points / IFNULL(PREV.points, 1) - 1) * 100, 1), '%') 
		    END as growth_rate_display,
		    CASE 
		        WHEN IFNULL(PREV.points, 0) = 0 THEN 999.99 
		        ELSE (CURRENT.points / IFNULL(PREV.points, 1) - 1) * 100 
		    END as growth_rate,
		    ROW_NUMBER() OVER (ORDER BY adjusted_growth DESC) as rank
		FROM (
		    SELECT 
		        student_id, 
		        SUM(points_change) as points
		    FROM point_logs 
		    WHERE 
		        action_time BETWEEN '$startTimestamp' AND '$endTimestamp'
		        AND (action LIKE '$projectFilter' OR action LIKE '%作业批改奖励%' OR action LIKE '%提交作业%' OR action LIKE '%操作练习%')
		    GROUP BY student_id
		) as CURRENT
		LEFT JOIN (
		    SELECT 
		        student_id, 
		        SUM(points_change) as points
		    FROM point_logs 
		    WHERE 
		        action_time BETWEEN DATE_SUB('$startTimestamp', INTERVAL 1 WEEK) 
		        AND DATE_SUB('$endTimestamp', INTERVAL 1 WEEK)
		        AND (action LIKE '$projectFilter' OR action LIKE '%作业批改奖励%' OR action LIKE '%提交作业%' OR action LIKE '%操作练习%')
		    GROUP BY student_id
		) as PREV ON CURRENT.student_id = PREV.student_id
		JOIN students s ON CURRENT.student_id = s.student_id
		WHERE 
		    CURRENT.points > 1
		    AND s.student_id NOT IN (
		        SELECT student_id 
		        FROM (
		            SELECT 
		                s.student_id,
		                ROW_NUMBER() OVER (ORDER BY SUM(pl.points_change) DESC) as rank
		            FROM point_logs pl
		            JOIN students s ON pl.student_id = s.student_id
		            WHERE 
		                pl.action_time BETWEEN '$startTimestamp' AND '$endTimestamp'
		                AND (pl.action LIKE '$projectFilter' OR pl.action LIKE '%作业批改奖励%' OR pl.action LIKE '%提交作业%' OR pl.action LIKE '%操作练习%')
		            GROUP BY s.student_id
		        ) as weekly_ranking
		        WHERE rank <= 3
		    )
		ORDER BY adjusted_growth DESC 
		LIMIT 3";
        
        $progressResult = $conn->query($progressSql);
        if (!$progressResult) {
            throw new Exception("查询进步之星失败: " . $conn->error);
        }
        
        $progressCount = 0;
        while ($row = $progressResult->fetch_assoc()) {
            $studentId = $conn->real_escape_string($row['student_id']);
            $name = $conn->real_escape_string($row['name']);
            $class = $conn->real_escape_string($row['class']);
            $growthRateDisplay = $conn->real_escape_string($row['growth_rate_display']);
            
            $insertSql = "INSERT INTO $cacheTable 
                          (project_key, ranking_type, student_id, name, class, points, growth_rate, growth_rate_display, rank, cache_date)
                          VALUES ('$projectKey', 'progress', '$studentId', '$name', '$class', 
                                  0, {$row['growth_rate']}, '$growthRateDisplay', {$row['rank']}, CURDATE())";
            
            if (!$conn->query($insertSql)) {
                throw new Exception("插入进步之星缓存失败: " . $conn->error);
            }
            $progressCount++;
        }
        
        return true;
    } catch (Exception $e) {
        logError("项目 $projectKey 缓存更新失败: " . $e->getMessage());
        return false;
    }
}

// 检查缓存表是否存在
try {
    $checkTableSql = "SHOW TABLES LIKE '$cacheTable'";
    $tableResult = $conn->query($checkTableSql);
    if ($tableResult->num_rows == 0) {
        $createTableSql = "CREATE TABLE $cacheTable (
            id INT PRIMARY KEY AUTO_INCREMENT,
            project_key VARCHAR(50) NOT NULL,
            ranking_type VARCHAR(20) NOT NULL,
            student_id VARCHAR(20) NOT NULL,
            name VARCHAR(100) NOT NULL,
            class VARCHAR(50) NOT NULL,
            points DECIMAL(10,2) DEFAULT 0,
            growth_rate DECIMAL(5,2) DEFAULT 0,
            growth_rate_display VARCHAR(50) DEFAULT '',
            rank INT NOT NULL,
            cache_date DATE NOT NULL
        )";
        if (!$conn->query($createTableSql)) {
            throw new Exception("创建缓存表失败: " . $conn->error);
        }
    }

    $checkMetaTableSql = "SHOW TABLES LIKE '$metaTable'";
    $metaTableResult = $conn->query($checkMetaTableSql);
    if ($metaTableResult->num_rows == 0) {
        $createMetaTableSql = "CREATE TABLE $metaTable (
            id INT PRIMARY KEY AUTO_INCREMENT,
            last_update DATE NOT NULL,
            week_start DATE NOT NULL,
            week_end DATE NOT NULL
        )";
        if (!$conn->query($createMetaTableSql)) {
            throw new Exception("创建元数据表失败: " . $conn->error);
        }
        
        $initMetaSql = "INSERT INTO $metaTable (last_update, week_start, week_end) 
                       VALUES (CURDATE(), '$startDate', '$endDate')";
        $conn->query($initMetaSql);
    }
} catch (Exception $e) {
    logError($e->getMessage());
}

// 获取缓存元数据
$meta = null;
$needUpdate = false;

try {
    $metaSql = "SELECT * FROM $metaTable ORDER BY id DESC LIMIT 1";
    $metaResult = $conn->query($metaSql);
    
    if ($metaResult->num_rows > 0) {
        $meta = $metaResult->fetch_assoc();
    }

    $forceUpdate = isset($_GET['debug']) && isset($_GET['force_update']);
    if ($meta && ($currentDay == 1 || strtotime($meta['week_end']) < strtotime($endDate) || $forceUpdate)) {
        $needUpdate = true;
    }
    
} catch (Exception $e) {
    logError("获取缓存元数据失败: " . $e->getMessage());
}

// 更新缓存
if ($needUpdate && $meta) {
    $projects = [
        'weekly' => ['name' => '一周积分', 'filter' => '%'],
        'game' => ['name' => '对战游戏', 'filter' => '%对战游戏%'],
        'quiz' => ['name' => '章节答题', 'filter' => '%章答题结束%'],
        'answer' => ['name' => '回答问题', 'filter' => '%回答问题%'],
        'wrong_question' => ['name' => '错题修炼手册', 'filter' => '%错题重做%'],
        'homework' => ['name' => '提交作业', 'filter' => '%提交作业%'],
        'practice' => ['name' => '操作练习', 'filter' => '%操作练习%']
    ];
    
    $conn->begin_transaction();
    
    try {
        $updateSuccess = true;
        foreach ($projects as $key => $project) {
            if (!updateRankingCache($conn, $key, $project['filter'], $startTimestamp, $endTimestamp, $cacheTable)) {
                $updateSuccess = false;
                throw new Exception("更新项目 {$project['name']} 缓存失败");
            }
        }
        
        if ($updateSuccess) {
            $updateMetaSql = "UPDATE $metaTable 
                             SET last_update = CURDATE(), 
                                 week_start = '$startDate', 
                                 week_end = '$endDate' 
                             WHERE id = {$meta['id']}";
            $conn->query($updateMetaSql);
            $conn->commit();
        }
    } catch (Exception $e) {
        $conn->rollback();
    }
}

// ====================== 🎯 总积分 = 原有积分 + 宠物总等级 × 2 ======================
$totalSql = "
    SELECT 
        s.name, 
        s.class, 
        s.points + IFNULL(pet_total_level, 0) * 2 AS total_points,
        IFNULL(pet_count, 0) AS pet_count,
        IFNULL(pet_total_level, 0) AS pet_total_level,
        s.student_id
    FROM students s
    LEFT JOIN (
        SELECT 
            student_id, 
            COUNT(*) AS pet_count,
            SUM(level) AS pet_total_level
        FROM student_pets 
        GROUP BY student_id
    ) p ON s.student_id = p.student_id
    ORDER BY total_points DESC
";
$totalResult = $conn->query($totalSql);

// 获取学生宠物图片
function getStudentPetIcons($conn, $student_id) {
    $sql = "SELECT pc.pet_image FROM student_pets sp 
            LEFT JOIN pet_config pc ON sp.pet_id = pc.id 
            WHERE sp.student_id = '$student_id'";
    $res = $conn->query($sql);
    $html = '';
    while($row = $res->fetch_assoc()){
        $img = htmlspecialchars($row['pet_image']);
        $html .= "<img src='$img' style='width:36px;height:36px;border-radius:50%;margin:0 3px;box-shadow:0 2px 6px rgba(0,0,0,0.1);vertical-align:middle;object-fit:cover;'>";
    }
    return $html;
}

// 项目定义（页面显示）
$projects = [
    'weekly' => ['name' => '一周积分', 'filter' => '%'],
    'game' => ['name' => '对战游戏', 'filter' => '%对战游戏%'],
    'quiz' => ['name' => '章节答题', 'filter' => '%章答题结束%'],
    'answer' => ['name' => '回答问题', 'filter' => '%回答问题%'],
    'wrong_question' => ['name' => '错题修炼手册', 'filter' => '%错题重做%'],
    'homework' => ['name' => '提交作业', 'filter' => '%提交作业%'],
    'practice' => ['name' => '操作练习', 'filter' => '%操作练习%']
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生积分排行榜</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4eaf5 100%);
            color: #333;
            padding: 30px 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            font-size: 28px;
            color: #2b6eff;
            margin-bottom: 10px;
            font-weight: 600;
        }

        h2 {
            text-align: center;
            font-size: 20px;
            color: #555;
            margin: 10px 0 20px;
            font-weight: 500;
        }

        .block {
            background: #fff;
            padding: 25px 30px;
            margin-bottom: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .project h3 {
            font-size: 18px;
            color: #2b6eff;
            margin: 10px 0 15px;
            padding-left: 12px;
            border-left: 4px solid #2b6eff;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 12px;
            overflow: hidden;
            margin: 10px 0;
        }

        th {
            background: linear-gradient(90deg, #3b82f6, #2b6eff);
            color: #fff;
            padding: 14px 12px;
            text-align: center;
            font-weight: 600;
            font-size: 15px;
        }

        td {
            padding: 14px 12px;
            text-align: center;
            border-bottom: 1px solid #f2f2f2;
            font-size: 15px;
        }

        tr:hover {
            background: #f9fafb;
        }

        .rank-1 {
            background: linear-gradient(135deg, #fff6d0, #fff9e6);
            font-weight: 600;
            color: #b47000;
        }

        .rank-2 {
            background: linear-gradient(135deg, #f0f4ff, #f7f9ff);
            font-weight: 600;
            color: #3b64c5;
        }

        .rank-3 {
            background: linear-gradient(135deg, #fff4eb, #fff9f5);
            font-weight: 600;
            color: #b95b00;
        }

        .empty-data {
            text-align: center;
            color: #999;
            padding: 30px 0;
            font-size: 14px;
        }

        .period-info {
            text-align: center;
            color: #888;
            font-size: 13px;
            margin: 10px 0;
        }

        .button-group {
            text-align: center;
            margin: 20px 0;
        }

        .filter-btn {
            display: inline-block;
            padding: 10px 22px;
            background: #2b6eff;
            color: #fff;
            border-radius: 50px;
            text-decoration: none;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(43,110,255,0.2);
            transition: all 0.3s;
        }

        .filter-btn:hover {
            background: #1d5cff;
            transform: translateY(-2px);
        }

        .pet-empty {
            color: #bbb;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏆 学生积分排行榜</h1>

        <!-- 总积分排行榜 -->
        <div class="block">
            <table>
                <tr>
                    <th>排名</th>
                    <th>姓名</th>
                    <th>班级</th>
                    <th>我的财富</th>
                    <th>财富总等级</th>
                    <th>总积分</th>
                </tr>
                <?php if ($totalResult->num_rows > 0): ?>
                    <?php $rank = 1; while ($row = $totalResult->fetch_assoc()): ?>
                    <tr class="rank-<?= $rank <=3 ? $rank : '' ?>">
                        <td><?= $rank++ ?></td>
                        <td><?= $row['name'] ?></td>
                        <td><?= $row['class'] ?></td>
                        <td>
                            <?php if($row['pet_count'] > 0): ?>
                                <?= getStudentPetIcons($conn, $row['student_id']) ?>
                            <?php else: ?>
                                <span class="pet-empty">🐾 暂无宠物</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $row['pet_total_level'] ?> 级</td>
                        <td><strong><?= $row['total_points'] ?></strong></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty-data">暂无数据记录</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- 分项目排行榜 -->
        <?php foreach ($projects as $key => $project): ?>
        <div class="block project">
            <div class="period-info">统计周期：<?= $startDate ?> 至 <?= $endDate ?></div>
            <h3><?= $project['name'] ?></h3>
            
            <div>
                <h4>🌟 每周之星（TOP 3）</h4>
                <?php
                $cacheSql = "SELECT name, class, points as total_points, rank 
                             FROM $cacheTable 
                             WHERE project_key = '$key' 
                             AND ranking_type = 'weekly'
                             ORDER BY rank ASC";
                $cacheResult = $conn->query($cacheSql);
                ?>
                <table>
                    <tr>
                        <th>排名</th>
                        <th>姓名</th>
                        <th>班别</th>
                        <th>积分</th>
                    </tr>
                    <?php if ($cacheResult->num_rows > 0): ?>
                        <?php while ($row = $cacheResult->fetch_assoc()): ?>
                        <tr class="rank-<?= $row['rank'] ?>">
                            <td><?= $row['rank'] ?></td>
                            <td><?= $row['name'] ?></td>
                            <td><?= $row['class'] ?></td>
                            <td><?= $row['total_points'] ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-data">暂无数据记录</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div style="margin-top: 20px;">
                <h4>🚀 进步之星</h4>
                <?php
                $cacheSql = "SELECT name, class, growth_rate, growth_rate_display, rank 
                             FROM $cacheTable 
                             WHERE project_key = '$key' 
                             AND ranking_type = 'progress'
                             ORDER BY rank ASC";
                $cacheResult = $conn->query($cacheSql);
                ?>
                <table>
                    <tr>
                        <th>排名</th>
                        <th>姓名</th>
                        <th>班别</th>
                        <th>进步幅度</th>
                    </tr>
                    <?php if ($cacheResult->num_rows > 0): ?>
                        <?php while ($row = $cacheResult->fetch_assoc()): ?>
                        <tr class="rank-<?= $row['rank'] ?>">
                            <td><?= $row['rank'] ?></td>
                            <td><?= $row['name'] ?></td>
                            <td><?= $row['class'] ?></td>
                            <td><?= $row['growth_rate_display'] ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-data">暂无数据记录</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="button-group">
            <a href="login.php" class="filter-btn">返回登录</a>
        </div>
    </div>
</body>
</html>