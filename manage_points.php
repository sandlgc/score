<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生积分管理</title>
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

        .student-name a {
            color: #007BFF;
            text-decoration: underline;
            cursor: pointer;
        }

        .student-name a:hover {
            color: #0056b3;
        }

        input[type="number"],
        input[type="text"],
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            margin-bottom: 10px;
        }

        button {
            background-color: #007BFF;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #0056b3;
        }

        .pagination {
            margin-bottom: 20px;
        }

        .pagination span {
            font-weight: bold;
            margin-right: 5px;
        }

        .pagination a {
            color: #007BFF;
            margin-right: 5px;
            text-decoration: none;
        }

        .pagination a:hover {
            text-decoration: underline;
        }

        a[href="admin_menu.php"],
        a[href="logout.php"] {
            display: inline-block;
            background-color: #007BFF;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            margin-right: 10px;
            transition: background-color 0.3s ease;
        }

        a[href="admin_menu.php"]:hover,
        a[href="logout.php"]:hover {
            background-color: #0056b3;
        }

        .search-form {
            margin-bottom: 20px;
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

    // 获取查询参数，将 student_id 改为 search_id
    $searchClass = isset($_GET['class']) ? $_GET['class'] : '';
    $searchId = isset($_GET['search_id']) ? $_GET['search_id'] : '';
    $searchName = isset($_GET['name']) ? $_GET['name'] : '';
    $page = isset($_GET['page']) ? $_GET['page'] : 1;

    // 每页显示的记录数
    $limit = 10;

    // 计算偏移量
    $offset = ($page - 1) * $limit;

    // 构建查询条件
    $whereClause = [];
    if (!empty($searchClass)) {
        $whereClause[] = "class = '$searchClass'";
    }
    if (!empty($searchId)) {
        $whereClause[] = "student_id LIKE '%$searchId%'";
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

    // 构建当前页面的查询参数，用于传递给 student_point_logs.php，将 student_id 改为 search_id
    $currentParams = http_build_query([
        'page' => $page,
        'class' => $searchClass,
        'search_id' => $searchId,
        'name' => $searchName
    ]);
    ?>
    <h1>欢迎，管理员</h1>
    <h2>学生积分管理</h2>
    <form class="search-form" method="get">
        <label for="class">选择班级:</label>
        <select name="class">
            <option value="">全部</option>
            <?php
            foreach ($classes as $class) {
                $selected = ($class == $searchClass) ? 'selected' : '';
                echo "<option value='$class' $selected>$class</option>";
            }
            ?>
        </select>
        <!-- 将 name 属性从 student_id 改为 search_id -->
        <label for="search_id">输入学号:</label>
        <input type="text" name="search_id" value="<?php echo $searchId; ?>">
        <label for="name">输入姓名:</label>
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
            <th>积分变化</th>
            <th>操作理由</th>
            <th>操作</th>
        </tr>
        <?php
        if ($result_students->num_rows > 0) {
            while ($row_students = $result_students->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row_students["student_id"] . "</td>";
                echo "<td>" . $row_students["class"] . "</td>";
                // 修改为链接，点击跳转到新页面，并传递当前页面的查询参数
                echo "<td class='student-name'><a href='student_point_logs.php?student_id=" . $row_students["student_id"] . "&$currentParams'>" . $row_students["name"] . "</a></td>";
                echo "<td>" . $row_students["gender"] . "</td>";
                echo "<td><span id='points_" . $row_students["student_id"] . "'>" . $row_students["points"] . "</span></td>";
                echo "<td><input type='number' id='points_change_" . $row_students["student_id"] . "'></td>";
                echo "<td><input type='text' id='action_" . $row_students["student_id"] . "'></td>";
                echo "<td>
                        <button onclick='updatePoints(\"" . $row_students["student_id"] . "\")'>更新积分</button>
                      </td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='8'>暂无符合条件的学生信息</td></tr>";
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
                // 将 student_id 改为 search_id
                echo "<a href='manage_points.php?page=$i&class=$searchClass&search_id=$searchId&name=$searchName'>$i</a> ";
            }
        }
        ?>
    </div>
    <a href="admin_menu.php">返回管理菜单</a>
    <a href="logout.php">退出登录</a>

    <script>
        function updatePoints(studentId) {
            var pointsChange = document.getElementById('points_change_' + studentId).value;
            var action = document.getElementById('action_' + studentId).value;

            if (pointsChange === '' || action === '') {
                alert('请输入积分变化和操作理由');
                return;
            }

            // 创建 AJAX 请求
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'update_points_ajax.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // 更新页面上的积分显示
                                var pointsElement = document.getElementById('points_' + studentId);
                                if (pointsElement) {
                                    pointsElement.innerHTML = response.new_points;
                                } else {
                                    alert('未找到积分显示元素');
                                }
                                // 清空积分变化和操作理由输入框
                                document.getElementById('points_change_' + studentId).value = '';
                                document.getElementById('action_' + studentId).value = '';
                                alert('积分更新成功');
                            } else {
                                alert('积分更新失败: ' + response.message);
                            }
                        } catch (error) {
                            alert('解析响应数据时出错: ' + error.message);
                        }
                    } else {
                        alert('AJAX 请求失败，状态码: ' + xhr.status);
                    }
                }
            };

            // 发送请求数据
            var data = 'student_id=' + studentId + '&points_change=' + pointsChange + '&action=' + action;
            xhr.send(data);
        }
    </script>
</body>

</html>