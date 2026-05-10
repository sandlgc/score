<?php
// 引入公共函数库
include 'student_common.php';

// 初始化（自动验证登录）
$conn = studentInit();
$student_id = $_SESSION["user_id"];

// 优先从session获取姓名（已由公共函数存入），兜底查询数据库
$student_name = $_SESSION["user_name"] ?? getStudentInfo($conn, $student_id);

// 获取参数
$video_name = $_POST['video_name'] ?? '';
$video_full_name = $_POST['video_full_name'] ?? '';

if (empty($video_name) || empty($video_full_name)) {
    echo json_encode(['code' => 400, 'msg' => '参数缺失']);
    exit();
}

// 记录开始观看的函数
function recordWatchStart($conn, $student_id, $student_name, $video_name, $video_full_name) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $watch_start_time = date('Y-m-d H:i:s');

    $sql = "INSERT INTO student_video_watch_log 
            (student_id, student_name, video_name, video_full_name, watch_start_time, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $student_id, $student_name, $video_name, $video_full_name, $watch_start_time, $ip_address, $user_agent);
    $stmt->execute();
    $log_id = $stmt->insert_id;
    $stmt->close();

    return $log_id;
}

// 执行记录并返回结果
$log_id = recordWatchStart($conn, $student_id, $student_name, $video_name, $video_full_name);
if ($log_id > 0) {
    echo json_encode([
        'code' => 200,
        'msg' => '记录成功',
        'data' => ['log_id' => $log_id]
    ]);
} else {
    echo json_encode(['code' => 500, 'msg' => '记录失败']);
}

$conn->close();
?>