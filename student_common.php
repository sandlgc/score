<?php
/**
 * 学生端通用函数库
 * 包含登录验证、学生信息查询、承诺书检测等通用逻辑
 */

// 初始化会话和数据库连接
function studentInit() {
    ob_start();
    session_start();
    
    // 引入配置文件（确保config.php存在数据库连接$conn）
    if (!file_exists('config.php')) {
        die("错误：缺少配置文件config.php");
    }
    include 'config.php';
    
    // 验证登录状态
    if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "student") {
        $_SESSION['redirect_after_login'] = $_SERVER['PHP_SELF'];
        header("Location: login.php");
        ob_end_flush();
        exit();
    }
    
    return $conn;
}

// 查询学生基本信息（返回姓名）
function getStudentInfo($conn, $student_id) {
    $student_name = '未知学生';
    $sql = "SELECT name FROM students WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $student_name = $row["name"];
        // 将姓名存入session，避免重复查询
        $_SESSION["user_name"] = $student_name;
    }
    $stmt->close();
    return $student_name;
}

// 承诺书检测函数（复用原有逻辑）
function checkStudentCommitment($conn, $student_id) {
    // 1. 查询系统强制同意配置
    $sql_force_agree = "SELECT config_value FROM system_config WHERE config_key = 'force_commitment_agree'";
    $result_force_agree = $conn->query($sql_force_agree);
    $force_agree = $result_force_agree->fetch_assoc()['config_value'] ?? '1';

    // 2. 查询学生承诺书状态
    $sql_commit = "SELECT status, agree_status FROM student_discipline_commitment WHERE student_id = ?";
    $stmt = $conn->prepare($sql_commit);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $commit_result = $stmt->get_result();
    $commitment = $commit_result->fetch_assoc() ?: ['status' => 0, 'agree_status' => 0];
    $stmt->close();

    $has_submitted = $commitment['status'] == 1; // 是否提交承诺书
    $has_agreed = $commitment['agree_status'] == 1; // 是否同意

    // 3. 未提交承诺书：强制跳转至签署页
    if (!$has_submitted) {
        $_SESSION['redirect_after_commit'] = $_SERVER['PHP_SELF'];
        header("Location: student_commitment.php");
        ob_end_flush();
        exit();
    }

    // 4. 强制同意但未同意：返回需要重新签署的状态
    if ($force_agree == '1' && !$has_agreed) {
        return [
            'need_recommit' => true,
            'force_agree' => $force_agree,
            'has_agreed' => $has_agreed,
            'warning' => '系统要求必须同意课堂纪律承诺书'
        ];
    }

    // 5. 非强制同意但未同意：返回提示状态
    if ($force_agree == '0' && !$has_agreed) {
        return [
            'need_recommit' => false,
            'force_agree' => $force_agree,
            'has_agreed' => $has_agreed,
            'warning' => '您已提交承诺书但选择了"不同意"，请遵守课堂纪律'
        ];
    }

    // 6. 已签署并同意：返回正常状态
    return [
        'need_recommit' => false,
        'force_agree' => $force_agree,
        'has_agreed' => $has_agreed,
        'warning' => ''
    ];
}
?>