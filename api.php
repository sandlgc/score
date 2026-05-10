<?php
require_once 'vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Dotenv\Dotenv;

// 强制纯JSON输出 + 错误日志化
ini_set('display_errors', 0);       // 关闭页面输出错误
ini_set('log_errors', 1);           // 开启错误日志
ini_set('error_log', __DIR__ . '/api_error.log'); // 错误写入文件
header_remove(); // 清除所有可能的多余响应头
// 强制JSON头（覆盖所有情况）
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
date_default_timezone_set('Asia/Shanghai'); // 全局设置时区

// 加载环境变量
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// 数据库连接
$conn = new mysqli(
    $_ENV['DB_HOST'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASSWORD'],
    $_ENV['DB_NAME']
);

if ($conn->connect_error) {
    // 连接失败时返回 JSON 错误信息
    http_response_code(500);
    echo json_encode(['error' => '数据库连接失败', 'message' => $conn->connect_error]);
    exit;
}

// 设置字符集
$conn->set_charset("utf8mb4");

// 解析请求URL，分离路径和查询参数
$url = parse_url($_SERVER['REQUEST_URI']);
$path = isset($url['path']) ? $url['path'] : ''; // 路径部分（如 /score/api.php/students/...）
$query = isset($url['query']) ? $url['query'] : ''; // 查询参数（如 chapter=...）

// 配置正确的 basePath（包含脚本名）
$basePath = '/score2026/api.php';

// 移除 basePath，获取真实路由路径
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// 修剪路径并分割为数组
$path = trim($path, '/');
$parts = explode('/', $path);

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// 路由处理
try {
    // 路由匹配规则中不再包含 'api/' 前缀
    switch (true) {
        // 获取学生信息：请求路径为 /score/api.php/students/110813
        case ($method === 'GET' && count($parts) === 2 && $parts[0] === 'students'):
            getStudent($conn, $parts[1]);
            break;

        // 获取章节列表：请求路径为 /score/api.php/chapters
        case ($method === 'GET' && count($parts) === 1 && $parts[0] === 'chapters'):
            getChapters($conn);
            break;

        // 获取错题列表：请求路径为 /score/api.php/students/110813/wrong-questions
        case ($method === 'GET' && count($parts) === 3 && $parts[0] === 'students' && $parts[2] === 'wrong-questions'):
            getWrongQuestions($conn, $parts[1]);
            break;

        // 提交答案：请求路径为 /score/api.php/students/110813/answer
        case ($method === 'POST' && count($parts) === 3 && $parts[0] === 'students' && $parts[2] === 'answer'):
            submitAnswer($conn, $parts[1]);
            break;
            
        // 获取学生今日积分统计：请求路径为 /score/api.php/students/110813/today-points
		case ($method === 'GET' && count($parts) === 3 && $parts[0] === 'students' && $parts[2] === 'today-points'):
		    getTodayPoints($conn, $parts[1]);
		    break;

        default:
            http_response_code(404);
            echo json_encode(['error' => '未找到路由']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '服务器内部错误', 'message' => $e->getMessage()]);
}

// 获取学生信息
function getStudent($conn, $studentId) {
    $stmt = $conn->prepare("SELECT student_id, name, class, points FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => '学生不存在']);
        return;
    }
    
    $student = $result->fetch_assoc();
    $student['points'] = (float) $student['points'];
    echo json_encode($student);
}

// 获取所有章节
function getChapters($conn) {
    $result = $conn->query("SELECT DISTINCT chapter_name FROM questions");
    
    $chapters = [];
    while ($row = $result->fetch_assoc()) {
        $chapters[] = $row['chapter_name'];
    }
    
    echo json_encode($chapters);
}

// 获取学生错题
function getWrongQuestions($conn, $studentId) {
    $chapter = $_GET['chapter'] ?? null;
    
    // 查询学生的错题记录
    $query = "SELECT wrong_question_ids FROM student_wrong_questions WHERE student_id = ?";
    $params = [$studentId];
    
    if ($chapter) {
        $query .= " AND chapter = ?";
        $params[] = $chapter;
    }
    
    $stmt = $conn->prepare($query);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 收集所有错题ID
    $allWrongIds = [];
    while ($row = $result->fetch_assoc()) {
        $questionIds = explode(',', $row['wrong_question_ids']);
        $allWrongIds = array_merge($allWrongIds, array_filter($questionIds));
    }
    
    // 去重
    $uniqueWrongIds = array_unique($allWrongIds);
    
    if (empty($uniqueWrongIds)) {
        echo json_encode([]);
        return;
    }
    
    // 查询错题详情
    $placeholders = implode(',', array_fill(0, count($uniqueWrongIds), '?'));
    $stmt = $conn->prepare("SELECT id, chapter_name, question, option1, option2, option3, option4, answer 
                           FROM questions WHERE id IN ($placeholders)");
    
    $types = str_repeat('i', count($uniqueWrongIds));
    $stmt->bind_param($types, ...$uniqueWrongIds);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $wrongQuestions = [];
    while ($row = $result->fetch_assoc()) {
        $wrongQuestions[] = $row;
    }
    
    echo json_encode($wrongQuestions);
}

// 获取学生今日积分统计
function getTodayPoints($conn, $studentId) {
    $dailyMaxPoints = 10.0; // 每日上限
    $today = date('Y-m-d');
    
    // 查询今日已获得的积分
    $stmt = $conn->prepare("SELECT SUM(points_change) as today_points 
                           FROM point_logs 
                           WHERE student_id = ? 
                           AND action LIKE '错题重做%' 
                           AND DATE(created_at) = ?");
    $stmt->bind_param("ss", $studentId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $todayUsedPoints = $result->fetch_assoc()['today_points'] ?? 0;
    $todayUsedPoints = (float) $todayUsedPoints;
    $remainingPoints = $dailyMaxPoints - $todayUsedPoints;
    
    // 返回积分统计数据
    echo json_encode([
        'todayUsedPoints' => round($todayUsedPoints, 1),
        'remainingPoints' => round($remainingPoints, 1),
        'dailyMaxPoints' => $dailyMaxPoints
    ]);
}

// 提交答案
function submitAnswer($conn, $studentId) {
    // 解析JSON数据
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);
    
    // 验证JSON解析是否成功
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'error' => '无效的JSON数据',
            'rawData' => $rawData,
            'errorMsg' => json_last_error_msg()
        ]);
        return;
    }
    
    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['error' => '无效的请求数据']);
        return;
    }
    
    // 验证必要参数
    $requiredFields = ['questionId', 'selectedOption', 'isCorrect', 'chapter'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "缺少必要的参数: $field", 'received' => $data]);
            return;
        }
    }
    
    // 强制转换参数类型（确保与数据库匹配）
    $questionId = (int) $data['questionId'];
    $selectedOption = (int) $data['selectedOption'];
    $isCorrect = (bool) $data['isCorrect'];
    $chapter = trim($data['chapter']);
    
    // 检查学生是否存在
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => '学生不存在', 'studentId' => $studentId]);
        return;
    }
    
    // 检查题目是否存在
    $stmt = $conn->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->bind_param("i", $questionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => '题目不存在', 'questionId' => $questionId]);
        return;
    }

    // ========== 错题重做每日积分上限逻辑 ==========
    $dailyMaxPoints = 10.0; // 每日错题重做上限积分
    $pointsChange = 0.1;    // 单题答对积分
    $today = date('Y-m-d'); // 当前日期（格式：2025-12-11）
    
    // 查询今日该学生错题重做已获得的积分
    $stmt = $conn->prepare("SELECT SUM(points_change) as today_points 
                           FROM point_logs 
                           WHERE student_id = ? 
                           AND action LIKE '错题重做%' 
                           AND DATE(created_at) = ?");
    $stmt->bind_param("ss", $studentId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $todayUsedPoints = $result->fetch_assoc()['today_points'] ?? 0;
    $todayUsedPoints = (float) $todayUsedPoints; // 确保浮点型
    
    // 计算剩余可获得积分
    $remainingPoints = $dailyMaxPoints - $todayUsedPoints;
    $actualPoints = 0;
    
    // 仅答对且未达上限时才给予积分
    if ($isCorrect) {
        if ($remainingPoints <= 0) {
            // 已达每日上限，仅返回提示，不给予积分
            $response = [
                'success' => true,
                'message' => '答案提交成功，但今日错题重做积分已达上限（10分），本次无积分奖励',
                'isCorrect' => $isCorrect,
                'pointsAdded' => 0,
                'todayUsedPoints' => round($todayUsedPoints, 1),
                'dailyMaxPoints' => $dailyMaxPoints,
                'remainingPoints' => round($remainingPoints, 1)
            ];
            echo json_encode($response);
            
            // 仍需更新答题记录和错题本，但不修改积分
            updateAnswerRecord($conn, $studentId, $chapter, $questionId, $isCorrect);
            updateWrongQuestions($conn, $studentId, $chapter, $questionId, $isCorrect);
            return;
        } elseif ($remainingPoints < $pointsChange) {
            // 剩余积分不足单题分值，按剩余值发放
            $actualPoints = $remainingPoints;
        } else {
            // 正常发放单题积分
            $actualPoints = $pointsChange;
        }
    }
    // ========== 积分上限逻辑结束 ==========
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 仅答对且有可发放积分时记录积分变更
        if ($isCorrect && $actualPoints > 0) {
            $action = "错题重做-{$chapter}-题目:{$questionId}";
            $timestamp = date('Y-m-d H:i:s');
            
            // 插入积分日志
            $stmt = $conn->prepare("INSERT INTO point_logs (student_id, action, points_change, created_at, action_time) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdss", $studentId, $action, $actualPoints, $timestamp, $timestamp);
            $stmt->execute();
            
            // 更新学生积分
            $stmt = $conn->prepare("UPDATE students SET points = points + ? WHERE student_id = ?");
            $stmt->bind_param("ds", $actualPoints, $studentId);
            $stmt->execute();
        }
        
        // 更新学生答题记录
        updateAnswerRecord($conn, $studentId, $chapter, $questionId, $isCorrect);
        
        // 更新学生错题本
        updateWrongQuestions($conn, $studentId, $chapter, $questionId, $isCorrect);
        
        // 提交事务
        $conn->commit();
        
        // 计算最终积分数据
        $finalTodayUsed = round($todayUsedPoints + $actualPoints, 1);
        $finalRemaining = round($dailyMaxPoints - $finalTodayUsed, 1);
        
        // 返回响应
        $response = [
            'success' => true,
            'message' => $isCorrect ? 
                ($actualPoints > 0 ? '答案正确，积分已发放' : '答案正确，但今日错题重做积分已达上限') : 
                '答案错误，无积分奖励',
            'isCorrect' => $isCorrect,
            'pointsAdded' => round($actualPoints, 1),
            'todayUsedPoints' => $finalTodayUsed,
            'dailyMaxPoints' => $dailyMaxPoints,
            'remainingPoints' => $finalRemaining
        ];
        echo json_encode($response);
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            'error' => '提交答案失败',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString() // 生产环境建议移除
        ]);
    }
}

