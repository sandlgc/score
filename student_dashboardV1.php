<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生面板</title>
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

        p {
            font-size: 1.2em;
            color: #555;
            margin-bottom: 20px;
        }

        h2 {
            font-size: 1.8em;
            color: #555;
            margin-bottom: 15px;
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
            display: inline-block;
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        a:hover {
            background-color: #c82333;
        }
    </style>
</head>

<body>
    <?php
    session_start();
    include 'config.php';

    if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "student") {
        header("Location: login.php");
        exit();
    }

    $student_id = $_SESSION["user_id"];

    // 查询学生积分和姓名
    $sql = "SELECT points, name FROM students WHERE student_id = '$student_id'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $points = $row["points"];
        $student_name = $row["name"];
    }

    // 查询积分明细
    $sql_logs = "SELECT action, points_change, created_at FROM point_logs WHERE student_id = '$student_id'";
    $result_logs = $conn->query($sql_logs);
    ?>
    <h1>欢迎，<?php echo $student_name; ?></h1>
    <p>当前积分: <?php echo $points; ?></p>
    <h2>积分明细</h2>
    <table>
        <tr>
            <th>操作</th>
            <th>积分变化</th>
            <th>时间</th>
        </tr>
        <?php
        if ($result_logs->num_rows > 0) {
            while ($row_logs = $result_logs->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row_logs["action"] . "</td>";
                echo "<td>" . $row_logs["points_change"] . "</td>";
                echo "<td>" . $row_logs["created_at"] . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='3'>暂无积分明细</td></tr>";
        }
        ?>
    </table>
    <a href="student_menu.php">返回学生菜单</a>
    <a href="logout.php">退出登录</a>
</body>

</html>