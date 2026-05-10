<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>随机点名</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e6f7ff 0%, #f4f4f9 100%);
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
        }

        /* 左侧操作区域 */
        .left-container {
            width: 200px;
            padding: 20px;
            border-right: 1px solid #ccc;
            display: flex;
            flex-direction: column;
            gap: 20px;
            background-color: rgba(255, 255, 255, 0.8);
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }

        .left-container select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            width: 200px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .left-container a {
            background-color: #007BFF;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            text-align: center;
            width: 150px;
            transition: background-color 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .left-container a:hover {
            background-color: #0056b3;
            transform: scale(1.05);
        }

        /* 中间的点名区域 */
        .roll-call-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-grow: 1;
            padding: 20px;
        }

        #result {
            font-size: 8em;
            color: #007BFF;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            min-height: 1.2em;
        }

        button {
            background-color: #007BFF;
            color: white;
            padding: 20px 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 24px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            min-width: 200px;
            margin: 10px; /* 设置按钮的外边距 */
        }

        button:hover {
            background-color: #0056b3;
            transform: scale(1.05);
        }

        /* 右侧统计区域 */
        .statistics {
            width: 200px;
            padding: 20px;
            border-left: 1px solid #ccc;
            display: flex;
            flex-direction: column;
            gap: 20px;
            background-color: rgba(255, 255, 255, 0.8);
            box-shadow: -2px 0 5px rgba(0, 0, 0, 0.1);
        }

        .statistics h2 {
            font-size: 1.5em;
            color: #007BFF;
            margin-bottom: 10px;
            border-bottom: 1px solid #007BFF;
            padding-bottom: 5px;
        }

        .statistics table {
            width: 100%;
            border-collapse: collapse;
        }

        .statistics th,
        .statistics td {
            padding: 8px 12px;
            text-align: center; /* 让表格内容居中 */
            border-bottom: 1px solid #ccc;
        }

        .statistics th {
            background-color: #e0f0ff;
        }

        .statistics tr:hover {
            background-color: #e1f2ff;
        }

        /* 新增按钮容器样式 */
        .answer-buttons-container {
            display: flex;
            justify-content: center;
            gap: 20px; /* 设置按钮之间的间距 */
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

    // 查询所有班级
    $class_sql = "SELECT DISTINCT class FROM students";
    $class_result = $conn->query($class_sql);
    $classes = [];
    while ($class_row = $class_result->fetch_assoc()) {
        $classes[] = $class_row['class'];
    }

    $selectedClass = isset($_GET['class']) ? $_GET['class'] : '';
    $students = [];
    if (!empty($selectedClass)) {
        // 根据选择的班级查询该班级的所有学生，同时获取学生 ID
        $students_sql = "SELECT student_id, name FROM students WHERE class = '$selectedClass'";
        $students_result = $conn->query($students_sql);
        while ($student_row = $students_result->fetch_assoc()) {
            $students[] = [
                'id' => $student_row['student_id'],
                'name' => $student_row['name']
            ];
        }
    }
    ?>
    <!-- 左侧操作区域 -->
    <div class="left-container">
        <form method="get" id="rollCallForm">
            <select name="class" id="classSelect">
                <option value="">请选择班别</option>
                <?php
                foreach ($classes as $class) {
                    $selected = ($class == $selectedClass) ? 'selected' : '';
                    echo "<option value='$class' $selected>$class</option>";
                }
                ?>
            </select>
        </form>
        <a href="admin_menu.php">返回管理菜单</a>
        <a href="logout.php">退出登录</a>
    </div>
    <!-- 中间的点名区域 -->
    <div class="roll-call-container">
        <div id="result">
            <span>&nbsp;</span>
        </div>
        <button type="button" id="startButton">开始点名</button>
        <!-- 新增回答按钮容器 -->
        <div class="answer-buttons-container">
            <button type="button" id="answerCorrect" style="visibility: hidden;">回答正确</button>
            <button type="button" id="answerWrong" style="visibility: hidden;">回答错误</button>
        </div>
    </div>
    <!-- 右侧统计区域 -->
    <div class="statistics">
        <h2>过去45分钟幸运排行榜</h2>
        <table id="top5ThisClassTable">
            <thead>
                <tr>
                    <th>排名</th>
                    <th>姓名</th>
                    <th>次数</th>
                </tr>
            </thead>
            <tbody>
                <!-- 这里将动态填充内容 -->
            </tbody>
        </table>
        <h2>回答正确名单</h2>
        <table id="tempCorrectTable">
            <thead>
                <tr>
                    <th>排名</th>
                    <th>姓名</th>
                </tr>
            </thead>
            <tbody>
                <!-- 这里将动态填充内容 -->
            </tbody>
        </table>
        <h2>幸运排行总榜</h2>
        <table id="top10TotalTable">
            <thead>
                <tr>
                    <th>排名</th>
                    <th>姓名</th>
                    <th>次数</th>
                </tr>
            </thead>
            <tbody>
                <!-- 这里将动态填充内容 -->
            </tbody>
        </table>

    </div>

    <script>
        const startButton = document.getElementById('startButton');
        const resultElement = document.getElementById('result').querySelector('span');
        const classSelect = document.getElementById('classSelect');
        const students = <?php echo json_encode($students); ?>;
        let intervalId;
        let isRolling = false;
        const top5ThisClassTable = document.getElementById('top5ThisClassTable').querySelector('tbody');
        const top10TotalTable = document.getElementById('top10TotalTable').querySelector('tbody');
        const answerCorrectButton = document.getElementById('answerCorrect');
        const answerWrongButton = document.getElementById('answerWrong');
        const tempCorrectTable = document.getElementById('tempCorrectTable').querySelector('tbody');

        // 从 localStorage 中获取回答正确的学生 ID 列表和时间戳
        let correctStudents = JSON.parse(localStorage.getItem('correctStudents')) || [];
        let correctStudentsTimeStamp = JSON.parse(localStorage.getItem('correctStudentsTimeStamp')) || null;

        // 检查是否超过45分钟
        if (correctStudentsTimeStamp) {
            const now = new Date().getTime();
            const elapsedTime = (now - correctStudentsTimeStamp) / (1000 * 60);
            if (elapsedTime > 45) {
                correctStudents = [];
                localStorage.removeItem('correctStudents');
                localStorage.removeItem('correctStudentsTimeStamp');
            }
        }

        // 定义函数用于更新统计列表
        async function updateStatistics() {
            const selectedClass = new URLSearchParams(window.location.search).get('class');
            try {
                const response = await fetch(`get_statistics.php?class=${selectedClass}`);
                if (!response.ok) {
                    throw new Error('网络响应失败');
                }
                const data = await response.json();

                // 清空表格内容
                top5ThisClassTable.innerHTML = '';
                top10TotalTable.innerHTML = '';
                tempCorrectTable.innerHTML = '';

                // 填充本节课幸运排行榜（前 5 名）
                const top5Rows = data.top5ThisClass.map((student, index) => {
                    const row = document.createElement('tr');
                    const rankCell = document.createElement('td');
                    rankCell.textContent = index + 1;
                    const nameCell = document.createElement('td');
                    nameCell.textContent = student.name;
                    const countCell = document.createElement('td');
                    countCell.textContent = student.count;
                    row.appendChild(rankCell);
                    row.appendChild(nameCell);
                    row.appendChild(countCell);
                    return row;
                });
                top5Rows.forEach(row => top5ThisClassTable.appendChild(row));

                // 填充幸运排行总榜（前 10 名）
                const top10Rows = data.top10Total.map((student, index) => {
                    const row = document.createElement('tr');
                    const rankCell = document.createElement('td');
                    rankCell.textContent = index + 1;
                    const nameCell = document.createElement('td');
                    nameCell.textContent = student.name;
                    const countCell = document.createElement('td');
                    countCell.textContent = student.count;
                    row.appendChild(rankCell);
                    row.appendChild(nameCell);
                    row.appendChild(countCell);
                    return row;
                });
                top10Rows.forEach(row => top10TotalTable.appendChild(row));

                // 填充临时回答正确名单
                const tempCorrectRows = correctStudents.slice(-3).map((studentId, index) => {
                    const student = students.find(s => s.id === studentId);
                    if (student) {
                        const row = document.createElement('tr');
                        const rankCell = document.createElement('td');
                        rankCell.textContent = index + 1;
                        const nameCell = document.createElement('td');
                        nameCell.textContent = student.name;
                        row.appendChild(rankCell);
                        row.appendChild(nameCell);
                        return row;
                    }
                    return null;
                }).filter(row => row !== null);
                tempCorrectRows.forEach(row => tempCorrectTable.appendChild(row));
            } catch (error) {
                console.error('更新统计信息时出错:', error);
            }
        }

        startButton.addEventListener('click', async function () {
            if (students.length === 0) {
                alert('请先选择有效的班别');
                return;
            }
            if (isRolling) {
                clearInterval(intervalId);
                isRolling = false;
                startButton.textContent = '开始点名';
                // 显示回答正确和错误按钮
                answerCorrectButton.style.visibility = 'visible';
                answerWrongButton.style.visibility = 'visible';
                // 插入点名记录
                const selectedStudent = JSON.parse(resultElement.dataset.student);
                if (selectedStudent) {
                    try {
                        const response = await fetch('record_roll_call.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `class=<?php echo $selectedClass; ?>&name=${selectedStudent.name}&student_id=${selectedStudent.id}`
                        });
                        if (!response.ok) {
                            throw new Error('保存点名记录失败');
                        }
                        // 局部刷新统计信息
                        await updateStatistics();
                    } catch (error) {
                        alert('保存点名记录失败：' + error.message);
                    }
                }
            } else {
                intervalId = setInterval(() => {
                    // 调整学生的抽中概率
                    const probabilities = students.map(student => {
                        return correctStudents.includes(student.id) ? 0.1 : 1;
                    });
                    const totalProbability = probabilities.reduce((sum, prob) => sum + prob, 0);
                    const normalizedProbabilities = probabilities.map(prob => prob / totalProbability);

                    const randomValue = Math.random();
                    let cumulativeProbability = 0;
                    let selectedIndex = -1;
                    for (let i = 0; i < normalizedProbabilities.length; i++) {
                        cumulativeProbability += normalizedProbabilities[i];
                        if (randomValue < cumulativeProbability) {
                            selectedIndex = i;
                            break;
                        }
                    }
                    const randomStudent = students[selectedIndex];
                    resultElement.textContent = randomStudent.name;
                    resultElement.dataset.student = JSON.stringify(randomStudent);
                }, 50);
                isRolling = true;
                startButton.textContent = '停止点名';
                // 隐藏回答正确和错误按钮
                answerCorrectButton.style.visibility = 'hidden';
                answerWrongButton.style.visibility = 'hidden';
            }
        });

        classSelect.addEventListener('change', function () {
            if (isRolling) {
                clearInterval(intervalId);
                isRolling = false;
                startButton.textContent = '开始点名';
                resultElement.textContent = '&nbsp;'; // 切换班级时清空名字显示
            }
            // 隐藏回答正确和错误按钮
            answerCorrectButton.style.visibility = 'hidden';
            answerWrongButton.style.visibility = 'hidden';
            document.getElementById('rollCallForm').submit();
        });

        // 回答正确按钮点击事件
        answerCorrectButton.addEventListener('click', async function () {
            const selectedStudent = JSON.parse(resultElement.dataset.student);
            if (selectedStudent) {
                try {
                    const response = await fetch('record_points_change.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `student_id=${selectedStudent.id}&points_change=1&action=回答问题`
                    });
                    if (!response.ok) {
                        throw new Error('保存积分记录失败');
                    }
                    alert('积分记录保存成功');
                    // 将回答正确的学生 ID 保存到 localStorage
                    if (!correctStudents.includes(selectedStudent.id)) {
                        if (correctStudents.length >= 3) {
                            correctStudents.shift();
                        }
                        correctStudents.push(selectedStudent.id);
                        localStorage.setItem('correctStudents', JSON.stringify(correctStudents));
                        localStorage.setItem('correctStudentsTimeStamp', new Date().getTime());
                    }
                    // 隐藏回答正确和错误按钮
                    answerCorrectButton.style.visibility = 'hidden';
                    answerWrongButton.style.visibility = 'hidden';
                    // 局部刷新统计信息
                    await updateStatistics();
                } catch (error) {
                    alert('保存积分记录失败：' + error.message);
                }
            }
        });

        // 回答错误按钮点击事件
        answerWrongButton.addEventListener('click', async function () {
            const selectedStudent = JSON.parse(resultElement.dataset.student);
            if (selectedStudent) {
                try {
                    const response = await fetch('record_points_change.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `student_id=${selectedStudent.id}&points_change=0.5&action=回答问题`
                    });
                    if (!response.ok) {
                        throw new Error('保存积分记录失败');
                    }
                    alert('积分记录保存成功');
                    // 隐藏回答正确和错误按钮
                    answerCorrectButton.style.visibility = 'hidden';
                    answerWrongButton.style.visibility = 'hidden';
                    // 局部刷新统计信息
                    await updateStatistics();
                } catch (error) {
                    alert('保存积分记录失败：' + error.message);
                }
            }
        });

        // 页面加载时初始化统计信息
        updateStatistics();
    </script>
</body>

</html>    