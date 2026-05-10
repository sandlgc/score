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

// 缓存更新函数 - 移到文件开头，确保在调用前已定义
function updateRankingCache($conn, $projectKey, $projectFilter, $startTimestamp, $endTimestamp, $cacheTable) {
    try {
        logError("开始更新项目缓存: $projectKey, 周期: $startTimestamp 至 $endTimestamp");
        
        // 清空当前项目的缓存
        $deleteSql = "DELETE FROM $cacheTable WHERE project_key = '$projectKey'";
        logError("执行SQL: $deleteSql");
        if (!$conn->query($deleteSql)) {
            throw new Exception("清空缓存失败: " . $conn->error);
        }
        logError("成功清空项目 $projectKey 的缓存");
        
        // 更新每周之星缓存
        $weeklySql = "SELECT s.student_id, s.name, s.class, SUM(points_change) as total_points, 
                      ROW_NUMBER() OVER (ORDER BY SUM(points_change) DESC) as rank
                      FROM students s 
                      JOIN point_logs pl ON s.student_id = pl.student_id
                      WHERE action_time BETWEEN '$startTimestamp' AND '$endTimestamp'
                      AND action LIKE '$projectFilter'
                      GROUP BY s.student_id 
                      ORDER BY total_points DESC LIMIT 3";
        
        logError("执行每周之星查询: $weeklySql");
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
            
            logError("插入每周之星数据: $insertSql");
            if (!$conn->query($insertSql)) {
                throw new Exception("插入每周之星缓存失败: " . $conn->error);
            }
            $weeklyCount++;
        }
        logError("成功插入 $weeklyCount 条每周之星数据到缓存");
        
        // 更新进步之星缓存 - 修正版（使用当前周 vs 上一周对比）
        // 更新进步之星缓存 - 修正版（使用当前周 vs 上一周对比，且排除每周之星学生）
		$progressSql = "SELECT 
		    s.student_id, 
		    s.name, 
		    s.class,
		    -- 当前周积分
		    CURRENT.points as current_points,
		    -- 上一周积分
		    PREV.points as prev_points,
		    -- 用于排序的调整后进步值
		    CASE 
		        WHEN IFNULL(PREV.points, 0) = 0 THEN CURRENT.points
		        ELSE (CURRENT.points - PREV.points) * LOG(PREV.points + 1)
		    END as adjusted_growth,
		    -- 用于显示的进步描述
		    CASE 
		        WHEN IFNULL(PREV.points, 0) = 0 THEN '从零开始'
		        WHEN (CURRENT.points / IFNULL(PREV.points, 1) - 1) > 3 THEN '显著提升'
		        ELSE CONCAT(ROUND((CURRENT.points / IFNULL(PREV.points, 1) - 1) * 100, 1), '%') 
		    END as growth_rate_display,
		    -- 用于存储的数值（百分比值）
		    CASE 
		        WHEN IFNULL(PREV.points, 0) = 0 THEN 999.99 
		        ELSE (CURRENT.points / IFNULL(PREV.points, 1) - 1) * 100 
		    END as growth_rate,
		    ROW_NUMBER() OVER (ORDER BY adjusted_growth DESC) as rank
		FROM (
		    -- 当前周积分
		    SELECT 
		        student_id, 
		        SUM(points_change) as points
		    FROM point_logs 
		    WHERE 
		        action_time BETWEEN '$startTimestamp' AND '$endTimestamp'
		        AND action LIKE '$projectFilter'
		    GROUP BY student_id
		) as CURRENT
		LEFT JOIN (
		    -- 上一周积分
		    SELECT 
		        student_id, 
		        SUM(points_change) as points
		    FROM point_logs 
		    WHERE 
		        action_time BETWEEN DATE_SUB('$startTimestamp', INTERVAL 1 WEEK) 
		        AND DATE_SUB('$endTimestamp', INTERVAL 1 WEEK)
		        AND action LIKE '$projectFilter'
		    GROUP BY student_id
		) as PREV ON CURRENT.student_id = PREV.student_id
		JOIN students s ON CURRENT.student_id = s.student_id
		-- 关键修改：排除已经是每周之星的学生
		WHERE 
		    CURRENT.points > 1
		    AND s.student_id NOT IN (
		        -- 使用窗口函数获取每周之星的学生ID
		        SELECT student_id 
		        FROM (
		            SELECT 
		                s.student_id,
		                ROW_NUMBER() OVER (ORDER BY SUM(pl.points_change) DESC) as rank
		            FROM point_logs pl
		            JOIN students s ON pl.student_id = s.student_id
		            WHERE 
		                pl.action_time BETWEEN '$startTimestamp' AND '$endTimestamp'
		                AND pl.action LIKE '$projectFilter'
		            GROUP BY s.student_id
		        ) as weekly_ranking
		        WHERE rank <= 3
		    )
		ORDER BY adjusted_growth DESC 
		LIMIT 3";
        
        logError("执行进步之星查询: $progressSql");
        $progressResult = $conn->query($progressSql);
        if (!$progressResult) {
            throw new Exception("查询进步之星失败: " . $conn->error);
        }
        
        // 记录基准周期数据（调试用）
        $debugSql = "SELECT 
            CURRENT.student_id, 
            CURRENT.points as current_points,
            PREV.points as prev_points
        FROM (
            SELECT 
                student_id, 
                SUM(points_change) as points
            FROM point_logs 
            WHERE 
                action_time BETWEEN '$startTimestamp' AND '$endTimestamp'
                AND action LIKE '$projectFilter'
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
                AND action LIKE '$projectFilter'
            GROUP BY student_id
        ) as PREV ON CURRENT.student_id = PREV.student_id
        WHERE 
            CURRENT.points > 5
        LIMIT 10";
        
        $debugResult = $conn->query($debugSql);
        $debugData = [];
        while ($row = $debugResult->fetch_assoc()) {
            $debugData[] = $row;
        }
        logError("进步之星基准周期数据: " . json_encode($debugData));
        
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
            
            logError("插入进步之星数据: $insertSql");
            if (!$conn->query($insertSql)) {
                throw new Exception("插入进步之星缓存失败: " . $conn->error);
            }
            $progressCount++;
        }
        logError("成功插入 $progressCount 条进步之星数据到缓存");
        
        logError("项目 $projectKey 缓存更新完成");
        return true;
    } catch (Exception $e) {
        logError("项目 $projectKey 缓存更新失败: " . $e->getMessage());
        return false;
    }
}

