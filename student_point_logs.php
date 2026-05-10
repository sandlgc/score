<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生积分明细</title>
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
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
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
            background-color: #007BFF;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 20px;
            transition: background-color 0.3s ease;
        }

        a:hover {
            background-color: #0056b3;
        }

        button {
            background-color: #dc3545;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #c82333;
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

    $studentName = "";
    $queryParams = [];
    $logs = [];
    if (isset($_GET['student_id'])) {
        $studentId = $_GET['student_id'];

        // 查询学生姓名
        $nameSql = "SELECT name FROM students WHERE student_id = ?";
        $nameStmt = $conn->prepare($nameSql);
        $nameStmt->bind_param("s", $studentId);
        if (!$nameStmt->execute()) {
            echo "查询学生姓名时出错: " . $nameStmt->error;
        } else {
            $nameResult = $nameStmt->get_result();
            if ($nameResult->num_rows > 0) {
                $nameRow = $nameResult->fetch_assoc();
                $studentName = $nameRow['name'];
            }
        }
        $nameStmt->close();

        // 使用预处理语句防止 SQL 注入
        $sql = "SELECT id, action_time, action, points_change FROM point_logs WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $studentId);
        if (!$stmt->execute()) {
            echo "查询积分明细时出错: " . $stmt->error;
        } else {
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    // 确保输出时正确显示小数
                    $row['points_change'] = number_format((float)$row['points_change'], 2);
                    $logs[] = $row;
                }
            }
        }
        $stmt->close();

        // 记录除 student_id 外的查询参数
        foreach ($_GET as $key => $value) {
            if ($key!== 'student_id') {
                $queryParams[$key] = $value;
            }
        }
    }

    $queryString = http_build_query($queryParams);
    ?>
    <h1><?php echo $studentName; ?> 学生积分明细</h1>
    <table>
        <tr>
            <th>操作时间</th>
            <th>操作理由</th>
            <th>积分变化</th>
            <th>操作</th>
        </tr>
        <?php
        if (!empty($logs)) {
            foreach ($logs as $log) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($log['action_time']) . "</td>";
                echo "<td>" . htmlspecialchars($log['action']) . "</td>";
                echo "<td>" . htmlspecialchars($log['points_change']) . "</td>";
                echo "<td><button onclick='deleteLog(" . $log['id'] . ")'>删除</button></td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='4'>暂无积分明细记录</td></tr>";
        }
        ?>
    </table>
    <a href="manage_points.php?<?php echo $queryString; ?>">返回学生列表</a>

    <script>
        function deleteLog(logId) {
            if (confirm('确定要删除这条积分记录吗？')) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'delete_point_log.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    alert('积分记录删除成功');
                                    location.reload();
                                } else {
                                    alert('积分记录删除失败: ' + response.message);
                                }
                            } catch (error) {
                                alert('解析响应数据时出错: ' + error.message);
                            }
                        } else {
                            alert('AJAX 请求失败，状态码: ' + xhr.status);
                        }
                    }
                };

                var data = 'log_id=' + logId;
                xhr.send(data);
            }
        }
    </script>
</body>

</html>