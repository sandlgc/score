<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit();
}

// 设置内部编码为 UTF-8
mb_internal_encoding('UTF-8');

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excel_file"])) {
    // 校验上传文件
    if ($_FILES["excel_file"]["error"] !== UPLOAD_ERR_OK) {
        $error = "文件上传失败，错误码：" . $_FILES["excel_file"]["error"];
    } else {
        $file = $_FILES["excel_file"]["tmp_name"];
        // 检查文件是否为CSV
        $file_ext = pathinfo($_FILES["excel_file"]["name"], PATHINFO_EXTENSION);
        if (strtolower($file_ext) !== 'csv') {
            $error = "请上传 CSV 格式的文件！";
        } else {
            $handle = fopen($file, "r");
            if (!$handle) {
                $error = "无法打开上传的文件！";
            } else {
                // 准备插入数据的预处理语句
                $insert_sql = "REPLACE INTO students (student_id, password, class, name, gender) VALUES (?,?,?,?,?)";
                $stmt = $conn->prepare($insert_sql);
                
                if (!$stmt) {
                    $error = "SQL 预处理语句失败：" . $conn->error;
                } else {
                    $row_count = 0;
                    // 循环读取CSV（跳过表头行，若CSV有表头则保留这行，无则注释）
                    // fgetcsv($handle, 1000, ","); // 跳过第一行表头（根据你的CSV是否有表头选择）
                    
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $row_count++;
                        // 校验列数是否正确
                        if (count($data) < 5) {
                            $error = "第 $row_count 行数据不完整，缺少列！";
                            break;
                        }

                        // 关键修复：强制转换为字符串并去除首尾空白（保留开头0）
                        $student_id = (string)trim($data[0]); // 强制字符串，保留开头0
                        $plain_password = (string)trim($data[1]); // 密码同理保留开头0
                        $class = (string)trim($data[2]);
                        $name = (string)trim($data[3]);
                        $gender = (string)trim($data[4]);

                        // 基础数据校验
                        if (empty($student_id)) {
                            $error = "第 $row_count 行学生ID不能为空！";
                            break;
                        }
                        if (empty($plain_password)) {
                            $error = "第 $row_count 行密码不能为空！";
                            break;
                        }

                        // 对密码进行哈希处理
                        $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

                        // 绑定参数并执行插入操作（参数类型 s 表示字符串，强制以字符串存入数据库）
                        $stmt->bind_param("sssss", $student_id, $hashed_password, $class, $name, $gender);
                        $exec_result = $stmt->execute();
                        
                        if (!$exec_result) {
                            $error = "第 $row_count 行插入失败：" . $stmt->error;
                            break;
                        }
                    }

                    fclose($handle);
                    $stmt->close();

                    // 若没有错误，提示成功
                    if (empty($error)) {
                        $message = "学生账号导入成功，共处理 $row_count 行数据！";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN"> <!-- 改为中文编码更友好 -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>导入学生账号</title>
    <style>
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>导入学生账号</h1>
    <?php 
    if (isset($message) && !empty($message)) {
        echo "<p class='success'>$message</p>";
    }
    if (isset($error) && !empty($error)) {
        echo "<p class='error'>$error</p>";
    }
    ?>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
        <label for="excel_file">选择 Excel 文件 (CSV 格式):</label>
        <input type="file" id="excel_file" name="excel_file" accept=".csv" required><br>
        <input type="submit" value="导入">
    </form>
    <a href="admin_menu.php">返回管理员面板</a><br>
    <img src="img/importstu1.png"  width="800"/><br>
    <img src="img/importstu2.png"  width="800"/>
</body>
</html>