// 检查缓存表是否存在，不存在则创建
try {
    $checkTableSql = "SHOW TABLES LIKE '$cacheTable'";
    $tableResult = $conn->query($checkTableSql);
    if ($tableResult->num_rows == 0) {
        $createTableSql = "CREATE TABLE $cacheTable (
            id INT PRIMARY KEY AUTO_INCREMENT,
            project_key VARCHAR(50) NOT NULL,
            ranking_type VARCHAR(20) NOT NULL,
            student_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            class VARCHAR(50) NOT NULL,
            points INT DEFAULT 0,
            growth_rate DECIMAL(5,2) DEFAULT 0,
            growth_rate_display VARCHAR(50) DEFAULT '',  -- 添加新字段用于存储显示值
            rank INT NOT NULL,
            cache_date DATE NOT NULL
        )";
        if (!$conn->query($createTableSql)) {
            throw new Exception("创建缓存表失败: " . $conn->error);
        }
    }

    // 检查元数据表是否存在，不存在则创建
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
        
        // 初始化元数据
        $initMetaSql = "INSERT INTO $metaTable (last_update, week_start, week_end) 
                       VALUES (CURDATE(), '$startDate', '$endDate')";
        if (!$conn->query($initMetaSql)) {
            throw new Exception("初始化元数据失败: " . $conn->error);
        }
    }
} catch (Exception $e) {
    logError($e->getMessage());
    echo "<div class='error-message' style='color:red;text-align:center;'>系统初始化错误，请联系管理员</div>";
}


