<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改学生信息</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        h1 {
            font-size: 2.5em;
            color: #007BFF;
            margin-bottom: 10px;
        }

        h2 {
            font-size: 1.8em;
            color: #555;
            margin-bottom: 20px;
        }

        form {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 400px;
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
        }

        input[type="text"],
        select {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        select:focus {
            border-color: #007BFF;
            outline: none;
        }

        input[type="submit"] {
            background-color: #007BFF;
            color: white;
            padding: 12px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-weight: 600;
        }

        input[type="submit"]:hover {
            background-color: #0056b3;
        }

        p {
            color: green;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }

        .links {
            display: flex;
            gap: 20px;
        }

        .links a {
            display: inline-block;
            background-color: #007BFF;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            transition: background-color 0.3s ease;
            font-weight: 600;
        }

        .links a:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <?php
    session_start();
    include 'config.php';

    if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
        header("Location: login.php");
        exit();
    }

    if (isset($_GET['student_id'])) {
        $student_id = $_GET['student_id'];

        // 查询学生信息
        $sql = "SELECT student_id, class, name, gender, password FROM students WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
        } else {
            echo "未找到该学生信息";
            exit();
        }
    } else {
        echo "未提供学生 ID";
        exit();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $new_class = $_POST['new_class'];
        $new_name = $_POST['new_name'];
        $new_gender = $_POST['new_gender'];
        $new_password = $_POST['new_password'];

        // 更新学生信息
        $update_sql = "UPDATE students SET class = ?, name = ?, gender = ?, password = ? WHERE student_id = ?";
        $stmt_update = $conn->prepare($update_sql);
        $stmt_update->bind_param("sssss", $new_class, $new_name, $new_gender, $new_password, $student_id);

        if ($stmt_update->execute()) {
            $message = "学生信息修改成功";
        } else {
            $message = "学生信息修改失败: " . $stmt_update->error;
        }
    }
    ?>
    <h1>欢迎，管理员</h1>
    <?php if (isset($message)) {
        echo "<p>$message</p>";
    } ?>
    <h2>修改学生信息</h2>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?student_id=" . $student_id; ?>">
        <label for="new_student_id">学生 ID:</label>
        <input type="text" id="new_student_id" name="new_student_id" value="<?php echo $row['student_id']; ?>" readonly><br>
        <label for="new_password">密码:</label>
        <input type="text" id="new_password" name="new_password" value="<?php echo $row['password']; ?>" required><br>
        <label for="new_class">班别:</label>
        <input type="text" id="new_class" name="new_class" value="<?php echo $row['class']; ?>" required><br>
        <label for="new_name">姓名:</label>
        <input type="text" id="new_name" name="new_name" value="<?php echo $row['name']; ?>" required><br>
        <label for="new_gender">性别:</label>
        <select id="new_gender" name="new_gender" required>
            <option value="男" <?php if ($row['gender'] == '男') echo 'selected'; ?>>男</option>
            <option value="女" <?php if ($row['gender'] == '女') echo 'selected'; ?>>女</option>
        </select><br>
        <input type="submit" value="修改学生信息">
    </form>
    <div class="links">
        <a href="manage_points.php">返回学生列表</a>
        <a href="logout.php">退出登录</a>
    </div>
</body>

</html>