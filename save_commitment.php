<?php
session_start();
include 'config.php';

// 验证请求来源
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "student") {
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => '非法请求'));
    exit();
}

// 获取请求参数（匹配前端传递的参数名）
$student_id = $_POST['student_id'] ?? '';
$student_name = $_POST['student_name'] ?? '';
$student_class = $_POST['student_class'] ?? '';
$signature_data = $_POST['signature_data'] ?? ''; // 学生签名（前端传递的是signature_data）
$parent_signature_data = $_POST['parent_signature_data'] ?? ''; // 家长签名（前端传递的是parent_signature_data）
$agree_status = $_POST['agree_status'] ?? ''; // 新增：是否同意状态
$parent_signature_required = $_POST['parent_signature_required'] ?? '1';

// 新增：获取强制同意配置（关键修复）
$sql_force = "SELECT config_value FROM system_config WHERE config_key = 'force_agree_commitment'";
$result_force = $conn->query($sql_force);
$force_agree = $result_force->fetch_assoc()['config_value'] ?? '1';

// 验证核心参数完整性
if (empty($student_id) || empty($student_name) || empty($student_class) || empty($signature_data)) {
    echo json_encode(array('success' => false, 'message' => '参数不完整（学生信息/签名不能为空）'));
    exit();
}

// 修复：验证是否选择同意状态（兼容字符串/数字类型）
if ($agree_status !== '0' && $agree_status !== '1' && $agree_status !== 0 && $agree_status !== 1) {
    echo json_encode(array('success' => false, 'message' => '请选择是否同意承诺书内容'));
    exit();
}

// 统一转换为字符串（避免类型问题）
$agree_status = (string)$agree_status;

// 验证学生签名数据格式（Base64）
if (!preg_match('/^data:image\/png;base64,/', $signature_data)) {
    echo json_encode(array('success' => false, 'message' => '学生签名格式无效，请重新签名'));
    exit();
}

// 验证家长签名格式（仅当配置开启且传递了签名时）
if ($parent_signature_required == '1' && !empty($parent_signature_data) && !preg_match('/^data:image\/png;base64,/', $parent_signature_data)) {
    echo json_encode(array('success' => false, 'message' => '家长签名格式无效，请重新签名'));
    exit();
}

// 检查是否已签署
$sql_check = "SELECT id FROM student_discipline_commitment WHERE student_id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("s", $student_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

// 准备SQL语句（适配字段名和同意状态）
if ($result_check->num_rows > 0) {
    // 已签署，更新数据
    if ($parent_signature_required == '1') {
        $sql = "UPDATE student_discipline_commitment 
                SET student_name = ?, 
                    class = ?, 
                    signature_data = ?, 
                    parent_signature_data = ?,
                    agree_status = ?,
                    status = 1, 
                    signed_time = NOW(), 
                    parent_signed_time = NOW(), 
                    updated_at = NOW()
                WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        // 绑定参数：name, class, 学生签名, 家长签名, 同意状态, 学生ID
        $stmt->bind_param("ssssis", $student_name, $student_class, $signature_data, $parent_signature_data, $agree_status, $student_id);
    } else {
        $sql = "UPDATE student_discipline_commitment 
                SET student_name = ?, 
                    class = ?, 
                    signature_data = ?,
                    agree_status = ?,
                    status = 1, 
                    signed_time = NOW(), 
                    updated_at = NOW()
                WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        // 绑定参数：name, class, 学生签名, 同意状态, 学生ID
        $stmt->bind_param("sssis", $student_name, $student_class, $signature_data, $agree_status, $student_id);
    }
} else {
    // 未签署，插入新数据
    if ($parent_signature_required == '1') {
        $sql = "INSERT INTO student_discipline_commitment 
                (student_id, student_name, class, signature_data, parent_signature_data, 
                 agree_status, status, signed_time, parent_signed_time, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        // 绑定参数：学生ID, name, class, 学生签名, 家长签名, 同意状态
        $stmt->bind_param("sssssi", $student_id, $student_name, $student_class, $signature_data, $parent_signature_data, $agree_status);
    } else {
        $sql = "INSERT INTO student_discipline_commitment 
                (student_id, student_name, class, signature_data, 
                 agree_status, status, signed_time, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        // 绑定参数：学生ID, name, class, 学生签名, 同意状态
        $stmt->bind_param("ssssi", $student_id, $student_name, $student_class, $signature_data, $agree_status);
    }
}

// 执行SQL并返回结果
try {
    if ($stmt->execute()) {
        $response = array(
            'success' => true, 
            'message' => $agree_status == '1' ? '承诺书签署成功！' : '承诺书已提交（您选择了不同意）',
            'agree_status' => $agree_status, // 返回同意状态
            'force_agree' => $force_agree // 现在变量已定义，可正常返回
        );
        echo json_encode($response);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false, 
        'message' => '数据库操作失败：' . $e->getMessage()
    ));
}

// 关闭数据库连接
$stmt_check->close();
if (isset($stmt) && $stmt) {
    $stmt->close();
}
$conn->close();
?>