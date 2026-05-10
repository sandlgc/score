<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            width: 300px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-bottom: 5px;
            color: #666;
        }

        input[type="text"],
        input[type="password"] {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #007BFF;
            outline: none;
        }

        input[type="submit"] {
            padding: 10px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        input[type="submit"]:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <?php
    session_start();
    include 'config.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $user_id = $_POST["user_id"];
        $password = $_POST["password"];

        // 检查是否为管理员
        $admin_sql = "SELECT * FROM admins WHERE admin_id = ?";
        $admin_stmt = $conn->prepare($admin_sql);
        $admin_stmt->bind_param("s", $user_id);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();

        if ($admin_result->num_rows > 0) {
            $admin_row = $admin_result->fetch_assoc();
            if (password_verify($password, $admin_row['password'])) {
                $_SESSION["user_id"] = $user_id;
                $_SESSION["user_type"] = "admin";
                header("Location: admin_menu.php");
                exit();
            }
        }

        // 检查是否为学生
        $student_sql = "SELECT * FROM students WHERE student_id = ?";
        $student_stmt = $conn->prepare($student_sql);
        $student_stmt->bind_param("s", $user_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();

        if ($student_result->num_rows > 0) {
            $student_row = $student_result->fetch_assoc();
            if (password_verify($password, $student_row['password'])) {
                $_SESSION["user_id"] = $user_id;
                $_SESSION["user_type"] = "student";
                header("Location: student_menu.php");
                exit();
            } else {
                $error = "学生密码错误";
            }
        } else {
            $error = "未找到该学生账号";
        }

        if (!isset($error)) {
            $error = "用户名或密码错误";
        }
    }
    ?>
    <div class="login-container">
        <h1>登录</h1>
        <?php if (isset($error)) {
            echo '<p class="error-message">' . $error . '</p>';
        } ?>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <label for="user_id">用户名:</label>
            <input type="text" id="user_id" name="user_id" required>
            <label for="password">密码:</label>
            <input type="password" id="password" name="password" required>
            <input type="submit" value="登录">
        </form>
    </div>
</body>

</html>