// 获取缓存元数据
$meta = null;
$needUpdate = false; // 默认不需要更新

try {
    $metaSql = "SELECT * FROM $metaTable ORDER BY id DESC LIMIT 1";
    logError("执行元数据查询: $metaSql");
    
    $metaResult = $conn->query($metaSql);
    
    if ($metaResult === false) {
        throw new Exception("元数据查询执行失败: " . $conn->error);
    }
    
    if ($metaResult->num_rows > 0) {
        $meta = $metaResult->fetch_assoc();
        logError("成功获取元数据: " . json_encode($meta));
    } else {
        logError("元数据表中没有记录");
        
        // 尝试重新初始化元数据
        try {
            $initMetaSql = "INSERT INTO $metaTable (last_update, week_start, week_end) 
                           VALUES (CURDATE(), '$startDate', '$endDate')";
            
            logError("尝试重新初始化元数据: $initMetaSql");
            
            if (!$conn->query($initMetaSql)) {
                throw new Exception("重新初始化元数据失败: " . $conn->error);
            }
            
            // 重新查询元数据
            $metaResult = $conn->query($metaSql);
            if ($metaResult && $metaResult->num_rows > 0) {
                $meta = $metaResult->fetch_assoc();
                logError("重新初始化后成功获取元数据: " . json_encode($meta));
            } else {
                throw new Exception("重新初始化后仍无法获取元数据");
            }
            
        } catch (Exception $e) {
            logError("元数据重新初始化过程中出错: " . $e->getMessage());
            throw $e; // 重新抛出异常，由外层处理
        }
    }
    
    // 强制更新参数（仅用于调试）
    $forceUpdate = isset($_GET['debug']) && isset($_GET['force_update']);

    // 如果是周一，或者缓存的周结束日期早于上周日，或者强制更新，需要更新
    if ($meta && ($currentDay == 1 || strtotime($meta['week_end']) < strtotime($endDate) || $forceUpdate)) {
        $needUpdate = true;
        
        if ($forceUpdate) {
            logError("检测到强制更新参数，忽略常规检查逻辑");
        }
    }
    
} catch (Exception $e) {
    logError("获取缓存元数据失败: " . $e->getMessage());
    
    // 显示错误信息
    echo "<div class='error-message' style='color:red;text-align:center;'>系统错误，请联系管理员</div>";
    
    if (isset($_GET['debug'])) {
        echo "<div class='error-details' style='background:#f8d7da;padding:15px;margin:10px 0;'>";
        echo "<h4>元数据错误详情</h4>";
        echo "<p>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>SQL查询: " . htmlspecialchars($metaSql) . "</p>";
        if (isset($conn) && $conn instanceof mysqli) {
            echo "<p>MySQL错误: " . htmlspecialchars($conn->error) . "</p>";
        }
        echo "</div>";
    }
    
    // 在元数据获取失败的情况下，默认不更新缓存
    $needUpdate = false;
}

// 现在可以安全地使用 $needUpdate 变量
logError("是否需要更新缓存: " . ($needUpdate ? "是" : "否"));

