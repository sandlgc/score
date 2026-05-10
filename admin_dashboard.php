<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit();
}

// 每页显示的记录数
$limit = 10;

// 获取当前页码
if (isset($_GET["page"])) {
    $page = $_GET["page"];
} else {
    $page = 1;
}

// 计算偏移量
$offset = ($page - 1) * $limit;

// 查询学生总数
$total_students_sql = "SELECT COUNT(*) as total FROM students";
$total_students_result = $conn->query($total_students_sql);
$total_students_row = $total_students_result->fetch_assoc();
$total_students = $total_students_row['total'];

// 计算总页数
$total_pages = ceil($total_students / $limit);

// 查询当前页的学生信息
$sql_students = "SELECT student_id, class, name, gender, points FROM students LIMIT $offset, $limit";
$result_students = $conn->query($sql_students);

// 处理添加学生操作
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_student'])) {
    $new_student_id = $_POST['new_student_id'];
    $new_password = $_POST['new_password'];
    $new_class = $_POST['new_class'];
    $new_name = $_POST['new_name'];
    $new_gender = $_POST['new_gender'];

    // 检查学生 ID 是否已经存在
    $check_sql = "SELECT id FROM students WHERE student_id = '$new_student_id'";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows == 0) {
        // 如果不存在，则插入新记录
        $insert_sql = "INSERT INTO students (student_id, password, class, name, gender) VALUES ('$new_student_id', '$new_password', '$new_class', '$new_name', '$new_gender')";
        if ($conn->query($insert_sql) === TRUE) {
            $message = "学生添加成功";
        } else {
            $message = "学生添加失败: " . $conn->error;
        }
    } else {
        $message = "学生 ID 已存在，添加失败";
    }
}

// 处理删除学生操作
if (isset($_GET['delete_student'])) {
    $delete_student_id = $_GET['delete_student'];

    // 先删除积分明细记录
    $delete_logs_sql = "DELETE FROM point_logs WHERE student_id = '$delete_student_id'";
    $conn->query($delete_logs_sql);

    // 再删除学生记录
    $delete_student_sql = "DELETE FROM students WHERE student_id = '$delete_student_id'";
    if ($conn->query($delete_student_sql) === TRUE) {
        $message = "学生删除成功";
    } else {
        $message = "学生删除失败: " . $conn->error;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员面板</title>
</head>

<body>
    <h1>欢迎，管理员</h1>
    <?php if (isset($message)) {
        echo "<p style='color: green;'>$message</p>";
    } ?>
    <h2>学生积分管理</h2>
    <!-- 添加学生表单 -->
    <h3>添加学生</h3>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?page=$page"; ?>">
        <label for="new_student_id">学生 ID:</label>
        <input type="text" id="new_student_id" name="new_student_id" required><br>
        <label for="new_password">密码:</label>
        <input type="text" id="new_password" name="new_password" required><br>
        <label for="new_class">班别:</label>
        <input type="text" id="new_class" name="new_class" required><br>
        <label for="new_name">姓名:</label>
        <input type="text" id="new_name" name="new_name" required><br>
        <label for="new_gender">性别:</label>
        <select id="new_gender" name="new_gender" required>
            <option value="男">男</option>
            <option value="女">女</option>
        </select><br>
        <input type="submit" name="add_student" value="添加学生">
    </form>

    <table border="1">
        <tr>
            <th>学生 ID</th>
            <th>班别</th>
            <th>姓名</th>
            <th>性别</th>
            <th>积分</th>
            <th>积分变化</th>
            <th>操作理由</th>
            <th>操作</th>
            <th>删除</th>
        </tr>
        <?php
        if ($result_students->num_rows > 0) {
            while ($row_students = $result_students->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row_students["student_id"] . "</td>";
                echo "<td>" . $row_students["class"] . "</td>";
                echo "<td>" . $row_students["name"] . "</td>";
                echo "<td>" . $row_students["gender"] . "</td>";
                echo "<td>" . $row_students["points"] . "</td>";
                echo "<td><input type='number' name='points_change_" . $row_students["student_id"] . "'></td>";
                echo "<td><input type='text' name='action_" . $row_students["student_id"] . "'></td>";
                echo "<td>
                        <form method='post' action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "?page=$page'>
                            <input type='hidden' name='student_id' value='" . $row_students["student_id"] . "'>
                            <input type='hidden' name='points_change' value='' id='points_change_" . $row_students["student_id"] . "'>
                            <input type='hidden' name='action' value='' id='action_" . $row_students["student_id"] . "'>
                            <input type='button' value='更新积分' onclick='updatePoints(\"" . $row_students["student_id"] . "\")'>
                        </form>
                      </td>";
                echo "<td><a href='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "?page=$page&delete_student=" . $row_students["student_id"] . "'>删除</a></td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='9'>暂无学生信息</td></tr>";
        }
        ?>
    </table>
    <!-- 分页导航 -->
    <div>
        <?php
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $page) {
                echo "<span style='font-weight: bold;'>$i</span> ";
            } else {
                echo "<a href='admin_dashboard.php?page=$i'>$i</a> ";
            }
        }
        ?>
    </div>
    <a href="logout.php">退出登录</a>

    <script>
        function updatePoints(studentId) {
          var pointsChange = document.getElementsByName('points_change_' + studentId)[0].value;
          var action = document.getElementsByName('action_' + studentId)[0].value;
          if (pointsChange === '' || action === '') {
                alert('请输入积分变化和操作理由');
                return;
          }
          document.getElementById('points_change_' + studentId).value = pointsChange;
          document.getElementById('action_' + studentId).value = action;
          document.forms[document.forms.length - 1].submit();
}
    </script>
</body>

</html>