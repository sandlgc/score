<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$studentId = $_SESSION["user_id"];
$score = floatval($_GET['score']);
$chapter = $_GET['chapter'];
$action = "第 $chapter 章答题结束，获得积分 $score";
$actionTime = date('Y-m-d H:i:s');

// 获取当天日期
$today = date('Y-m-d');

try {
    // 创建数据库连接
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: ". $conn->connect_error);
    }

    // 查询章节历史正确率 - 使用 student_answer_records 表
    $chapterQuery = "SELECT 
                        AVG(accuracy) as avg_accuracy, 
                        COUNT(*) as attempt_count
                     FROM student_answer_records 
                     WHERE chapter =? 
                     GROUP BY chapter";
    $chapterStmt = $conn->prepare($chapterQuery);
    $chapterStmt->bind_param("s", $chapter);
    if (!$chapterStmt->execute()) {
        throw new Exception("查询章节历史数据失败: ". $chapterStmt->error);
    }
    $chapterResult = $chapterStmt->get_result();
    $chapterData = $chapterResult->fetch_assoc();
    $chapterStmt->close();

    // 计算动态阈值dynamicThreshold
    $baseThreshold = 0.7; // 默认基础阈值
    $dynamicThreshold = $baseThreshold;
    
    if ($chapterData && $chapterData['attempt_count'] > 5) { // 确保有足够数据
        $globalAccuracy = floatval($chapterData['avg_accuracy']) / 100; // 转为小数
        
        // 根据章节难度动态调整阈值
        if ($globalAccuracy > 0.8) { // 简单章节提高阈值
            $dynamicThreshold = min(0.9, $baseThreshold + 0.1);
        } elseif ($globalAccuracy < 0.5) { // 困难章节降低阈值
            $dynamicThreshold = max(0.6, $baseThreshold - 0.1);
        }
        
        // 查询学生在该章节的历史表现
        $studentHistoryQuery = "SELECT 
                                    AVG(accuracy) as student_avg_accuracy,
                                    COUNT(*) as student_attempts,
                                    MAX(consecutive_correct_max) as max_consecutive
                                FROM student_answer_records 
                                WHERE chapter =? AND student_id =?";
        $studentHistoryStmt = $conn->prepare($studentHistoryQuery);
        $studentHistoryStmt->bind_param("ss", $chapter, $studentId);
        if (!$studentHistoryStmt->execute()) {
            throw new Exception("查询学生历史数据失败: ". $studentHistoryStmt->error);
        }
        $studentHistoryResult = $studentHistoryStmt->get_result();
        $studentHistory = $studentHistoryResult->fetch_assoc();
        $studentHistoryStmt->close();
        
        // 根据学生表现调整阈值
        if ($studentHistory && $studentHistory['student_attempts'] > 2) {
            $studentAccuracy = floatval($studentHistory['student_avg_accuracy']) / 100;
            
            // 学生掌握较好时提高阈值
            if ($studentAccuracy > ($globalAccuracy + 0.1)) {
                $dynamicThreshold = min(0.95, $dynamicThreshold + 0.05);
            }
            
            // 连续答对次数多，可能已掌握
            if (intval($studentHistory['max_consecutive']) >= 5) {
                $dynamicThreshold = min(0.95, $dynamicThreshold + 0.1);
            }
        }
    }
    
    // 查询本次答题正确率 - 假设前端通过参数传递
    $correctRate = isset($_GET['correct_rate'])? floatval($_GET['correct_rate']) : 0;
    
    // 计算积分系数
    $pointCoefficient = 1.0;
    if ($correctRate > $dynamicThreshold) {
        // 超过阈值后积分打折
        $excessRate = ($correctRate - $dynamicThreshold) / (1 - $dynamicThreshold);
        $pointCoefficient = max(0.3, 1 - ($excessRate * 0.5)); // 最多打3折
    }
    
    // 应用积分系数
    $adjustedScore = $score * $pointCoefficient;
    
    // 查询当天该学生在该章节的积分记录次数
    $countQuery = "SELECT COUNT(*) as count FROM point_logs 
                   WHERE student_id =? 
                   AND action LIKE? 
                   AND DATE(action_time) =?";
    $countStmt = $conn->prepare($countQuery);
    $likePattern = "%第 $chapter 章答题结束%";
    $countStmt->bind_param("sss", $studentId, $likePattern, $today);
    if (!$countStmt->execute()) {
        throw new Exception("查询积分记录次数失败: ". $countStmt->error);
    }
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $recordCount = intval($countRow['count']);
    $countStmt->close();

    // 查询当天该学生的总积分
    $totalScoreQuery = "SELECT SUM(points_change) as total_score FROM point_logs 
                        WHERE student_id =? 
                        AND DATE(action_time) =?";
    $totalScoreStmt = $conn->prepare($totalScoreQuery);
    $totalScoreStmt->bind_param("ss", $studentId, $today);
    if (!$totalScoreStmt->execute()) {
        throw new Exception("查询总积分失败: ". $totalScoreStmt->error);
    }
    $totalScoreResult = $totalScoreStmt->get_result();
    $totalScoreRow = $totalScoreResult->fetch_assoc();
    $totalScore = floatval($totalScoreRow['total_score']);
    $totalScore = is_null($totalScore)? 0 : $totalScore;
    $totalScoreStmt->close();

    $message = "";
    // 判断是否满足记录条件
    if ($recordCount >= 3) {
        $message = "您今天在第 $chapter 章的积分记录次数已达到上限 3 次，请更换章节继续学习。";
    } elseif (($totalScore) >= 8) {
        $message = "您今天的选择题答题积分已达到$totalScore，超过上限 8 分，给你点个赞，虽然不记录积分但可以继续学习获取更多知识。";
    } else {
        // 更新action信息，包含积分调整信息
        if ($pointCoefficient < 1.0) {
            $action .= " (原积分$score，因正确率$correctRate 超过阈值$dynamicThreshold，积分调整为$adjustedScore)";
        }
        
        // 插入积分记录到 point_logs 表
        $stmt = $conn->prepare("INSERT INTO point_logs (student_id, action, points_change, action_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $studentId, $action, $adjustedScore, $actionTime);
        if (!$stmt->execute()) {
            throw new Exception("插入积分记录失败: ". $stmt->error);
        }
        $stmt->close();

        // 更新学生总积分
        $updateQuery = "UPDATE students SET points = points +? WHERE student_id =?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ds", $adjustedScore, $studentId);
        if (!$updateStmt->execute()) {
            throw new Exception("更新学生总积分失败: ". $updateStmt->error);
        }
        $updateStmt->close();

        // 记录答题记录到 student_answer_records 表
        $answerStmt = $conn->prepare("INSERT INTO student_answer_records 
                                      (student_id, chapter, answered_questions, correct_questions, accuracy, 
                                       consecutive_correct_max, wrong_question_ids, attempt_time) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        // 从请求参数获取答题详情
        $answeredQuestions = isset($_GET['answered_questions'])? intval($_GET['answered_questions']) : 0;
        $correctQuestions = isset($_GET['correct_questions'])? intval($_GET['correct_questions']) : 0;
        $consecutiveMax = isset($_GET['consecutive_max'])? intval($_GET['consecutive_max']) : 0;
        $wrongQuestionIds = isset($_GET['wrong_question_ids'])? $_GET['wrong_question_ids'] : '';
        
        $answerStmt->bind_param("ssiiidss", $studentId, $chapter, $answeredQuestions, $correctQuestions, 
                               $correctRate * 100, $consecutiveMax, $wrongQuestionIds, $actionTime);
                               
        if (!$answerStmt->execute()) {
            error_log("记录答题记录失败: ". $answerStmt->error);
            // 记录失败不影响主流程
        }
        $answerStmt->close();

        if ($pointCoefficient < 1.0) {
            $message = "积分记录成功！您已掌握该章节，继续练习积分打折为$adjustedScore 分，建议学习其他章节。";
        } else {
            $message = "积分记录成功！获得积分$adjustedScore 分。";
        }
    }

    $conn->close();
    
    //返回message
    error_log("信息记录: ". $message->error);

    // 返回JSON响应
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'originalScore' => $score,
        'adjustedScore' => $adjustedScore,
        'coefficient' => $pointCoefficient,
        'threshold' => $dynamicThreshold
    ]);
} catch (Exception $e) {
    // 记录错误信息到日志
    error_log("积分记录出错: ". $e->getMessage());
    // 返回JSON错误响应
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>