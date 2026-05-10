<?php
// 基础配置：字符集、时区、session
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Shanghai');
session_start();
include 'config.php';

// 强制登录校验
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 初始化全局变量（关键修复：避免未定义警告）
$studentId = $_SESSION["user_id"];
$score = 0.0;            // 默认初始值
$chapter = '';           // 默认初始值
$action = '';            // 默认初始值
$actionTime = date('Y-m-d H:i:s');
$adjustedScore = 0.0;
$reasonStr = "";         // 明确初始化为空字符串
$pointCoefficient = 1.0; // 默认系数
$dynamicThreshold = 0.7; // 默认阈值
$timeCoefficient = 1.0;  // 默认时间系数
$correctRate = 0.0;      // 默认正确率

// 校验必要参数（score）
if (!isset($_GET['score'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => '缺少必要的参数: score',
        'originalScore' => '0.0',
        'adjustedScore' => '0.0',
        'coefficient' => '1.00',
        'threshold' => '0.70',
        'timeCoefficient' => '1.00',
        'correctRate' => '0.00'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 解析参数（确保类型安全）
$score = round(floatval($_GET['score']), 2); // 原始积分保留两位小数
$chapter = trim($_GET['chapter'] ?? ''); 
$action = "第 {$chapter} 章答题结束，获得积分 " . number_format($score, 2); // 初始action显示两位小数 

// 初始化积分计算日志
$logData = [
    'timestamp' => $actionTime,
    'student_id' => $studentId,
    'chapter' => $chapter,
    'original_score' => $score,
    'calculation_steps' => []
];

try {
    // 数据库连接（带字符集校验）
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("设置数据库字符集失败: " . $conn->error);
    }

    // 查询章节历史数据（防SQL注入）
    $chapterQuery = "SELECT 
                        AVG(accuracy) as avg_accuracy, 
                        COUNT(*) as attempt_count,
                        AVG(answer_time) as avg_answer_time,
                        AVG(answered_questions) as avg_questions
                     FROM student_answer_records 
                     WHERE chapter = ? 
                     GROUP BY chapter";
    $chapterStmt = $conn->prepare($chapterQuery);
    $chapterStmt->bind_param("s", $chapter);
    if (!$chapterStmt->execute()) {
        throw new Exception("查询章节历史数据失败: " . $chapterStmt->error);
    }
    $chapterResult = $chapterStmt->get_result();
    $chapterData = $chapterResult->fetch_assoc();
    $chapterStmt->close();
    $logData['calculation_steps'][] = ['step' => '获取章节历史数据', 'data' => $chapterData ?: []];

    // 动态阈值计算（新增防未定义判断）
    $baseThreshold = 0.7;
    $dynamicThreshold = $baseThreshold;
    $logData['calculation_steps'][] = ['step' => '初始阈值', 'value' => $baseThreshold];

    if (!empty($chapterData) && $chapterData['attempt_count'] > 5) {
        $globalAccuracy = isset($chapterData['avg_accuracy']) ? floatval($chapterData['avg_accuracy']) / 100 : 0;
        $avgAnswerTime = isset($chapterData['avg_answer_time']) ? floatval($chapterData['avg_answer_time']) : 0;
        $avgQuestions = isset($chapterData['avg_questions']) ? floatval($chapterData['avg_questions']) : 0;

        // 章节难度调整阈值
        if ($globalAccuracy > 0.8) {
            $dynamicThreshold = min(0.9, $baseThreshold + 0.1);
        } elseif ($globalAccuracy < 0.5) {
            $dynamicThreshold = max(0.6, $baseThreshold - 0.1);
        }
        $logData['calculation_steps'][] = [
            'step' => '根据章节难度调整阈值',
            'global_accuracy' => $globalAccuracy,
            'new_threshold' => $dynamicThreshold
        ];

        // 查询学生历史表现（防SQL注入）
        $studentHistoryQuery = "SELECT 
                                    AVG(accuracy) as student_avg_accuracy,
                                    COUNT(*) as student_attempts,
                                    MAX(consecutive_correct_max) as max_consecutive,
                                    AVG(answer_time) as student_avg_time
                                FROM student_answer_records 
                                WHERE chapter = ? AND student_id = ?";
        $studentHistoryStmt = $conn->prepare($studentHistoryQuery);
        $studentHistoryStmt->bind_param("ss", $chapter, $studentId);
        if (!$studentHistoryStmt->execute()) {
            throw new Exception("查询学生历史数据失败: " . $studentHistoryStmt->error);
        }
        $studentHistoryResult = $studentHistoryStmt->get_result();
        $studentHistory = $studentHistoryResult->fetch_assoc();
        $studentHistoryStmt->close();
        $logData['calculation_steps'][] = ['step' => '获取学生历史数据', 'data' => $studentHistory ?: []];

        // 学生表现调整阈值（新增空值校验）
        if (!empty($studentHistory) && $studentHistory['student_attempts'] > 2) {
            $studentAccuracy = isset($studentHistory['student_avg_accuracy']) ? floatval($studentHistory['student_avg_accuracy']) / 100 : 0;
            $studentAvgTime = isset($studentHistory['student_avg_time']) ? floatval($studentHistory['student_avg_time']) : 0;
            $originalThreshold = $dynamicThreshold;

            if ($studentAccuracy > ($globalAccuracy + 0.1)) {
                $dynamicThreshold = min(0.95, $dynamicThreshold + 0.05);
            }
            if (intval($studentHistory['max_consecutive'] ?? 0) >= 5) {
                $dynamicThreshold = min(0.95, $dynamicThreshold + 0.1);
            }
            if ($avgAnswerTime > 0 && $studentAvgTime < ($avgAnswerTime * 0.6)) {
                $dynamicThreshold = min(0.95, $dynamicThreshold + 0.1);
            }

            if ($originalThreshold != $dynamicThreshold) {
                $logData['calculation_steps'][] = [
                    'step' => '根据学生表现调整阈值',
                    'student_accuracy' => $studentAccuracy,
                    'student_avg_time' => $studentAvgTime,
                    'avg_answer_time' => $avgAnswerTime,
                    'original_threshold' => $originalThreshold,
                    'new_threshold' => $dynamicThreshold
                ];
            }
        }
    }

    // 解析本次答题数据（新增空值默认）
    $correctRate = isset($_GET['correct_rate']) ? floatval($_GET['correct_rate']) : 0;
    $answerTime = isset($_GET['answer_time']) ? intval($_GET['answer_time']) : 0;
    $answeredQuestions = isset($_GET['answered_questions']) ? intval($_GET['answered_questions']) : 0;
    $logData['calculation_steps'][] = [
        'step' => '获取本次答题数据',
        'correct_rate' => $correctRate,
        'answer_time' => $answerTime,
        'answered_questions' => $answeredQuestions
    ];

    // 计算每题平均时间（防除零错误）
    $avgTimePerQuestion = ($answerTime > 0 && $answeredQuestions > 0) ? ($answerTime / $answeredQuestions) : 0;
    $logData['calculation_steps'][] = ['step' => '计算每题平均时间', 'value' => $avgTimePerQuestion];

    // 时间系数计算（防未定义）
    $timeCoefficient = ($avgTimePerQuestion > 0 && $avgTimePerQuestion < 3) ? max(0.5, $avgTimePerQuestion / 3) : 1.0;
    $logData['calculation_steps'][] = [
        'step' => '计算时间系数',
        'avg_time_per_question' => $avgTimePerQuestion,
        'value' => $timeCoefficient
    ];

    // 查询学生历史章节答题次数（防SQL注入）
    $historyCountQuery = "SELECT COUNT(*) as count FROM point_logs 
                         WHERE student_id = ? 
                         AND action LIKE ?";
    $historyCountStmt = $conn->prepare($historyCountQuery);
    $likePattern = "%第 {$chapter} 章答题结束%";
    $historyCountStmt->bind_param("ss", $studentId, $likePattern);
    if (!$historyCountStmt->execute()) {
        throw new Exception("查询历史积分记录次数失败: " . $historyCountStmt->error);
    }
    $historyCountResult = $historyCountStmt->get_result();
    $historyCountRow = $historyCountResult->fetch_assoc();
    $historyRecordCount = intval($historyCountRow['count'] ?? 0);
    $historyCountStmt->close();
    $logData['calculation_steps'][] = ['step' => '查询历史章节积分记录次数', 'count' => $historyRecordCount];

    // 积分系数计算（新增空值判断和历史前两次不打折逻辑）
    $excessRate = 0;
    if ($historyRecordCount >= 2) { // 只对第三次及以后的答题应用正确率打折
        if ($correctRate > $dynamicThreshold) {
            $excessRate = ($correctRate - $dynamicThreshold) / (1 - $dynamicThreshold);
            $pointCoefficient = max(0.3, 1 - ($excessRate * 0.5));
        }
    }
    $logData['calculation_steps'][] = [
        'step' => '计算积分系数',
        'correct_rate' => $correctRate,
        'dynamic_threshold' => $dynamicThreshold,
        'excess_rate' => $excessRate,
        'value' => $pointCoefficient
    ];

    // 应用时间系数（关键修复：确保系数不为负）
    $pointCoefficient = max(0.3, $pointCoefficient * $timeCoefficient);
    $logData['calculation_steps'][] = [
        'step' => '应用时间系数后的最终积分系数',
        'value' => $pointCoefficient
    ];

    // 调整后积分（防负分,新增四舍五入处理，强制两位小数）
    $adjustedScore = round(max(0, $score * $pointCoefficient), 2); // 关键修改：四舍五入到两位小数
    $logData['calculation_steps'][] = [
        'step' => '计算调整后积分',
        'original_score' => $score,
        'adjusted_score' => $adjustedScore
    ];

    // 查询当天章节积分记录次数（防SQL注入）
    $today = date('Y-m-d');
    $todayCountQuery = "SELECT COUNT(*) as count FROM point_logs 
                       WHERE student_id = ? 
                       AND action LIKE ? 
                       AND DATE(action_time) = ?";
    $todayCountStmt = $conn->prepare($todayCountQuery);
    $likePattern = "%第 {$chapter} 章答题结束%";
    $todayCountStmt->bind_param("sss", $studentId, $likePattern, $today);
    if (!$todayCountStmt->execute()) {
        throw new Exception("查询当天积分记录次数失败: " . $todayCountStmt->error);
    }
    $todayCountResult = $todayCountStmt->get_result();
    $todayCountRow = $todayCountResult->fetch_assoc();
    $todayRecordCount = intval($todayCountRow['count'] ?? 0);
    $todayCountStmt->close();
    $logData['calculation_steps'][] = ['step' => '查询当天章节积分记录次数', 'count' => $todayRecordCount];

    // 查询当天章节答题总积分（防SQL注入）
    $totalScoreQuery = "SELECT SUM(points_change) as total_score FROM point_logs 
                        WHERE student_id = ? 
                        AND action LIKE '%章答题结束%'
                        AND DATE(action_time) = ?";
    $totalScoreStmt = $conn->prepare($totalScoreQuery);
    $totalScoreStmt->bind_param("ss", $studentId, $today);
    if (!$totalScoreStmt->execute()) {
        throw new Exception("查询总积分失败: " . $totalScoreStmt->error);
    }
    $totalScoreResult = $totalScoreStmt->get_result();
    $totalScoreRow = $totalScoreResult->fetch_assoc();
    $totalScore = floatval($totalScoreRow['total_score'] ?? 0);
    $totalScoreStmt->close();
    $logData['calculation_steps'][] = ['step' => '查询当天章节答题总积分', 'total_score' => $totalScore];

    // 积分记录逻辑（关键修复：消息拼接优化）
    if ($todayRecordCount >= 3) {
        $message = "您今天在第 {$chapter} 章的积分记录次数已达到上限 3 次，请更换章节继续学习。";
        $adjustedScore = 0;
        $logData['calculation_steps'][] = [
            'step' => '积分记录结果',
            'reason' => '次数超限',
            'final_score' => $adjustedScore
        ];
    } elseif ($totalScore >= 8) {
        $message = "您今天的选择题答题积分已达到{$totalScore}分，超过上限 8 分，给你点个赞，虽然不记录积分但可以继续学习获取更多知识。";
        $adjustedScore = 0;
        $logData['calculation_steps'][] = [
            'step' => '积分记录结果',
            'reason' => '总积分超限',
            'final_score' => $adjustedScore
        ];
    } else {
        // 调整原因生成（关键修复：兜底文案）
        $adjustmentReason = [];
        if ($pointCoefficient < 1.0) {
            if ($timeCoefficient < 1.0) {
                $adjustmentReason[] = "答题速度过快";
            }
            if ($correctRate > $dynamicThreshold && $historyRecordCount >= 2) {
                $adjustmentReason[] = "正确率超过阈值";
            }
            $reasonStr = !empty($adjustmentReason) ? implode("和", $adjustmentReason) : "系统调整"; // 兜底原因
        }

        // 拼接action（关键修复：避免空值,精度调整为两位小数）
        $actionSuffix = $pointCoefficient < 1.0 
            ? " (原积分" . number_format($score, 2) . "，因{$reasonStr}，积分调整为" . number_format($adjustedScore, 2) . ")"
            : " (原积分" . number_format($score, 2) . "，未调整)";
        $action .= $actionSuffix;

        // 插入积分记录（防SQL注入）
        $stmt = $conn->prepare("INSERT INTO point_logs (student_id, action, points_change, action_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $studentId, $action, $adjustedScore, $actionTime);
        if (!$stmt->execute()) {
            throw new Exception("插入积分记录失败: " . $stmt->error);
        }
        $stmt->close();

        // 更新学生总积分（防SQL注入）
        $updateQuery = "UPDATE students SET points = points + ? WHERE student_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ds", $adjustedScore, $studentId);
        if (!$updateStmt->execute()) {
            throw new Exception("更新学生总积分失败: " . $updateStmt->error);
        }
        $updateStmt->close();

        // 生成提示消息（关键修复：避免空原因，精度调整为两位小数）
        $message = $pointCoefficient < 1.0 
            ? "积分记录成功！您的积分因{$reasonStr}打折为" . number_format($adjustedScore, 2) . "分，建议认真学习以获取更高积分。"
            : "积分记录成功！获得积分" . number_format($adjustedScore, 2) . "分。";

        $logData['calculation_steps'][] = [
            'step' => '积分记录结果',
            'reason' => '正常记录',
            'final_score' => $adjustedScore,
            'adjustment_reason' => $adjustmentReason
        ];
    }

    // 关闭连接并记录成功日志
    $conn->close();
    $logData['final_result'] = [
        'status' => 'success',
        'message' => $message,
        'adjusted_score' => $adjustedScore
    ];
    error_log("积分计算过程: " . json_encode($logData, JSON_UNESCAPED_UNICODE));

    // 返回成功响应（数值格式化所有数值统一两位小数）
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'originalScore' => (string)number_format($score, 2),
        'adjustedScore' => (string)number_format($adjustedScore, 2),
        'coefficient' => (string)number_format($pointCoefficient, 2),
        'threshold' => (string)number_format($dynamicThreshold, 2),
        'timeCoefficient' => (string)number_format($timeCoefficient, 2),
        'correctRate' => (string)number_format($correctRate, 2),
        'historyRecordCount' => (string)$historyRecordCount
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // 异常处理（关键修复：确保变量有默认值）
    $logData['final_result'] = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    error_log("积分记录出错: " . $e->getMessage());
    error_log("积分计算过程: " . json_encode($logData, JSON_UNESCAPED_UNICODE));

    // 强制初始化变量（避免未定义警告）
    $score = isset($score) ? $score : 0.0;
    $adjustedScore = 0.0;
    $pointCoefficient = 1.0;
    $dynamicThreshold = 0.7;
    $timeCoefficient = 1.0;
    $correctRate = 0.0;
    $reasonStr = "系统异常";
    $historyRecordCount = 0;

    // 返回错误响应（数值格式化）
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'originalScore' => (string)number_format($score, 1),
        'adjustedScore' => (string)number_format($adjustedScore, 1),
        'coefficient' => (string)number_format($pointCoefficient, 2),
        'threshold' => (string)number_format($dynamicThreshold, 2),
        'timeCoefficient' => (string)number_format($timeCoefficient, 2),
        'correctRate' => (string)number_format($correctRate, 2),
        'historyRecordCount' => '0'
    ], JSON_UNESCAPED_UNICODE);
}
?>