// 更新学生答题记录
function updateAnswerRecord($conn, $studentId, $chapter, $questionId, $isCorrect) {
    // 查找最近的答题记录
    $stmt = $conn->prepare("SELECT * FROM student_answer_records 
                           WHERE student_id = ? AND chapter = ? 
                           ORDER BY attempt_time DESC LIMIT 1");
    $stmt->bind_param("ss", $studentId, $chapter);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // 更新现有记录
        $record = $result->fetch_assoc();
        $answeredQuestions = $record['answered_questions'] + 1;
        $correctQuestions = $record['correct_questions'] + ($isCorrect ? 1 : 0);
        $accuracy = round(($correctQuestions / $answeredQuestions) * 100, 2);
        
        // 更新连续答对最高记录
        $consecutiveCorrectMax = $record['consecutive_correct_max'];
        if ($isCorrect) {
            $currentStreak = substr_count($record['wrong_question_ids'] ?? '', ',') + 1;
            $consecutiveCorrectMax = max($consecutiveCorrectMax, $currentStreak + 1);
        }
        
        // 更新错题ID列表
        $wrongQuestionIds = $record['wrong_question_ids'];
        if (!$isCorrect) {
            // 添加错题
            if ($wrongQuestionIds) {
                $wrongIds = explode(',', $wrongQuestionIds);
                if (!in_array($questionId, $wrongIds)) {
                    $wrongQuestionIds .= ",$questionId";
                }
            } else {
                $wrongQuestionIds = (string) $questionId;
            }
        } else {
            // 移除已答对的题
            if ($wrongQuestionIds) {
                $wrongIds = explode(',', $wrongQuestionIds);
                $wrongIds = array_diff($wrongIds, [(string) $questionId]);
                $wrongQuestionIds = implode(',', $wrongIds);
                if (empty($wrongIds)) $wrongQuestionIds = null;
            }
        }
        
        $stmt = $conn->prepare("UPDATE student_answer_records 
                              SET answered_questions = ?, 
                                  correct_questions = ?, 
                                  accuracy = ?, 
                                  consecutive_correct_max = ?, 
                                  wrong_question_ids = ?,
                                  attempt_time = NOW()
                              WHERE student_id = ?");
        $stmt->bind_param("iidisi", $answeredQuestions, $correctQuestions, $accuracy, $consecutiveCorrectMax, $wrongQuestionIds, $record['id']);
        $stmt->execute();
    } else {
        // 创建新记录
        $answeredQuestions = 1;
        $correctQuestions = $isCorrect ? 1 : 0;
        $accuracy = $isCorrect ? 100.0 : 0.0;
        $consecutiveCorrectMax = $isCorrect ? 1 : 0;
        $wrongQuestionIds = !$isCorrect ? (string) $questionId : null;
        
        $stmt = $conn->prepare("INSERT INTO student_answer_records 
                              (student_id, chapter, answered_questions, correct_questions, 
                               accuracy, consecutive_correct_max, wrong_question_ids, attempt_time)
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssiiids", 
            $studentId,          // s: 字符串
            $chapter,            // s: 字符串
            $answeredQuestions,  // i: 整数
            $correctQuestions,   // i: 整数
            $accuracy,           // d: 浮点数
            $consecutiveCorrectMax, // i: 整数
            $wrongQuestionIds    // s: 字符串（允许null）
        );
        $stmt->execute();
    }
}

// 更新学生错题本
function updateWrongQuestions($conn, $studentId, $chapter, $questionId, $isCorrect) {
    // 查找学生的错题记录
    $stmt = $conn->prepare("SELECT * FROM student_wrong_questions 
                           WHERE student_id = ? AND chapter = ?");
    $stmt->bind_param("ss", $studentId, $chapter);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $record = $result->fetch_assoc();
        $wrongQuestionIds = $record['wrong_question_ids'];
        
        if ($isCorrect) {
            // 从错题本中移除已答对的题
            $wrongIds = explode(',', $wrongQuestionIds);
            $wrongIds = array_diff($wrongIds, [(string) $questionId]);
            $wrongQuestionIds = implode(',', $wrongIds);
            
            if (empty($wrongIds)) {
                // 如果没有错题了，删除记录
                $stmt = $conn->prepare("DELETE FROM student_wrong_questions WHERE id = ?");
                $stmt->bind_param("i", $record['id']);
                $stmt->execute();
            } else {
                // 更新记录
                $stmt = $conn->prepare("UPDATE student_wrong_questions SET wrong_question_ids = ?, last_updated = NOW() WHERE id = ?");
                $stmt->bind_param("si", $wrongQuestionIds, $record['id']);
                $stmt->execute();
            }
        } else {
            // 添加新错题
            if ($wrongQuestionIds) {
                $wrongIds = explode(',', $wrongQuestionIds);
                if (!in_array($questionId, $wrongIds)) {
                    $wrongQuestionIds .= ",$questionId";
                    
                    $stmt = $conn->prepare("UPDATE student_wrong_questions SET wrong_question_ids = ?, last_updated = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $wrongQuestionIds, $record['id']);
                    $stmt->execute();
                }
            } else {
                $stmt = $conn->prepare("UPDATE student_wrong_questions SET wrong_question_ids = ?, last_updated = NOW() WHERE id = ?");
                $stmt->bind_param("si", $questionId, $record['id']);
                $stmt->execute();
            }
        }
    } else if (!$isCorrect) {
        // 创建新的错题记录
        $stmt = $conn->prepare("INSERT INTO student_wrong_questions 
                              (student_id, chapter, wrong_question_ids, last_updated)
                              VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sss", $studentId, $chapter, $questionId);
        $stmt->execute();
    }
}
?>