// 如果需要更新缓存
if ($needUpdate && $meta) {
    $projects = [
        'weekly' => ['name' => '一周积分', 'filter' => '%'],
        'game' => ['name' => '对战游戏', 'filter' => '%对战游戏%'],
        'quiz' => ['name' => '章节答题', 'filter' => '%章答题结束%'],
        'answer' => ['name' => '回答问题', 'filter' => '%回答问题%']
    ];
    
    logError("开始每周缓存更新流程");
    
    // 开启事务确保数据一致性
    $conn->begin_transaction();
    
    try {
        logError("事务已开启");
        
        // 更新所有项目的缓存
        $updateSuccess = true;
        foreach ($projects as $key => $project) {
            if (!updateRankingCache($conn, $key, $project['filter'], $startTimestamp, $endTimestamp, $cacheTable)) {
                $updateSuccess = false;
                throw new Exception("更新项目 {$project['name']} 缓存失败");
            }
        }
        
        if ($updateSuccess) {
            // 更新元数据
            $updateMetaSql = "UPDATE $metaTable 
                             SET last_update = CURDATE(), 
                                 week_start = '$startDate', 
                                 week_end = '$endDate' 
                             WHERE id = {$meta['id']}";
            
            logError("更新缓存元数据: $updateMetaSql");
            if (!$conn->query($updateMetaSql)) {
                throw new Exception("更新元数据失败: " . $conn->error);
            }
            
            $conn->commit();
            logError("事务已提交，所有缓存更新完成");
            echo "<div class='update-message' style='color:green;text-align:center;'>缓存已更新</div>";
        } else {
            throw new Exception("部分缓存更新失败");
        }
    } catch (Exception $e) {
        $conn->rollback();
        logError("事务已回滚: " . $e->getMessage());
        echo "<div class='error-message' style='color:red;text-align:center;'>缓存更新失败，请稍后再试</div>";
    }
}

// 总积分排行榜（每次都查询）
$totalSql = "SELECT s.name, s.class, SUM(points_change) as total_points
             FROM students s JOIN point_logs pl 
             ON s.student_id = pl.student_id
             GROUP BY s.student_id
             ORDER BY total_points DESC";
$totalResult = $conn->query($totalSql);

// 定义各项目筛选条件
$projects = [
    'weekly' => ['name' => '一周积分', 'filter' => '%'],
    'game' => ['name' => '对战游戏', 'filter' => '%对战游戏%'],
    'quiz' => ['name' => '章节答题', 'filter' => '%章答题结束%'],
    'answer' => ['name' => '回答问题', 'filter' => '%回答问题%']
];

