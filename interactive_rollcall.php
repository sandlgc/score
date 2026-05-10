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
// 防止SQL注入
$selectedClass = $conn->real_escape_string($selectedClass);

$students = [];
if (!empty($selectedClass)) {
    $students_sql = "SELECT student_id, name FROM students WHERE class = '$selectedClass'";
    $students_result = $conn->query($students_sql);
    while ($student_row = $students_result->fetch_assoc()) {
        $students[] = [
            'id' => $student_row['student_id'],
            'name' => $student_row['name'],
            'score' => isset($_SESSION['student_scores'][$student_row['student_id']]) ? $_SESSION['student_scores'][$student_row['student_id']] : 0
        ];
    }
}

// 初始化学生积分
if (!isset($_SESSION['student_scores'])) {
    $_SESSION['student_scores'] = [];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>互动式随机点名系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Microsoft YaHei', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
            overflow-x: hidden;
        }
        
        /* 顶部导航 */
        .header {
            background: #2c3e50;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .class-selector {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            font-size: 16px;
        }
        
        /* 主容器 */
        .main-container {
            display: flex;
            padding: 20px;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* 左侧：互动区 */
        .interaction-panel {
            flex: 2;
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        /* 点名显示区 */
        .name-display {
            font-size: 8em;
            font-weight: bold;
            color: #2980b9;
            margin: 40px 0;
            text-align: center;
            min-height: 1.2em;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .rolling {
            animation: roll 0.1s infinite, colorShift 0.5s infinite alternate;
        }
        
        .selected {
            animation: bounce 1s ease-in-out, glow 1.5s infinite alternate;
        }
        
        /* 功能按钮区 */
        .control-buttons {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-success {
            background: #2ecc71;
            color: white;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* 互动答题区 */
        .quiz-section {
            width: 100%;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            display: none;
        }
        
        .quiz-section.active {
            display: block;
        }
        
        .question-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 18px;
            margin-bottom: 20px;
            resize: none;
            height: 100px;
        }
        
        .answer-options {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 20px 0;
        }
        
        .answer-btn {
            flex: 1;
            min-width: 120px;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }
        
        /* 右侧：互动数据区 */
        .stats-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stats-title {
            font-size: 20px;
            color: #2980b9;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e3f2fd;
        }
        
        .score-list {
            list-style: none;
        }
        
        .score-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .score-item:last-child {
            border-bottom: none;
        }
        
        .rank-number {
            display: inline-block;
            width: 25px;
            height: 25px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 25px;
            margin-right: 10px;
        }
        
        /* 抢答区 */
        .rush-answer {
            background: #ffecb3;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        
        .rush-btn {
            background: #ff5722;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        /* 动画效果 */
        @keyframes roll {
            0% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0); }
        }
        
        @keyframes colorShift {
            0% { color: #3498db; }
            25% { color: #2ecc71; }
            50% { color: #f39c12; }
            75% { color: #e74c3c; }
            100% { color: #9b59b6; }
        }
        
        @keyframes bounce {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @keyframes glow {
            0% { text-shadow: 0 0 10px #3498db; }
            100% { text-shadow: 0 0 20px #3498db, 0 0 30px #3498db; }
        }
        
        /* 响应式设计 */
        @media (max-width: 900px) {
            .main-container {
                flex-direction: column;
            }
            
            .name-display {
                font-size: 5em;
            }
        }
        
        /* 投票进度条 */
        .vote-progress {
            height: 10px;
            background: #eee;
            border-radius: 5px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .vote-bar {
            height: 100%;
            background: #3498db;
            border-radius: 5px;
            transition: width 0.5s ease;
            width: 0;
        }
        
        /* 音效控制 */
        .sound-control {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 10px;
            border-radius: 50%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            cursor: pointer;
        }
    </style>
    <!-- 引入图标库 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-random"></i> 互动式随机点名系统</h1>
        <form method="get" id="classForm">
            <select name="class" class="class-selector" onchange="this.form.submit()">
                <option value="">选择班级</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class; ?>" <?php echo $class == $selectedClass ? 'selected' : ''; ?>>
                        <?php echo $class; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    
    <div class="main-container">
        <!-- 左侧互动区 -->
        <div class="interaction-panel">
            <div class="name-display" id="nameDisplay">&nbsp;</div>
            
            <div class="control-buttons">
                <button class="btn btn-primary" id="startBtn">
                    <i class="fas fa-play"></i> 开始点名
                </button>
                <button class="btn btn-success" id="quizBtn">
                    <i class="fas fa-question-circle"></i> 发起答题
                </button>
                <button class="btn btn-warning" id="taskBtn">
                    <i class="fas fa-tasks"></i> 随机任务
                </button>
                <button class="btn btn-danger" id="resetBtn">
                    <i class="fas fa-redo"></i> 重置积分
                </button>
            </div>
            
            <!-- 抢答区 -->
            <div class="rush-answer">
                <h3><i class="fas fa-hand-paper"></i> 抢答区</h3>
                <p>点击下方按钮参与抢答！</p>
                <button class="rush-btn" id="rushAnswerBtn" onclick="rushAnswer()">
                    <i class="fas fa-bolt"></i> 我要抢答
                </button>
                <div id="rushResult"></div>
            </div>
            
            <!-- 答题互动区 -->
            <div class="quiz-section" id="quizSection">
                <h3>互动答题 <span id="currentStudentName"></span></h3>
                <textarea class="question-input" id="questionInput" placeholder="请输入问题..."></textarea>
                
                <div class="answer-options">
                    <button class="answer-btn" style="background: #2ecc71;" onclick="recordAnswer('correct')">
                        <i class="fas fa-check"></i> 回答正确
                    </button>
                    <button class="answer-btn" style="background: #f39c12;" onclick="recordAnswer('partial')">
                        <i class="fas fa-half-star"></i> 部分正确
                    </button>
                    <button class="answer-btn" style="background: #e74c3c;" onclick="recordAnswer('wrong')">
                        <i class="fas fa-times"></i> 回答错误
                    </button>
                </div>
                
                <div>
                    <h4>全班投票评分</h4>
                    <div class="vote-progress">
                        <div class="vote-bar" id="voteBar"></div>
                    </div>
                    <div class="answer-options">
                        <button class="answer-btn" style="background: #3498db;" onclick="vote(1)">
                            <i class="fas fa-thumbs-up"></i> 很棒
                        </button>
                        <button class="answer-btn" style="background: #95a5a6;" onclick="vote(0)">
                            <i class="fas fa-thumbs-down"></i> 还需努力
                        </button>
                    </div>
                    <p id="voteCount">投票数：0</p>
                </div>
            </div>
        </div>
        
        <!-- 右侧数据区 -->
        <div class="stats-panel">
            <div class="stats-card">
                <div class="stats-title"><i class="fas fa-trophy"></i> 积分排行榜</div>
                <ul class="score-list" id="scoreList">
                    <?php if (!empty($students)): ?>
                        <?php 
                        // 按积分排序
                        usort($students, function($a, $b) {
                            return $b['score'] - $a['score'];
                        });
                        $rank = 1;
                        foreach (array_slice($students, 0, 10) as $student): 
                        ?>
                            <li class="score-item">
                                <span>
                                    <span class="rank-number"><?php echo $rank++; ?></span>
                                    <?php echo $student['name']; ?>
                                </span>
                                <span><?php echo $student['score']; ?> 分</span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="score-item">请选择班级查看积分</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="stats-card">
                <div class="stats-title"><i class="fas fa-history"></i> 点名记录</div>
                <ul class="score-list" id="callHistory">
                    <li class="score-item">暂无记录</li>
                </ul>
            </div>
            
            <div class="stats-card">
                <div class="stats-title"><i class="fas fa-tasks"></i> 随机任务库</div>
                <div id="taskList">
                    <div class="score-item">1. 朗读课文段落</div>
                    <div class="score-item">2. 解答一道练习题</div>
                    <div class="score-item">3. 分享学习心得</div>
                    <div class="score-item">4. 带领全班朗读</div>
                    <div class="score-item">5. 讲解解题思路</div>
                    <button class="btn btn-primary" style="width:100%; margin-top:10px;" onclick="addTask()">
                        <i class="fas fa-plus"></i> 添加自定义任务
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 音效控制 -->
    <div class="sound-control" id="soundControl" onclick="toggleSound()">
        <i class="fas fa-volume-up" id="soundIcon"></i>
    </div>

    <script>
        // 全局变量
        const students = <?php echo json_encode($students); ?>;
        let isRolling = false;
        let rollInterval;
        let currentStudent = null;
        let soundEnabled = true;
        let voteCount = 0;
        let voteScore = 0;
        let callHistory = [];
        
        // DOM 元素
        const nameDisplay = document.getElementById('nameDisplay');
        const startBtn = document.getElementById('startBtn');
        const quizBtn = document.getElementById('quizBtn');
        const taskBtn = document.getElementById('taskBtn');
        const resetBtn = document.getElementById('resetBtn');
        const quizSection = document.getElementById('quizSection');
        const currentStudentName = document.getElementById('currentStudentName');
        const voteBar = document.getElementById('voteBar');
        const voteCountEl = document.getElementById('voteCount');
        const scoreList = document.getElementById('scoreList');
        const callHistoryEl = document.getElementById('callHistory');
        const rushResult = document.getElementById('rushResult');
        
        // 音效
        const sounds = {
            roll: new Audio('https://assets.mixkit.co/sfx/preview/mixkit-fast-small-sweep-transition-166.mp3'),
            select: new Audio('https://assets.mixkit.co/sfx/preview/mixkit-achievement-bell-600.mp3'),
            correct: new Audio('https://assets.mixkit.co/sfx/preview/mixkit-positive-interface-beep-221.mp3'),
            wrong: new Audio('https://assets.mixkit.co/sfx/preview/mixkit-negative-click-1104.mp3'),
            rush: new Audio('https://assets.mixkit.co/sfx/preview/mixkit-alarm-digital-clock-beep-989.mp3')
        };
        
        // 播放音效
        function playSound(type) {
            if (soundEnabled && sounds[type]) {
                sounds[type].currentTime = 0;
                sounds[type].play();
            }
        }
        
        // 切换音效
        function toggleSound() {
            soundEnabled = !soundEnabled;
            const icon = document.getElementById('soundIcon');
            icon.className = soundEnabled ? 'fas fa-volume-up' : 'fas fa-volume-mute';
        }
        
        // 开始/停止点名
        startBtn.addEventListener('click', function() {
            if (students.length === 0) {
                alert('请先选择班级！');
                return;
            }
            
            if (isRolling) {
                // 停止点名
                clearInterval(rollInterval);
                isRolling = false;
                startBtn.innerHTML = '<i class="fas fa-play"></i> 开始点名';
                nameDisplay.classList.remove('rolling');
                nameDisplay.classList.add('selected');
                playSound('select');
                
                // 记录点名历史
                if (currentStudent) {
                    callHistory.unshift({
                        name: currentStudent.name,
                        time: new Date().toLocaleTimeString()
                    });
                    updateCallHistory();
                }
                
            } else {
                // 开始点名
                isRolling = true;
                startBtn.innerHTML = '<i class="fas fa-stop"></i> 停止点名';
                nameDisplay.classList.remove('selected');
                nameDisplay.classList.add('rolling');
                playSound('roll');
                
                // 随机滚动名字
                rollInterval = setInterval(() => {
                    const randomIndex = Math.floor(Math.random() * students.length);
                    currentStudent = students[randomIndex];
                    nameDisplay.textContent = currentStudent.name;
                }, 100);
            }
        });
        
        // 发起答题
        quizBtn.addEventListener('click', function() {
            if (!currentStudent) {
                alert('请先点名选择学生！');
                return;
            }
            
            quizSection.classList.toggle('active');
            currentStudentName.textContent = `（${currentStudent.name}）`;
            voteCount = 0;
            voteScore = 0;
            updateVoteDisplay();
        });
        
        // 记录答题结果
        function recordAnswer(type) {
            if (!currentStudent) return;
            
            let score = 0;
            switch(type) {
                case 'correct':
                    score = 5;
                    playSound('correct');
                    alert(`${currentStudent.name} 回答正确！获得 ${score} 积分`);
                    break;
                case 'partial':
                    score = 2;
                    playSound('correct');
                    alert(`${currentStudent.name} 回答部分正确！获得 ${score} 积分`);
                    break;
                case 'wrong':
                    score = 0;
                    playSound('wrong');
                    alert(`${currentStudent.name} 回答错误！继续加油`);
                    break;
            }
            
            // 更新积分
            updateScore(currentStudent.id, score);
            quizSection.classList.remove('active');
        }
        
        // 投票功能
        function vote(isPositive) {
            voteCount++;
            voteScore += isPositive ? 1 : -1;
            updateVoteDisplay();
        }
        
        // 更新投票显示
        function updateVoteDisplay() {
            const total = Math.max(1, voteCount);
            const percentage = ((voteScore + voteCount) / (2 * total)) * 100;
            voteBar.style.width = `${percentage}%`;
            voteCountEl.textContent = `投票数：${voteCount}`;
            
            // 根据投票结果变色
            if (percentage > 70) voteBar.style.background = '#2ecc71';
            else if (percentage < 30) voteBar.style.background = '#e74c3c';
            else voteBar.style.background = '#3498db';
        }
        
        // 随机任务
        taskBtn.addEventListener('click', function() {
            if (!currentStudent) {
                alert('请先点名选择学生！');
                return;
            }
            
            const tasks = [
                '朗读课文第' + (Math.floor(Math.random() * 20) + 1) + '段',
                '解答练习册第' + (Math.floor(Math.random() * 30) + 1) + '题',
                '分享一个学习小技巧',
                '带领全班朗读今天的重点内容',
                '讲解刚才这道题的解题思路',
                '分享今天的学习收获',
                '出一道题目考全班同学',
                '总结本节课的知识点'
            ];
            
            const randomTask = tasks[Math.floor(Math.random() * tasks.length)];
            alert(`${currentStudent.name} 的随机任务：\n\n${randomTask}`);
        });
        
        // 抢答功能
        function rushAnswer() {
            playSound('rush');
            const randomTime = Math.floor(Math.random() * 3000) + 1000; // 1-4秒
            rushResult.innerHTML = '<i class="fas fa-hourglass-half"></i> 抢答倒计时...';
            
            setTimeout(() => {
                const randomStudent = students[Math.floor(Math.random() * students.length)];
                rushResult.innerHTML = `<strong>🎉 抢答成功！</strong><br>${randomStudent.name} 获得抢答机会！`;
                
                // 更新抢答者积分
                updateScore(randomStudent.id, 1);
            }, randomTime);
        }
        
        // 添加自定义任务
        function addTask() {
            const task = prompt('请输入自定义任务：');
            if (task) {
                const taskList = document.getElementById('taskList');
                const newTask = document.createElement('div');
                newTask.className = 'score-item';
                newTask.textContent = (taskList.children.length) + '. ' + task;
                taskList.insertBefore(newTask, taskList.lastElementChild);
            }
        }
        
        // 更新学生积分
        function updateScore(studentId, points) {
            // 找到学生
            const student = students.find(s => s.id === studentId);
            if (student) {
                student.score += points;
                
                // 更新本地存储
                <?php $_SESSION['student_scores'][studentId] = student.score; ?>
                
                // 更新排行榜
                updateScoreList();
            }
        }
        
        // 更新积分排行榜
        function updateScoreList() {
            // 排序
            students.sort((a, b) => b.score - a.score);
            
            // 更新UI
            scoreList.innerHTML = '';
            students.slice(0, 10).forEach((student, index) => {
                const li = document.createElement('li');
                li.className = 'score-item';
                li.innerHTML = `
                    <span>
                        <span class="rank-number">${index + 1}</span>
                        ${student.name}
                    </span>
                    <span>${student.score} 分</span>
                `;
                scoreList.appendChild(li);
            });
        }
        
        // 更新点名记录
        function updateCallHistory() {
            callHistoryEl.innerHTML = '';
            callHistory.slice(0, 5).forEach((item, index) => {
                const li = document.createElement('li');
                li.className = 'score-item';
                li.innerHTML = `
                    <span>${item.name}</span>
                    <span>${item.time}</span>
                `;
                callHistoryEl.appendChild(li);
            });
        }
        
        // 重置积分
        resetBtn.addEventListener('click', function() {
            if (confirm('确定要重置所有学生的积分吗？')) {
                students.forEach(student => {
                    student.score = 0;
                    <?php unset($_SESSION['student_scores']); ?>
                });
                updateScoreList();
            }
        });
        
        // 初始化
        updateScoreList();
    </script>
</body>
</html>