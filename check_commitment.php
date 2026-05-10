<?php
/**
 * 学生承诺书检测通用函数
 * 需在session_start()后调用
 */
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
        // 记录来源页面（排除签署页本身，避免循环跳转）
        $current_page = basename($_SERVER['PHP_SELF']);
        $exclude_pages = ['student_commitment.php', 'save_commitment.php'];
        if (!in_array($current_page, $exclude_pages)) {
            $_SESSION['redirect_after_commit'] = $_SERVER['REQUEST_URI'];
        } else {
            $_SESSION['redirect_after_commit'] = 'student_menu.php';
        }
        // 跳转至签署页
        header("Location: student_commitment.php");
        exit();
    }

    // 4. 强制同意但未同意：跳转至签署页并提示
    if ($force_agree == '1' && !$has_agreed) {
        $_SESSION['force_agree_warning'] = "您必须同意承诺书才能使用系统功能，请重新签署！";
        $_SESSION['redirect_after_commit'] = $_SERVER['REQUEST_URI'];
        header("Location: student_commitment.php");
        exit();
    }

    // 5. 非强制同意但未同意：返回警告提示（不跳转，仅提示）
    if ($force_agree == '0' && !$has_agreed) {
        return [
            'has_agreed' => false,
            'warning' => '您已提交承诺书但选择了"不同意"，请遵守课堂纪律！'
        ];
    }

    // 6. 已签署并同意：返回正常状态
    return [
        'has_agreed' => true,
        'warning' => ''
    ];
}
?>