// 调试信息 - 仅开发环境使用
if (isset($_GET['debug'])) {
    echo "<div style='padding:10px;margin:20px 0;background:#f8f8f8;border:1px solid #ddd;'>";
    echo "<h3>调试信息</h3>";
    echo "<p>当前日期: " . $today->format('Y-m-d') . " (星期$currentDay)</p>";
    echo "<p>统计周期: $startDate 至 $endDate</p>";
    
    if ($meta) {
        echo "<p>缓存信息: 上次更新 {$meta['last_update']}, 周期 {$meta['week_start']} 至 {$meta['week_end']}</p>";
        echo "<p>是否需要更新: " . ($needUpdate ? "是" : "否") . "</p>";
    }
    
    // 检查缓存表数据
    $checkCacheSql = "SELECT project_key, ranking_type, COUNT(*) as count FROM $cacheTable GROUP BY project_key, ranking_type";
    $cacheCheckResult = $conn->query($checkCacheSql);
    
    echo "<h4>缓存数据统计</h4>";
    if ($cacheCheckResult->num_rows > 0) {
        echo "<table border='1' style='width:100%;border-collapse:collapse;'>";
        echo "<tr><th>项目</th><th>类型</th><th>记录数</th></tr>";
        while ($row = $cacheCheckResult->fetch_assoc()) {
            echo "<tr><td>{$row['project_key']}</td><td>{$row['ranking_type']}</td><td>{$row['count']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>缓存表中没有数据</p>";
    }
    
    // 检查原始数据
    echo "<h4>原始数据检查</h4>";
    foreach ($projects as $key => $project) {
        $checkDataSql = "SELECT COUNT(*) as count 
                        FROM point_logs 
                        WHERE action_time BETWEEN '$startTimestamp' AND '$endTimestamp'
                        AND action LIKE '{$project['filter']}'";
        $dataCheckResult = $conn->query($checkDataSql);
        $count = $dataCheckResult->fetch_assoc()['count'];
        echo "<p>{$project['name']} 记录数: $count</p>";
    }
    
    echo "</div>";
}
?>

<!-- HTML 部分保持不变 -->
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生积分排行榜</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        h1, h2 {
            text-align: center;
            color: #007BFF;
            margin-bottom: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .block {
            background: white;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .project {
            margin-bottom: 30px;
        }

        .project h3 {
            color: #007BFF;
            margin: 15px 0;
            border-left: 4px solid #007BFF;
            padding-left: 10px;
        }

        table {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            background-color: #f8f8f8;
            color: #333;
        }

        .rank-1 { background-color: #fff3cd; }
        .rank-2 { background-color: #e2e3e5; }
        .rank-3 { background-color: #ffe4b5; }

        .empty-data {
            text-align: center;
            color: #888;
            padding: 30px 0;
        }

        .period-info {
            text-align: center;
            color: #666;
            font-size: 0.9em;
            margin-top: 10px;
        }

        .button-group {
            text-align: center;
            margin: 30px 0;
        }

        .filter-btn {
            display: inline-block;
            padding: 8px 20px;
            margin: 0 5px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .filter-btn.active,
        .filter-btn:hover {
            background-color: #0056b3;
        }
        
        .error-message {
            padding: 10px;
            background-color: #ffebee;
            color: #b71c1c;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .update-message {
            padding: 10px;
            background-color: #e8f5e9;
            color: #2e7d32;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 120px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 0;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- 总积分排行榜 -->
        <div class="block">
            <h2>总积分排行榜</h2>
            <table>
                <tr>
                    <th>排名</th>
                    <th>姓名</th>
                    <th>班别</th>
                    <th>总积分</th>
                </tr>
                <?php if ($totalResult->num_rows > 0): ?>
                    <?php $rank = 1; while ($row = $totalResult->fetch_assoc()): ?>
                    <tr class="rank-<?= $rank <=3 ? $rank : '' ?>">
                        <td><?= $rank++ ?></td>
                        <td><?= $row['name'] ?></td>
                        <td><?= $row['class'] ?></td>
                        <td><?= $row['total_points'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="empty-data">
                            <i class="fa fa-info-circle"></i> 暂无数据记录
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            
        </div>

        <!-- 分项目排行榜 -->
        <?php foreach ($projects as $key => $project): ?>
        <div class="block project">
            <div class="period-info">统计周期：<?= $startDate ?> 至 <?= $endDate ?></div>
            <h3><?= $project['name'] ?></h3>
            
            <!-- 每周之星 -->
            <div>
                <h4>每周之星（TOP 3）</h4>
                <?php
                // 从缓存表获取数据
                $cacheSql = "SELECT name, class, points as total_points, rank 
                             FROM $cacheTable 
                             WHERE project_key = '$key' 
                             AND ranking_type = 'weekly'
                             ORDER BY rank ASC";
                $cacheResult = $conn->query($cacheSql);
                
                // 调试信息
                if (isset($_GET['debug'])) {
                    echo "<p style='color:blue;'>SQL: $cacheSql</p>";
                    echo "<p style='color:blue;'>结果数: " . $cacheResult->num_rows . "</p>";
                }
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
                            <td colspan="4" class="empty-data">
                                <i class="fa fa-info-circle"></i> 暂无数据记录
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- 进步之星 -->
            <div style="margin-top: 20px;">
                <h4>进步之星（TOP 3）</h4>
                <?php
                // 从缓存表获取数据，同时获取growth_rate_display字段
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
                            <td>
                                <?= $row['growth_rate_display'] ?>
                                <?php if ($row['growth_rate_display'] == '从零开始'): ?>
                                <span class="tooltip" title="表示该学生在上一周没有此类积分记录">
                                    <i class="fa fa-info-circle text-info"></i>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-data">
                                <i class="fa fa-info-circle"></i> 暂无数据记录
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="login.php" class="filter-btn">登录</a>
            <?php if (isset($_GET['debug'])): ?>
            <a href="?debug=1&force_update=1" class="filter-btn" style="background-color: #28a745;">强制更新缓存</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>