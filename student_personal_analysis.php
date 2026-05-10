<?php
session_start();
include 'config.php';

// 学生身份验证
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "student") {
    header("Location: login.php");
    exit();
}
$selectedStudentId = $_SESSION["user_id"];

// 每周生成限制
$checkLogSql = "SELECT report_date, report_content FROM student_report_logs WHERE student_id = ?";
$stmt = $conn->prepare($checkLogSql);
$stmt->bind_param("s", $selectedStudentId);
$stmt->execute();
$checkLogResult = $stmt->get_result();
$lastLog = $checkLogResult->fetch_assoc();

$oneWeek = 60 * 60 * 24 * 7;
$canGenerateNew = true;
$lastGenerateTime = '';
$nextGenerateTime = '';
$cacheContent = '';

if ($lastLog) {
    $lastReportTime = strtotime($lastLog['report_date']);
    $currentTime = time();
    $timeDiff = $currentTime - $lastReportTime;
    
    if ($timeDiff < $oneWeek) {
        $canGenerateNew = false;
        $lastGenerateTime = date('Y-m-d H:i:s', $lastReportTime);
        $nextGenerateTime = date('Y-m-d H:i:s', $lastReportTime + $oneWeek);
        $cacheContent = $lastLog['report_content'];
    }
}

// 满足条件才生成新报告
if ($canGenerateNew) {
    // 学习项目映射
    $projectsMap = [
        'game' => ['name' => '对战游戏', 'desc' => '通过游戏化答题巩固知识点', 'suggestion' => '保持高频次参与，注重错题复盘'],
        'quiz' => ['name' => '章节答题', 'desc' => '章节知识点掌握程度检测', 'suggestion' => '针对错误章节进行专项复习'],
        'answer' => ['name' => '回答问题', 'desc' => '课堂/课后主动答题表现', 'suggestion' => '主动拓展同类问题解题思路'],
        'wrong_question' => ['name' => '错题修炼', 'desc' => '错题重做巩固薄弱点', 'suggestion' => '建立错题本，定期回顾'],
        'practice' => ['name' => '操作练习', 'desc' => '实操练习提升动手能力', 'suggestion' => '多动手、多复盘'],
        'homework' => ['name' => '提交作业', 'desc' => '按时完成并提交作业', 'suggestion' => '按时提交，认真完成'],
    ];

    // 基础信息
    $studentSql = "SELECT student_id, name, class FROM students WHERE student_id = ?";
    $stmt = $conn->prepare($studentSql);
    $stmt->bind_param("s", $selectedStudentId);
    $stmt->execute();
    $studentResult = $stmt->get_result();
    $currentStudent = $studentResult->fetch_assoc();
    if (!$currentStudent) die("错误：学生信息不存在！");
    $studentName = $currentStudent['name'];
    $currentClass = $currentStudent['class'];

    // 核心表现数据
    $performanceSql = "
        SELECT 
            'total' as type, 
            SUM(points_change) as points,
            COUNT(id) as record_count,
            0 as project_key
        FROM point_logs 
        WHERE student_id = ?
        UNION ALL
        SELECT 
            'game' as type,
            SUM(CASE WHEN action LIKE '%对战游戏%' THEN points_change ELSE 0 END) as points,
            COUNT(CASE WHEN action LIKE '%对战游戏%' THEN 1 ELSE NULL END) as record_count,
            1 as project_key
        FROM point_logs 
        WHERE student_id = ?
        UNION ALL
        SELECT 
            'quiz' as type,
            SUM(CASE WHEN action LIKE '%章答题结束%' THEN points_change ELSE 0 END) as points,
            COUNT(CASE WHEN action LIKE '%章答题结束%' THEN 1 ELSE NULL END) as record_count,
            2 as project_key
        FROM point_logs 
        WHERE student_id = ?
        UNION ALL
        SELECT 
            'answer' as type,
            SUM(CASE WHEN action LIKE '%回答问题%' THEN points_change ELSE 0 END) as points,
            COUNT(CASE WHEN action LIKE '%回答问题%' THEN 1 ELSE NULL END) as record_count,
            3 as project_key
        FROM point_logs 
        WHERE student_id = ?
        UNION ALL
        SELECT 
            'wrong_question' as type,
            SUM(CASE WHEN action LIKE '%错题重做%' THEN points_change ELSE 0 END) as points,
            COUNT(CASE WHEN action LIKE '%错题重做%' THEN 1 ELSE NULL END) as record_count,
            4 as project_key
        FROM point_logs 
        WHERE student_id = ?
        UNION ALL
        SELECT 
            'practice' as type,
            SUM(CASE WHEN action LIKE '%操作练习%' THEN points_change ELSE 0 END) as points,
            COUNT(CASE WHEN action LIKE '%操作练习%' THEN 1 ELSE NULL END) as record_count,
            5 as project_key
        FROM point_logs 
        WHERE student_id = ?
        UNION ALL
        SELECT 
            'homework' as type,
            0 as points,
            COUNT(id) as record_count,
            6 as project_key
        FROM homework_submit 
        WHERE student_id = ?
    ";
    $stmt = $conn->prepare($performanceSql);
    $stmt->bind_param("sssssss", 
        $selectedStudentId, $selectedStudentId, $selectedStudentId, 
        $selectedStudentId, $selectedStudentId, $selectedStudentId, $selectedStudentId
    );
    $stmt->execute();
    $performanceResult = $stmt->get_result();
    
    $performanceData = [
        'total' => ['points' => 0, 'record_count' => 0, 'avg_points' => 0],
        'game' => ['points' => 0, 'record_count' => 0, 'avg_points' => 0],
        'quiz' => ['points' => 0, 'record_count' => 0, 'avg_points' => 0],
        'answer' => ['points' => 0, 'record_count' => 0, 'avg_points' => 0],
        'wrong_question' => ['points' => 0, 'record_count' => 0, 'avg_points' => 0],
        'practice' => ['points' => 0, 'record_count' => 0, 'avg_points' => 0],
        'homework' => ['points' => 0, 'record_count' => 0, 'avg_points' => 0],
    ];
    $totalPoints = 0;
    $totalRecordCount = 0;
    
    while ($row = $performanceResult->fetch_assoc()) {
        $type = $row['type'];
        $points = $row['points'];
        $recordCount = $row['record_count'];
        $performanceData[$type] = [
            'points' => $points,
            'record_count' => $recordCount,
            'avg_points' => $recordCount > 0 ? round($points / $recordCount, 2) : 0
        ];
        if ($type == 'total') {
            $totalPoints = $points;
            $totalRecordCount = $recordCount;
        }
    }

    // 班级排名
    $rankSql = "
        SELECT 
            COUNT(*) + 1 as rank,
            (SELECT COUNT(*) FROM students WHERE class = ?) as class_total,
            (SELECT AVG(student_total) FROM (SELECT SUM(points_change) as student_total FROM point_logs GROUP BY student_id) as t) as class_avg_points
        FROM students s
        JOIN (SELECT student_id, SUM(points_change) as total FROM point_logs GROUP BY student_id) pl 
        ON s.student_id = pl.student_id
        WHERE s.class = ? AND pl.total > (SELECT SUM(points_change) FROM point_logs WHERE student_id = ?)
    ";
    $stmt = $conn->prepare($rankSql);
    $stmt->bind_param("sss", $currentClass, $currentClass, $selectedStudentId);
    $stmt->execute();
    $rankResult = $stmt->get_result();
    $rankData = $rankResult->fetch_assoc() ?: [];
    
    $classRank = $rankData['rank'] ?? '未上榜';
    $classTotal = $rankData['class_total'] ?? 0;
    $classAvgPoints = $rankData['class_avg_points'] ?? 0;
    $classAvgPoints = is_nan($classAvgPoints) ? 0 : $classAvgPoints;
    $rankPercentile = $classTotal > 0 ? round(($classTotal - $classRank + 1) / $classTotal * 100, 1) : 0;

    // 时间分布
    $timeBehaviorSql = "
        SELECT 
            CASE 
                WHEN HOUR(created_at) BETWEEN 6 AND 8 THEN '早间（6-8时）'
                WHEN HOUR(created_at) BETWEEN 9 AND 11 THEN '上午（9-11时）'
                WHEN HOUR(created_at) BETWEEN 12 AND 14 THEN '午间（12-14时）'
                WHEN HOUR(created_at) BETWEEN 15 AND 17 THEN '下午（15-17时）'
                WHEN HOUR(created_at) BETWEEN 18 AND 20 THEN '晚间（18-20时）'
                ELSE '深夜（21-5时）'
            END as time_period,
            COUNT(id) as behavior_count,
            SUM(points_change) as total_points,
            AVG(points_change) as avg_points
        FROM point_logs
        WHERE student_id = ?
        GROUP BY time_period
        UNION ALL
        SELECT 
            CASE WHEN WEEKDAY(created_at) < 5 THEN '工作日' ELSE '周末' END as time_period,
            COUNT(id) as behavior_count,
            SUM(points_change) as total_points,
            AVG(points_change) as avg_points
        FROM point_logs
        WHERE student_id = ?
        GROUP BY CASE WHEN WEEKDAY(created_at) < 5 THEN '工作日' ELSE '周末' END
    ";
    $stmt = $conn->prepare($timeBehaviorSql);
    $stmt->bind_param("ss", $selectedStudentId, $selectedStudentId);
    $stmt->execute();
    $timeBehaviorResult = $stmt->get_result();
    
    $timePeriodData = []; 
    $weekdayWeekendData = [
        ['time_period' => '工作日', 'behavior_count' => 0, 'total_points' => 0, 'avg_points' => 0],
        ['time_period' => '周末', 'behavior_count' => 0, 'total_points' => 0, 'avg_points' => 0]
    ];
    
    while ($row = $timeBehaviorResult->fetch_assoc()) {
        if ($row['time_period'] == '工作日') {
            $weekdayWeekendData[0] = $row;
        } elseif ($row['time_period'] == '周末') {
            $weekdayWeekendData[1] = $row;
        } else {
            $timePeriodData[] = $row;
        }
    }

    // 学习连续性
    $studentDatesSql = "
        SELECT DISTINCT DATE(created_at) as study_date 
        FROM point_logs 
        WHERE student_id = ?
        ORDER BY study_date ASC
    ";
    $stmt = $conn->prepare($studentDatesSql);
    $stmt->bind_param("s", $selectedStudentId);
    $stmt->execute();
    $studentDatesResult = $stmt->get_result();
    
    $studyDates = [];
    while ($row = $studentDatesResult->fetch_assoc()) {
        $studyDates[] = $row['study_date'];
    }

    $totalActiveDays = count($studyDates);
    $hasContinuousStudy = 0;
    $dayGaps = [];
    $avgDayGap = 0;
    $minDayGap = 0;
    $maxDayGap = 0;

    for ($i = 1; $i < $totalActiveDays; $i++) {
        $prevDate = strtotime($studyDates[$i-1]);
        $currDate = strtotime($studyDates[$i]);
        $gap = ($currDate - $prevDate) / (24 * 3600);
        $dayGaps[] = $gap;
        if ($gap == 1) {
            $hasContinuousStudy = 1;
        }
    }

    if (!empty($dayGaps)) {
        $avgDayGap = array_sum($dayGaps) / count($dayGaps);
        $minDayGap = min($dayGaps);
        $maxDayGap = max($dayGaps);
    }

    // 问题行为
    $problemBehaviorSql = "
        SELECT 
            COUNT(id) as problem_count,
            SUM(points_change) as problem_points_loss,
            SUM(CASE WHEN action LIKE '%睡觉%' THEN 1 ELSE 0 END) as sleep_count,
            SUM(CASE WHEN action LIKE '%不交%' THEN 1 ELSE 0 END) as no_submit_count,
            SUM(CASE WHEN points_change < 0 AND action NOT LIKE '%睡觉%' AND action NOT LIKE '%不交%' THEN 1 ELSE 0 END) as other_penalty_count,
            MAX(created_at) as last_problem_time
        FROM point_logs
        WHERE student_id = ?
        AND (points_change < 0 OR action LIKE '%睡觉%' OR action LIKE '%不交%')
    ";
    $stmt = $conn->prepare($problemBehaviorSql);
    $stmt->bind_param("s", $selectedStudentId);
    $stmt->execute();
    $problemBehaviorResult = $stmt->get_result();
    $problemBehaviorData = $problemBehaviorResult->fetch_assoc() ?: [];
    
    $problemCount = $problemBehaviorData['problem_count'] ?? 0;
    $problemPointsLoss = $problemBehaviorData['problem_points_loss'] ?? 0;
    $sleepCount = $problemBehaviorData['sleep_count'] ?? 0;
    $noSubmitCount = $problemBehaviorData['no_submit_count'] ?? 0;
    $otherPenaltyCount = $problemBehaviorData['other_penalty_count'] ?? 0;
    $lastProblemTime = $problemBehaviorData['last_problem_time'] ?? '无';

    // 行为效率
    $actionLogSql = "
        SELECT 
            CASE 
                WHEN action LIKE '%对战游戏%' THEN 'game'
                WHEN action LIKE '%章答题结束%' THEN 'quiz'
                WHEN action LIKE '%回答问题%' THEN 'answer'
                WHEN action LIKE '%错题重做%' THEN 'wrong_question'
                WHEN action LIKE '%操作练习%' THEN 'practice'
                ELSE 'other'
            END as project_type,
            created_at,
            points_change,
            id
        FROM point_logs
        WHERE student_id = ?
        ORDER BY project_type, created_at ASC
    ";
    $stmt = $conn->prepare($actionLogSql);
    $stmt->bind_param("s", $selectedStudentId);
    $stmt->execute();
    $actionLogResult = $stmt->get_result();
    
    $actionLogs = [];
    while ($row = $actionLogResult->fetch_assoc()) {
        if ($row['project_type'] != 'other') {
            $actionLogs[] = $row;
        }
    }

    $efficiencyData = [
        'game' => ['project_type' => 'game', 'action_count' => 0, 'total_points' => 0, 'avg_points_per_action' => 0, 'avg_seconds_per_action' => 0],
        'quiz' => ['project_type' => 'quiz', 'action_count' => 0, 'total_points' => 0, 'avg_points_per_action' => 0, 'avg_seconds_per_action' => 0],
        'answer' => ['project_type' => 'answer', 'action_count' => 0, 'total_points' => 0, 'avg_points_per_action' => 0, 'avg_seconds_per_action' => 0],
        'wrong_question' => ['project_type' => 'wrong_question', 'action_count' => 0, 'total_points' => 0, 'avg_points_per_action' => 0, 'avg_seconds_per_action' => 0],
        'practice' => ['project_type' => 'practice', 'action_count' => 0, 'total_points' => 0, 'avg_points_per_action' => 0, 'avg_seconds_per_action' => 0],
    ];

    $projectActions = [];
    foreach ($actionLogs as $log) {
        $project = $log['project_type'];
        if (!isset($projectActions[$project])) {
            $projectActions[$project] = [];
        }
        $projectActions[$project][] = $log;
    }

    foreach ($projectActions as $project => $actions) {
        if (!isset($efficiencyData[$project])) continue;
        
        $actionCount = count($actions);
        $totalPoints = 0;
        $totalSeconds = 0;
        
        foreach ($actions as $action) {
            $totalPoints += $action['points_change'];
        }
        
        for ($i = 1; $i < $actionCount; $i++) {
            $prevTime = strtotime($actions[$i-1]['created_at']);
            $currTime = strtotime($actions[$i]['created_at']);
            $totalSeconds += ($currTime - $prevTime);
        }
        
        $avgSecondsPerAction = $actionCount > 1 ? round($totalSeconds / ($actionCount - 1), 0) : 0;
        $avgPointsPerAction = $actionCount > 0 ? round($totalPoints / $actionCount, 2) : 0;
        
        $efficiencyData[$project] = [
            'project_type' => $project,
            'action_count' => $actionCount,
            'total_points' => $totalPoints,
            'avg_points_per_action' => $avgPointsPerAction,
            'avg_seconds_per_action' => $avgSecondsPerAction
        ];
    }

    // 班级各项目平均分（对比核心）
    $classAvgSql = "
        SELECT 
            (SELECT COALESCE(AVG(sgt), 0) FROM (SELECT s.student_id, COALESCE(SUM(CASE WHEN pl.action LIKE '%对战游戏%' THEN pl.points_change ELSE 0 END),0) sgt FROM students s LEFT JOIN point_logs pl ON s.student_id=pl.student_id WHERE s.class=? GROUP BY s.student_id) t) as game_avg,
            (SELECT COALESCE(AVG(sqt), 0) FROM (SELECT s.student_id, COALESCE(SUM(CASE WHEN pl.action LIKE '%章答题结束%' THEN pl.points_change ELSE 0 END),0) sqt FROM students s LEFT JOIN point_logs pl ON s.student_id=pl.student_id WHERE s.class=? GROUP BY s.student_id) t) as quiz_avg,
            (SELECT COALESCE(AVG(sat), 0) FROM (SELECT s.student_id, COALESCE(SUM(CASE WHEN pl.action LIKE '%回答问题%' THEN pl.points_change ELSE 0 END),0) sat FROM students s LEFT JOIN point_logs pl ON s.student_id=pl.student_id WHERE s.class=? GROUP BY s.student_id) t) as answer_avg,
            (SELECT COALESCE(AVG(swt), 0) FROM (SELECT s.student_id, COALESCE(SUM(CASE WHEN pl.action LIKE '%错题重做%' THEN pl.points_change ELSE 0 END),0) swt FROM students s LEFT JOIN point_logs pl ON s.student_id=pl.student_id WHERE s.class=? GROUP BY s.student_id) t) as wrong_question_avg,
            (SELECT COALESCE(AVG(spt), 0) FROM (SELECT s.student_id, COALESCE(SUM(CASE WHEN pl.action LIKE '%操作练习%' THEN pl.points_change ELSE 0 END),0) spt FROM students s LEFT JOIN point_logs pl ON s.student_id=pl.student_id WHERE s.class=? GROUP BY s.student_id) t) as practice_avg,

            (SELECT COALESCE(AVG(cgt),0) FROM (SELECT COUNT(CASE WHEN action LIKE '%对战游戏%' THEN 1 ELSE NULL END) cgt FROM point_logs GROUP BY student_id) t) as game_count_avg,
            (SELECT COALESCE(AVG(cqt),0) FROM (SELECT COUNT(CASE WHEN action LIKE '%章答题结束%' THEN 1 ELSE NULL END) cqt FROM point_logs GROUP BY student_id) t) as quiz_count_avg,
            (SELECT COALESCE(AVG(cat),0) FROM (SELECT COUNT(CASE WHEN action LIKE '%回答问题%' THEN 1 ELSE NULL END) cat FROM point_logs GROUP BY student_id) t) as answer_count_avg,
            (SELECT COALESCE(AVG(cwt),0) FROM (SELECT COUNT(CASE WHEN action LIKE '%错题重做%' THEN 1 ELSE NULL END) cwt FROM point_logs GROUP BY student_id) t) as wrong_count_avg,
            (SELECT COALESCE(AVG(cpt),0) FROM (SELECT COUNT(CASE WHEN action LIKE '%操作练习%' THEN 1 ELSE NULL END) cpt FROM point_logs GROUP BY student_id) t) as practice_count_avg
    ";
    $stmt = $conn->prepare($classAvgSql);
    $stmt->bind_param("sssss", $currentClass, $currentClass, $currentClass, $currentClass, $currentClass);
    $stmt->execute();
    $classAverages = $stmt->get_result()->fetch_assoc() ?: [];
    
    $classAverages = array_map(function($v){ return is_nan($v) ? 0 : round($v,1); }, $classAverages);

    // 优势/待改进项目
    $strengths = [];
    $improvements = [];
    $projectScores = [
        'game' => $performanceData['game']['points'],
        'quiz' => $performanceData['quiz']['points'],
        'answer' => $performanceData['answer']['points'],
        'wrong_question' => $performanceData['wrong_question']['points'],
        'practice' => $performanceData['practice']['points'],
    ];
    
    foreach ($projectScores as $key => $score) {
        $classAvg = $classAverages[$key . '_avg'] ?? 0;
        if ($score >= $classAvg * 1.5 && $score > 0) {
            $strengths[] = [
                'name' => $projectsMap[$key]['name'],
                'score' => $score,
                'class_avg' => $classAvg,
                'record_count' => $performanceData[$key]['record_count']
            ];
        } elseif ($score < $classAvg || $score == 0) {
            $improvements[] = [
                'name' => $projectsMap[$key]['name'],
                'score' => $score,
                'class_avg' => $classAvg,
                'record_count' => $performanceData[$key]['record_count'],
                'suggestion' => $projectsMap[$key]['suggestion']
            ];
        }
    }

    // 月度趋势
    $trendSql = "
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(points_change) as monthly_points 
        FROM point_logs WHERE student_id = ? GROUP BY month ORDER BY month
    ";
    $stmt = $conn->prepare($trendSql);
    $stmt->bind_param("s", $selectedStudentId);
    $stmt->execute();
    $trendResult = $stmt->get_result();
    
    $trendData = [];
    while ($row = $trendResult->fetch_assoc()) $trendData[] = $row;
    
    $progressRate = 0;
    $progressDesc = "暂无数据";
    if (count($trendData) >= 2) {
        $first = $trendData[0]['monthly_points'];
        $last = end($trendData)['monthly_points'];
        $progressRate = $first != 0 ? (($last - $first) / abs($first)) * 100 : 0;
        $progressDesc = $progressRate >= 50 ? "显著进步" : ($progressRate >= 10 ? "有所进步" : ($progressRate >= -10 ? "基本稳定" : "需要改进"));
    }

    // 雷达图数据（含班级平均）
    $netChartSql = "
        SELECT 
            SUM(CASE WHEN action LIKE '%对战游戏%' THEN 1 ELSE 0 END) as game_count,
            SUM(CASE WHEN action LIKE '%章答题结束%' THEN 1 ELSE 0 END) as quiz_count,
            SUM(CASE WHEN action LIKE '%回答问题%' THEN 1 ELSE 0 END) as answer_count,
            SUM(CASE WHEN action LIKE '%错题重做%' THEN 1 ELSE 0 END) as wrong_question_count,
            SUM(CASE WHEN action LIKE '%操作练习%' THEN 1 ELSE 0 END) as practice_count,
            AVG(CASE WHEN action LIKE '%对战游戏%' THEN points_change ELSE NULL END) as game_quality,
            AVG(CASE WHEN action LIKE '%章答题结束%' THEN points_change ELSE NULL END) as quiz_quality,
            AVG(CASE WHEN action LIKE '%回答问题%' THEN points_change ELSE NULL END) as answer_quality,
            AVG(CASE WHEN action LIKE '%错题重做%' THEN points_change ELSE NULL END) as wrong_question_quality,
            AVG(CASE WHEN action LIKE '%操作练习%' THEN points_change ELSE NULL END) as practice_quality
        FROM point_logs WHERE student_id = ?
    ";
    $stmt = $conn->prepare($netChartSql);
    $stmt->bind_param("s", $selectedStudentId);
    $stmt->execute();
    $netChartRaw = $stmt->get_result()->fetch_assoc() ?: [];
    $netChartRaw = array_map(function($v){ return is_nan($v) ? 0 : $v; }, $netChartRaw);

    $maxCount = max([$netChartRaw['game_count'],$netChartRaw['quiz_count'],$netChartRaw['answer_count'],$netChartRaw['wrong_question_count'],$netChartRaw['practice_count'],1]);
    $maxQuality = max([$netChartRaw['game_quality'],$netChartRaw['quiz_quality'],$netChartRaw['answer_quality'],$netChartRaw['wrong_question_quality'],$netChartRaw['practice_quality'],1]);
    
    $myRadar = [
        round($netChartRaw['game_count']/$maxCount*100),
        round($netChartRaw['quiz_count']/$maxCount*100),
        round($netChartRaw['answer_count']/$maxCount*100),
        round($netChartRaw['wrong_question_count']/$maxCount*100),
        round($netChartRaw['practice_count']/$maxCount*100),
    ];
    $classRadar = [
        round(($classAverages['game_count_avg']??0)/$maxCount*100),
        round(($classAverages['quiz_count_avg']??0)/$maxCount*100),
        round(($classAverages['answer_count_avg']??0)/$maxCount*100),
        round(($classAverages['wrong_count_avg']??0)/$maxCount*100),
        round(($classAverages['practice_count_avg']??0)/$maxCount*100),
    ];

    // 行为建议
    $behaviorSuggestions = [];
    $homeworkCount = $performanceData['homework']['record_count'];
    if($homeworkCount < 3) $behaviorSuggestions[] = "本周作业提交{$homeworkCount}次，偏少，建议按时完成。";
    $practiceCount = $performanceData['practice']['record_count'];
    if($practiceCount < 5) $behaviorSuggestions[] = "操作练习{$practiceCount}次，需加强动手练习。";

    $suggestions = [];
    if ($rankPercentile < 30) $suggestions[] = "排名靠后，优先改进薄弱项";
    elseif ($rankPercentile < 70) $suggestions[] = "中游水平，可冲刺班级前30%";
    else $suggestions[] = "优秀！保持优势，挑战更高难度";
    $suggestions = array_merge($suggestions, $behaviorSuggestions);
    if (count($trendData)>=2) $suggestions[] = "学习趋势：{$progressDesc}（".round($progressRate,1)."%）";

    ob_start(); 
} else {
    if ($cacheContent) { echo $cacheContent; exit(); } else { ob_start(); }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学习分析报告（含班级对比）</title>
    <link rel="stylesheet" href="fontawesome-free-6.4.0-web/css/all.min.css">
    <script src="js/echarts.min.js"></script>
    <style>
        :root {
            --primary: #4096ff; --success: #52c41a; --warning: #faad14; --danger: #ff4d4f;
            --gray-50: #f9fafb; --gray-100: #f3f4f6; --radius-lg: 12px; --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        * {margin:0;padding:0;box-sizing:border-box;font-family: 'Microsoft Yahei',sans-serif;}
        body {background:var(--gray-50);color:#333;line-height:1.6}
        .navbar {background:white;box-shadow:var(--shadow-md);padding:16px 24px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100}
        .logo {font-size:20px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:8px}
        .btn-back {padding:8px 16px;border-radius:4px;text-decoration:none;color:#666}
        .btn-back:hover {background:#e8f3ff;color:var(--primary)}
        .container {max-width:1200px;margin:24px auto;padding:0 24px}
        .card {background:white;border-radius:var(--radius-lg);box-shadow:var(--shadow-md);padding:24px;margin-bottom:24px}
        .card-title {font-size:18px;font-weight:600;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #eee}
        .student-info {display:flex;gap:20px;flex-wrap:wrap;margin:16px 0;color:#666}
        .stats-grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin:16px 0}
        .stat-item {background:#f7f9fc;padding:16px;border-radius:8px;text-align:center}
        .stat-item .label {font-size:14px;color:#666}
        .stat-item .value {font-size:22px;font-weight:700;color:var(--primary);margin-top:4px}
        .chart-box {width:100%;height:350px;margin:16px 0}
        .analysis-grid {display:grid;grid-template-columns:1fr 1fr;gap:24px}
        .info-box {background:#f7f9fc;padding:16px;border-radius:8px;border-left:4px solid var(--primary);margin-bottom:12px}
        .info-box.warning {border-left-color:var(--warning)}
        .suggestion-list {list-style:none;margin-top:16px}
        .suggestion-list li {padding:12px 16px;background:#f7f9fc;margin-bottom:8px;border-radius:4px}
        .info-grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin:16px 0}
    </style>
</head>
<body>
<div class="navbar">
    <div class="logo"><i class="fas fa-graduation-cap"></i> 学习分析报告</div>
    <a href="student_menu.php" class="btn-back"><i class="fas fa-arrow-left"></i> 返回</a>
</div>

<div class="container">
    <div class="card">
        <h1 style="margin-bottom:16px">我的学习情况分析报告</h1>
        <div class="student-info">
            <div><i class="fas fa-user"></i> 姓名：<?= $studentName ?></div>
            <div><i class="fas fa-school"></i> 班级：<?= $currentClass ?></div>
            <div><i class="fas fa-chart-line"></i> 班级排名：<?= $classRank ?>/<?= $classTotal ?></div>
            <div><i class="fas fa-star"></i> 超越：<?= $rankPercentile ?>% 同学</div>
            <div><i class="fas fa-crown"></i> 班级平均分：<?= round($classAvgPoints) ?> 分</div>
            <div><i class="fas fa-trophy"></i> 我的总分：<?= $totalPoints ?> 分</div>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-chart-pie"></i> 学习表现概览</div>
        <div class="stats-grid">
            <?php foreach(['game'=>'对战游戏','quiz'=>'章节答题','answer'=>'回答问题','wrong_question'=>'错题修炼','practice'=>'操作练习','homework'=>'提交作业'] as $k=>$v): ?>
            <div class="stat-item">
                <div class="label"><?= $v ?></div>
                <div class="value"><?= $performanceData[$k]['record_count'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 柱状图：我 vs 班级平均 -->
    <div class="card">
        <div class="card-title"><i class="fas fa-bar-chart"></i> 参与次数对比（我 vs 班级平均）</div>
        <div id="barChart" class="chart-box"></div>
    </div>

    <!-- 雷达图：我 vs 班级平均 -->
    <div class="card">
        <div class="card-title"><i class="fas fa-bullseye"></i> 能力雷达对比（我 vs 班级）</div>
        <div id="radarChart" class="chart-box"></div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-thermometer-half"></i> 学习时间分布</div>
        <div id="heatChart" class="chart-box"></div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-pie-chart"></i> 学习行为占比</div>
        <div id="pieChart" class="chart-box"></div>
    </div>

    <!-- 优势 & 待改进（完整对比） -->
    <div class="card">
        <div class="card-title"><i class="fas fa-medal"></i> 优势 & 待改进项目（班级对比）</div>
        <div class="analysis-grid">
            <div>
                <h3 style="color:var(--success);margin-bottom:12px">✅ 优势项目</h3>
                <?php if(empty($strengths)): ?>
                    <p>暂无优势项目，加油！</p>
                <?php else: ?>
                    <?php foreach($strengths as $item): ?>
                    <div class="info-box">
                        <?= $item['name'] ?><br>
                        我的得分：<?= $item['score'] ?> | 班级平均：<?= $item['class_avg'] ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div>
                <h3 style="color:var(--warning);margin-bottom:12px">⚠️ 待改进项目</h3>
                <?php if(empty($improvements)): ?>
                    <p>全部优秀！</p>
                <?php else: ?>
                    <?php foreach($improvements as $item): ?>
                    <div class="info-box warning">
                        <?= $item['name'] ?><br>
                        我的得分：<?= $item['score'] ?> | 班级平均：<?= $item['class_avg'] ?><br>
                        建议：<?= $item['suggestion'] ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-search"></i> 学习行为深度分析</div>
        <div class="info-grid">
            <div class="info-box">
                <h4>📅 学习活跃度</h4>
                活跃天数：<?= $totalActiveDays ?> 天<br>
                连续学习：<?= $hasContinuousStudy ? '是' : '否' ?><br>
                平均间隔：<?= round($avgDayGap,1) ?> 天
            </div>
            <div class="info-box">
                <h4>⏰ 学习时段</h4>
                最活跃：<?php $mt=array_reduce($timePeriodData,fn($a,$b)=>$a['behavior_count']>$b['behavior_count']?$a:$b,['behavior_count'=>0]); echo $mt['time_period']??'无' ?><br>
                工作日：<?= $weekdayWeekendData[0]['behavior_count'] ?> 次<br>
                周末：<?= $weekdayWeekendData[1]['behavior_count'] ?> 次
            </div>
            <div class="info-box">
                <h4>⚠️ 问题行为</h4>
                总违规：<?= $problemCount ?> 次<br>
                上课睡觉：<?= $sleepCount ?> 次<br>
                未交作业：<?= $noSubmitCount ?> 次
            </div>
            <div class="info-box">
                <h4>📈 学习趋势</h4>
                状态：<?= $progressDesc ?><br>
                进步率：<?= round($progressRate,1) ?>%<br>
                总分：<?= $totalPoints ?> 分
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-lightbulb"></i> 个性化建议</div>
        <ul class="suggestion-list">
            <?php foreach($suggestions as $s): ?><li><?= $s ?></li><?php endforeach; ?>
        </ul>
    </div>
</div>

<script>
// 柱状图：我 vs 班级平均
const barChart = echarts.init(document.getElementById('barChart'));
barChart.setOption({
    title: {text: '参与次数对比'},
    xAxis: {type: 'category', data: ['对战游戏','章节答题','回答问题','错题修炼','操作练习']},
    yAxis: {type: 'value'},
    series: [
        {name: '我的次数', type: 'bar', data: [<?= $performanceData['game']['record_count'] ?>,<?= $performanceData['quiz']['record_count'] ?>,<?= $performanceData['answer']['record_count'] ?>,<?= $performanceData['wrong_question']['record_count'] ?>,<?= $performanceData['practice']['record_count'] ?>]},
        {name: '班级平均', type: 'bar', data: [<?= $classAverages['game_count_avg']??0 ?>,<?= $classAverages['quiz_count_avg']??0 ?>,<?= $classAverages['answer_count_avg']??0 ?>,<?= $classAverages['wrong_count_avg']??0 ?>,<?= $classAverages['practice_count_avg']??0 ?>]}
    ]
});

// 雷达图：我 vs 班级
const radarChart = echarts.init(document.getElementById('radarChart'));
radarChart.setOption({
    title: {text: '能力综合对比'},
    radar: {indicator: [{name:'对战游戏',max:100},{name:'章节答题',max:100},{name:'回答问题',max:100},{name:'错题修炼',max:100},{name:'操作练习',max:100}]},
    series: [
        {type: 'radar', name: '我的能力', data: <?= json_encode($myRadar) ?>},
        {type: 'radar', name: '班级平均', data: <?= json_encode($classRadar) ?>}
    ]
});

// 热力图
const heatChart = echarts.init(document.getElementById('heatChart'));
const heatData = <?= json_encode($timePeriodData) ?>;
heatChart.setOption({
    title: {text: '学习时段分布'},
    series: {type: 'pie', radius: '50%', data: heatData.map(i=>({name:i.time_period,value:i.behavior_count}))}
});

// 饼图
const pieChart = echarts.init(document.getElementById('pieChart'));
pieChart.setOption({
    title: {text: '学习行为分布'},
    series: [{type: 'pie', radius: '60%', data: [
        {value: <?= $performanceData['game']['record_count'] ?>, name: '对战游戏'},
        {value: <?= $performanceData['quiz']['record_count'] ?>, name: '章节答题'},
        {value: <?= $performanceData['answer']['record_count'] ?>, name: '回答问题'},
        {value: <?= $performanceData['wrong_question']['record_count'] ?>, name: '错题修炼'},
        {value: <?= $performanceData['practice']['record_count'] ?>, name: '操作练习'},
        {value: <?= $performanceData['homework']['record_count'] ?>, name: '提交作业'}
    ]}]
});

window.addEventListener('resize', () => {
    barChart.resize(); radarChart.resize(); heatChart.resize(); pieChart.resize();
});
</script>
</body>
</html>

<?php
if ($canGenerateNew) {
    $reportContent = ob_get_contents();
    ob_end_flush();
    
    $saveLogSql = "INSERT INTO student_report_logs (student_id, report_date, report_content) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE report_date=NOW(), report_content=?";
    $stmt = $conn->prepare($saveLogSql);
    $stmt->bind_param("sss", $selectedStudentId, $reportContent, $reportContent);
    $stmt->execute();
    $stmt->close();
} else {
    if ($cacheContent) { echo $cacheContent; exit(); } else { ob_end_flush(); }
}
$conn->close();
?>