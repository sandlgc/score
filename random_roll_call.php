<?php
// 必须在所有输出之前启动会话（文件最顶部，无空格/换行）
session_start();
include 'config.php';

// 权限验证（在输出任何HTML前完成跳转）
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
    // 防止SQL注入（重要！）
    $selectedClass = $conn->real_escape_string($selectedClass);
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
            position: relative;
        }

        #result {
            font-size: 8em;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            min-height: 1.2em;
            position: relative;
            transition: all 0.3s ease;
        }

        /* 多样化的文字动画效果 */
        .name-rolling {
            animation: roll 0.1s infinite, colorShift 0.5s infinite alternate;
        }

        .name-selected {
            animation: bounce 1s ease-in-out, pulse 2s infinite, glow 1.5s infinite alternate;
        }

        .display-style-selector {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .display-style-selector select {
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        /* 动画定义 */
        @keyframes roll {
            0% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0); }
        }

        @keyframes colorShift {
            0% { color: #007BFF; }
            25% { color: #28a745; }
            50% { color: #ffc107; }
            75% { color: #dc3545; }
            100% { color: #6f42c1; }
        }

        @keyframes bounce {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes glow {
            0% { 
                text-shadow: 0 0 5px #fff, 0 0 10px #fff, 0 0 15px #007BFF, 0 0 20px #007BFF; 
            }
            100% { 
                text-shadow: 0 0 10px #fff, 0 0 20px #fff, 0 0 30px #007BFF, 0 0 40px #007BFF; 
            }
        }

        /* 不同展示风格 */
        .style-big {
            font-size: 10em !important;
            letter-spacing: 10px;
        }

        .style-funky {
            font-family: 'Comic Sans MS', cursive, sans-serif !important;
            transform: rotate(-2deg);
        }

        .style-elegant {
            font-family: 'Times New Roman', serif !important;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.2);
        }

        .style-neon {
            color: #00ffcc !important;
            text-shadow: 0 0 5px #00ffcc, 0 0 10px #00ffcc, 0 0 15px #00ffcc !important;
        }

        .style-retro {
            font-family: 'Courier New', monospace !important;
            letter-spacing: 5px;
            color: #ff6b6b;
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

        /* 胜利庆祝效果 */
        .celebration {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
            display: none;
        }

        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: #f00;
            opacity: 0.7;
            animation: fall 5s linear infinite;
        }

        @keyframes fall {
            0% {
                transform: translateY(-10px) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
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
        <!-- 展示风格选择器 -->
        <div class="display-style-selector">
            <label for="displayStyle">展示风格：</label>
            <select id="displayStyle">
                <option value="default">默认风格</option>
                <option value="big">超大字体</option>
                <option value="funky">趣味字体</option>
                <option value="elegant">优雅字体</option>
                <option value="neon">霓虹效果</option>
                <option value="retro">复古风格</option>
            </select>
        </div>
        
        <div id="result">
            <span>&nbsp;</span>
        </div>
        
        <!-- 庆祝效果容器 -->
        <div class="celebration" id="celebration"></div>
        
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
        const resultContainer = document.getElementById('result');
        const classSelect = document.getElementById('classSelect');
        const students = <?php echo json_encode($students); ?>;
        const displayStyleSelect = document.getElementById('displayStyle');
        const celebrationContainer = document.getElementById('celebration');
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

        // 创建庆祝效果
        function createCelebration() {
            celebrationContainer.style.display = 'block';
            celebrationContainer.innerHTML = '';
            
            // 创建彩色纸屑
            for (let i = 0; i < 100; i++) {
                const confetti = document.createElement('div');
                confetti.classList.add('confetti');
                
                // 随机颜色
                const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                
                // 随机大小
                const size = Math.random() * 10 + 5;
                confetti.style.width = `${size}px`;
                confetti.style.height = `${size}px`;
                
                // 随机位置
                confetti.style.left = `${Math.random() * 100}%`;
                confetti.style.top = '-10px';
                
                // 随机旋转和动画时长
                confetti.style.animationDuration = `${Math.random() * 3 + 2}s`;
                confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
                
                celebrationContainer.appendChild(confetti);
                
                // 移除纸屑
                setTimeout(() => {
                    confetti.remove();
                }, 5000);
            }
            
            // 5秒后隐藏庆祝效果
            setTimeout(() => {
                celebrationContainer.style.display = 'none';
            }, 5000);
        }

        // 切换展示风格
        function updateDisplayStyle() {
            // 移除所有风格类
            resultContainer.classList.remove('style-big', 'style-funky', 'style-elegant', 'style-neon', 'style-retro');
            
            // 添加选中的风格
            const selectedStyle = displayStyleSelect.value;
            if (selectedStyle !== 'default') {
                resultContainer.classList.add(`style-${selectedStyle}`);
            }
        }

        // 初始化展示风格
        displayStyleSelect.addEventListener('change', updateDisplayStyle);
        updateDisplayStyle();

        startButton.addEventListener('click', async function () {
            if (students.length === 0) {
                alert('请先选择有效的班别');
                return;
            }
            if (isRolling) {
                clearInterval(intervalId);
                isRolling = false;
                startButton.textContent = '开始点名';
                
                // 移除滚动动画，添加选中动画
                resultElement.classList.remove('name-rolling');
                resultElement.classList.add('name-selected');
                
                // 创建庆祝效果
                createCelebration();
                
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
                // 添加滚动动画，移除选中动画
                resultElement.classList.add('name-rolling');
                resultElement.classList.remove('name-selected');
                
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
                resultElement.classList.remove('name-rolling', 'name-selected');
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