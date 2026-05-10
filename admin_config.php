<?php
session_start();
include 'config.php';

// 复用admin_menu.php的管理员验证逻辑
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit();
}

// 获取当前配置
$sql = "SELECT config_key, config_value FROM system_config WHERE config_key IN ('parent_signature_required', 'force_agree_commitment')";
$result = $conn->query($sql);
$configs = [];
while ($row = $result->fetch_assoc()) {
    $configs[$row['config_key']] = $row['config_value'];
}
$parent_signature_required = $configs['parent_signature_required'] ?? '1';
$force_agree_commitment = $configs['force_agree_commitment'] ?? '1'; // 1=强制同意才能登录，0=仅需提交即可

// 更新配置
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 处理家长签名配置
    if (isset($_POST['parent_signature'])) {
        $new_parent_value = $_POST['parent_signature'] === '1' ? '1' : '0';
        $sql = "UPDATE system_config SET config_value = ? WHERE config_key = 'parent_signature_required'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $new_parent_value);
        if (!$stmt->execute()) {
            $error_msg = "家长签名配置更新失败：" . $conn->error;
        }
    }
    
    // 处理强制同意配置
    if (isset($_POST['force_agree'])) {
        $new_force_value = $_POST['force_agree'] === '1' ? '1' : '0';
        $sql = "UPDATE system_config SET config_value = ? WHERE config_key = 'force_agree_commitment'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $new_force_value);
        if (!$stmt->execute()) {
            $error_msg = "强制同意配置更新失败：" . $conn->error;
        }
    }
    
    // 更新成功提示
    if (!isset($error_msg)) {
        $success_msg = "所有配置更新成功！";
        // 重新获取最新配置
        $parent_signature_required = $_POST['parent_signature'] ?? $parent_signature_required;
        $force_agree_commitment = $_POST['force_agree'] ?? $force_agree_commitment;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>承诺书系统配置</title>
    <style>
        /* 保持与管理员菜单一致的样式风格 */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 2.5em;
            color: #007BFF;
            margin-bottom: 20px;
            text-align: center;
        }

        h2 {
            font-size: 1.8em;
            color: #555;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .form-group:last-child {
            border-bottom: none;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-size: 1.1em;
            font-weight: 600;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn {
            background-color: #007BFF;
            color: #fff;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1.1em;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .success {
            color: green;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #28a745;
            border-radius: 5px;
            text-align: center;
        }

        .error {
            color: red;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #dc3545;
            border-radius: 5px;
            text-align: center;
        }

        .back-menu {
            margin-top: 20px;
            text-align: center;
        }

        .back-menu a {
            color: #007BFF;
            text-decoration: none;
            font-size: 1.1em;
        }

        .back-menu a:hover {
            text-decoration: underline;
        }

        .logout-btn {
            display: inline-block;
            margin-top: 20px;
            background-color: #dc3545;
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .logout-btn:hover {
            background-color: #c82333;
        }

        .note {
            font-size: 0.9em;
            color: #666;
            margin-top: 8px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>欢迎，管理员</h1>
    <div class="container">
        <h2>承诺书系统配置</h2>
        
        <?php if (isset($success_msg)) echo "<div class='success'>$success_msg</div>"; ?>
        <?php if (isset($error_msg)) echo "<div class='error'>$error_msg</div>"; ?>
        
        <form method="post">
            <!-- 家长签名配置 -->
            <div class="form-group">
                <label>家长签名要求：</label>
                <div class="radio-group">
                    <label style="display:inline;">
                        <input type="radio" name="parent_signature" value="1" <?php echo $parent_signature_required == '1' ? 'checked' : ''; ?>> 
                        需要家长签名
                    </label>
                    <label style="display:inline;">
                        <input type="radio" name="parent_signature" value="0" <?php echo $parent_signature_required == '0' ? 'checked' : ''; ?>> 
                        不需要家长签名
                    </label>
                </div>
            </div>

            <!-- 强制同意配置 -->
            <div class="form-group">
                <label>承诺书同意强制要求：</label>
                <div class="radio-group">
                    <label style="display:inline;">
                        <input type="radio" name="force_agree" value="1" <?php echo $force_agree_commitment == '1' ? 'checked' : ''; ?>> 
                        必须同意才能登录系统
                    </label>
                    <label style="display:inline;">
                        <input type="radio" name="force_agree" value="0" <?php echo $force_agree_commitment == '0' ? 'checked' : ''; ?>> 
                        仅需提交（同意/不同意均可登录）
                    </label>
                </div>
                <div class="note">
                    注：选择"必须同意"时，学生选择"不同意"将被强制退出登录；选择"仅需提交"时，无论同意与否均可正常使用系统。
                </div>
            </div>

            <button type="submit" class="btn">保存配置</button>
        </form>
        
        <div class="back-menu">
            <a href="admin_menu.php">返回管理员菜单</a>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="logout.php" class="logout-btn">退出登录</a>
        </div>
    </div>
</body>
</html>