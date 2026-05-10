<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生管理</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 20px;
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

        table {
            width: 100%;
            border-collapse: collapse;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
            margin-bottom: 20px;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #007BFF;
            color: white;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        a {
            color: #007BFF;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .pagination {
            margin-bottom: 20px;
        }

        .pagination span {
            font-weight: bold;
            margin-right: 5px;
        }

        .pagination a {
            margin-right: 5px;
        }

        .action-buttons a {
            display: inline-block;
            background-color: #007BFF;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            margin-right: 10px;
            transition: background-color 0.3s ease;
        }

        .action-buttons a:hover {
            background-color: #0056b3;
        }

        .search-form {
            margin-bottom: 20px;
        }

        .search-form label {
            margin-right: 10px;
        }

        .search-form select,
        .search-form input {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-right: 10px;
        }

        .search-form button {
            background-color: #007BFF;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .search-form button:hover {
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

    // 获取查询参数
    $searchClass = isset($_GET['class']) ? $_GET['class'] : '';
    $searchName = isset($_GET['name']) ? $_GET['name'] : '';
    $page = isset($_GET["page"]) ? $_GET["page"] : 1;

    // 每页显示的记录数
    $limit = 10;

    // 计算偏移量
    $offset = ($page - 1) * $limit;

    // 构建查询条件
    $whereClause = [];
    if (!empty($searchClass)) {
        $whereClause[] = "class = '$searchClass'";
    }
    if (!empty($searchName)) {
        $whereClause[] = "name LIKE '%$searchName%'";
    }
    $where = implode(" AND ", $whereClause);
    if (!empty($where)) {
        $where = "WHERE " . $where;
    }

    // 查询学生总数
    $total_students_sql = "SELECT COUNT(*) as total FROM students $where";
    $total_students_result = $conn->query($total_students_sql);
    $total_students_row = $total_students_result->fetch_assoc();
    $total_students = $total_students_row['total'];

    // 计算总页数
    $total_pages = ceil($total_students / $limit);

    // 查询当前页的学生信息
    $sql_students = "SELECT student_id, class, name, gender, points FROM students $where LIMIT $offset, $limit";
    $result_students = $conn->query($sql_students);

    // 查询所有班级
    $class_sql = "SELECT DISTINCT class FROM students";
    $class_result = $conn->query($class_sql);
    $classes = [];
    while ($class_row = $class_result->fetch_assoc()) {
        $classes[] = $class_row['class'];
    }
    ?>
    <h1>欢迎，管理员</h1>
    <h2>学生管理</h2>
    <form class="search-form" method="get">
        <label for="class">选择班别:</label>
        <select name="class">
            <option value="">全部</option>
            <?php
            foreach ($classes as $class) {
                $selected = ($class == $searchClass) ? 'selected' : '';
                echo "<option value='$class' $selected>$class</option>";
            }
            ?>
        </select>
        <label for="name">输入学生姓名:</label>
        <input type="text" name="name" value="<?php echo $searchName; ?>">
        <button type="submit">查询</button>
    </form>
    <table>
        <tr>
            <th>学生 ID</th>
            <th>班别</th>
            <th>姓名</th>
            <th>性别</th>
            <th>积分</th>
        </tr>
        <?php
        if ($result_students->num_rows > 0) {
            while ($row_students = $result_students->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row_students["student_id"] . "</td>";
                echo "<td>" . $row_students["class"] . "</td>";
                // 将学生姓名改为链接，点击后跳转到 edit_student.php 页面，并传递学生的 ID
                echo "<td><a href='edit_student.php?student_id=" . $row_students["student_id"] . "'>" . $row_students["name"] . "</a></td>";
                echo "<td>" . $row_students["gender"] . "</td>";
                echo "<td>" . $row_students["points"] . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='5'>暂无符合条件的学生信息</td></tr>";
        }
        ?>
    </table>
    <!-- 分页导航 -->
    <div class="pagination">
        <?php
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $page) {
                echo "<span>$i</span> ";
            } else {
                echo "<a href='manage_students.php?page=$i&class=$searchClass&name=$searchName'>$i</a> ";
            }
        }
        ?>
    </div>
    <div class="action-buttons">
        <a href="admin_menu.php">返回管理菜单</a>
        <a href="logout.php">退出登录</a>
    </div>
</body>

</html>