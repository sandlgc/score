<?php
session_start();
include 'config.php';

// 禁用浏览器缓存
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 检查是否选择了章节
if (!isset($_SESSION['selected_chapter'])) {
    header("Location: select_chapter.php");
    exit();
}

// 检查页面加载标志
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_SESSION['page_loaded'])) {
        // 如果标志存在，说明是非法刷新，重置所有数据
        unset($_SESSION['score']);
        unset($_SESSION['current_question_index']);
        unset($_SESSION['consecutive_correct']);
        unset($_SESSION['consecutive_correct_max']);
        unset($_SESSION['correct_count']);
        unset($_SESSION['wrong_question_ids']);
        unset($_SESSION['total_answer_start_time']);
        unset($_SESSION['answer_stats']);
        unset($_SESSION['page_loaded']);
        
        echo "答题期间不允许刷新，请认真答题！";
        header("Location: select_chapter.php?chapter=" . $_SESSION['selected_chapter']);
        exit();
    } else {
        // 设置页面加载标志，表示页面已正常加载
        $_SESSION['page_loaded'] = true;
    }
}

// 处理从表单提交过来的数据
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取POST数据
    $countdownTimeLeft = isset($_POST['countdownTimeLeft']) ? $_POST['countdownTimeLeft'] : null;
    $correctRate = isset($_POST['correctRate']) ? floatval($_POST['correctRate']) : 0;
    $answerTime = isset($_POST['answerTime']) ? intval($_POST['answerTime']) : 0;
    
    // 保存倒计时时间
    if ($countdownTimeLeft !== null) {
        $_SESSION['countdownTimeLeft'] = $countdownTimeLeft;
    }
    
    // 记录答题统计信息
    if (!isset($_SESSION['answer_stats'])) {
        $_SESSION['answer_stats'] = [];
    }
    $_SESSION['answer_stats'][] = [
        'question_index' => $_SESSION['current_question_index'],
        'correct_rate' => $correctRate,
        'answer_time' => $answerTime
    ];
    
    // 清除页面加载标志，表示页面已完成加载
    unset($_SESSION['page_loaded']);
    
    // 更新当前题目索引到下一题
    $_SESSION['current_question_index']++;
}

// 若章节变更，重置分数和答题状态
if (isset($_GET['chapter']) && $_GET['chapter'] != $_SESSION['selected_chapter']) {
    $_SESSION['selected_chapter'] = $_GET['chapter'];
    $_SESSION['score'] = 0;
    // 当章节变更时，重新打乱题目顺序并重置索引
    if (isset($_SESSION['questions'])) {
        shuffle($_SESSION['questions']);
        $_SESSION['current_question_index'] = 0;
    }
    // 重置连续答对题目的计数器
    $_SESSION['consecutive_correct'] = 0;
    // 重置连续答对题目数量最高记录
    $_SESSION['consecutive_correct_max'] = 0;
    // 清除倒计时和本地存储中的倒计时时间
    echo '<script>
        if (typeof countdownInterval!== "undefined") {
            clearInterval(countdownInterval);
        }
        localStorage.removeItem("countdownTimeLeft");
        localStorage.removeItem("totalAnswerTime"); // 清除总答题时间
    </script>';
    // 重置答对题目数量
    $_SESSION['correct_count'] = 0;
    // 重置错题 ID 数组
    $_SESSION['wrong_question_ids'] = [];
    // 重置答题开始时间
    $_SESSION['total_answer_start_time'] = microtime(true);
    // 重置页面加载标志
    unset($_SESSION['page_loaded']);
}

// 初始化分数
if (!isset($_SESSION['score'])) {
    $_SESSION['score'] = 0;
}

// 初始化连续答对题目的计数器
if (!isset($_SESSION['consecutive_correct'])) {
    $_SESSION['consecutive_correct'] = 0;
}

// 初始化答对题目数量
if (!isset($_SESSION['correct_count'])) {
    $_SESSION['correct_count'] = 0;
}

// 初始化连续答对题目数量最高记录
if (!isset($_SESSION['consecutive_correct_max'])) {
    $_SESSION['consecutive_correct_max'] = 0;
}

// 初始化错题 ID 数组
if (!isset($_SESSION['wrong_question_ids'])) {
    $_SESSION['wrong_question_ids'] = [];
}

// 检查是否有题目
if (!isset($_SESSION['questions']) || count($_SESSION['questions']) === 0) {
    echo "没有可用的题目。";
    exit();
}

// 检查是否从 select_chapter.php 跳转过来
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'select_chapter.php')!== false) {
    shuffle($_SESSION['questions']);
    $_SESSION['current_question_index'] = 0;
    $_SESSION['score'] = 0;
    // 重置连续答对题目的计数器
    $_SESSION['consecutive_correct'] = 0;
    // 重置连续答对题目数量最高记录
    $_SESSION['consecutive_correct_max'] = 0;
    // 清除倒计时和本地存储中的倒计时时间
    echo '<script>
        if (typeof countdownInterval!== "undefined") {
            clearInterval(countdownInterval);
        }
        localStorage.removeItem("countdownTimeLeft");
        localStorage.removeItem("totalAnswerTime"); // 清除总答题时间
    </script>';
    // 重置答对题目数量
    $_SESSION['correct_count'] = 0;
    // 重置错题 ID 数组
    $_SESSION['wrong_question_ids'] = [];
    // 重置总答题开始时间
    $_SESSION['total_answer_start_time'] = microtime(true);
    // 重置页面加载标志
    unset($_SESSION['page_loaded']);
}

// 只有在初次进入章节答题时才打乱题目顺序并重置索引
if (!isset($_SESSION['is_question_shuffled']) || $_SESSION['is_question_shuffled']!== $_SESSION['selected_chapter']) {
    shuffle($_SESSION['questions']);
    $_SESSION['current_question_index'] = 0;
    $_SESSION['score'] = 0;
    $_SESSION['is_question_shuffled'] = $_SESSION['selected_chapter'];
    // 重置连续答对题目的计数器
    $_SESSION['consecutive_correct'] = 0;
    // 重置连续答对题目数量最高记录
    $_SESSION['consecutive_correct_max'] = 0;
    // 清除倒计时和本地存储中的倒计时时间
    echo '<script>
        if (typeof countdownInterval!== "undefined") {
            clearInterval(countdownInterval);
        }
        localStorage.removeItem("countdownTimeLeft");
        localStorage.removeItem("totalAnswerTime"); // 清除总答题时间
    </script>';
    // 重置答对题目数量
    $_SESSION['correct_count'] = 0;
    // 重置错题 ID 数组
    $_SESSION['wrong_question_ids'] = [];
    // 重置总答题开始时间
    $_SESSION['total_answer_start_time'] = microtime(true);
    // 重置页面加载标志
    unset($_SESSION['page_loaded']);
}

// 获取当前题目的索引
$current_index = $_SESSION['current_question_index'];
// 检查是否超出题目范围
if ($current_index >= count($_SESSION['questions'])) {
    // 如果超出范围，重定向到结果页面或选择章节页面
    header("Location: select_chapter.php?chapter=" . $_SESSION['selected_chapter']);
    exit();
}

$current_question = $_SESSION['questions'][$current_index];
// 获取题目总数
$total_questions = count($_SESSION['questions']);
// 计算当前是第几题（索引从 0 开始，所以加 1）
$current_question_number = $current_index + 1;

// 打乱选项顺序
$options = ['option1', 'option2', 'option3', 'option4'];
$validOptions = [];
foreach ($options as $option) {
    if (!empty($current_question[$option])) {
        $validOptions[$option] = $current_question[$option];
    }
}
shuffle($validOptions);

// 获取正确答案的原始键名
$correctAnswerKey = 'option' . $current_question['answer'];
// 找到打乱后正确答案的位置
$correctAnswerPosition = array_search($current_question[$correctAnswerKey], $validOptions) + 1;

// 加密函数
function encrypt($data, $key) {
    $timestamp = time();
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encryptedData = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    $base64Encoded = base64_encode($timestamp . ':' . base64_encode($iv) . ':' . base64_encode($encryptedData));
    // 日志输出，检查生成的 Base64 字符串
    error_log("Generated Base64: $base64Encoded");
    return $base64Encoded;
}

// 使用定义的密钥
$encryptedAnswer = encrypt($correctAnswerPosition, ENCRYPTION_KEY);

// 记录当前题目的开始时间（用于计算单题用时，但总时间从章节开始时计算）
$_SESSION['current_question_start_time'] = microtime(true);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>答题中……第<?php echo $current_question_number; ?>题，共<?php echo $total_questions; ?>题，当前分数: <?php echo number_format($_SESSION['score'], 1); ?></title>
    <link href="fontawesome-free-6.4.0-web/css/all.min.css" rel="stylesheet">
    
    <style>
        /* 全局样式 */
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
            background-size: cover;
            background-repeat: no-repeat;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            overflow: hidden; /* 防止滚动 */
        }

        /* 标题样式 */
        h1 {
            font-size: 2em;
            color: #007BFF;
            margin-bottom: 10px;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        /* 表单样式 */
        form {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            width: 500px;
            margin-bottom: 40px;
            display: flex;
            flex-direction: column;
            gap: 25px;
            width: 60%;
        }

        /* 问题样式 */
        .question {
            font-size: 1.8em;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }

        /* 选项样式 */
        .option {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        input[type="radio"] {
            margin-right: 15px;
        }

        /* 增大选项字体大小 */
        .option label {
            color: #666;
            font-weight: 500;
            font-size: 1.6em;
        }

        /* 增加选择器特异性 */
        .option label.correct {
            color: #28a745;
            font-weight: bold;
        }

        .option label.wrong {
            color: #dc3545;
        }

        /* 链接样式 */
        .links {
            display: flex;
            gap: 30px;
        }

        .links a {
            display: inline-block;
            background-color: #007BFF;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.3s ease;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .links a:hover {
            background-color: #0056b3;
            transform: translateY(-3px);
        }

        /* 连续答对题目数量和倒计时样式 */
        #right-float-container {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        #consecutive-correct {
            font-size: 1.5em;
            color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        #countdown {
            font-size: 2em;
            color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            visibility: hidden; /* 初始状态不可见，但占用空间 */
        }

        #bonus-message {
            font-size: 1.5em;
            color: #007BFF;
            background-color: rgba(0, 123, 255, 0.1);
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            visibility: hidden;
        }

        /* 全屏遮罩样式 */
        #fullscreen-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.01);
            z-index: 9998;
            pointer-events: none; /* 允许点击穿透到内容 */
        }
        
        /* 全屏按钮样式 */
        .fullscreen-button {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 9999;
            padding: 10px 15px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .fullscreen-button:hover {
            background-color: #0056b3;
        }
        
        /* 开发者工具检测警告样式 */
        #devtools-warning {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 0, 0, 0.7);
            color: white;
            font-size: 2em;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            text-align: center;
            padding: 20px;
            display: none;
        }
        
        #devtools-warning button {
            margin-top: 20px;
            padding: 10px 20px;
            font-size: 0.8em;
            background-color: white;
            color: red;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>

<body>
	<!-- 开发者工具检测警告 -->
    <div id="devtools-warning">
        <p>检测到您已打开开发者工具，这违反了答题规则。</p>
        <p>请关闭开发者工具后再进入答题。</p>
        <button id="back-to-chapter">返回章节选择</button>
    </div>
	
    <!-- 全屏遮罩，防止鼠标右键和拖拽 -->
    <div id="fullscreen-overlay"></div>
    
    <h1 id="question-title">答题中……第<?php echo $current_question_number; ?>题，共<?php echo $total_questions; ?>题，当前分数: <?php echo number_format($_SESSION['score'], 1); ?></h1>
    <form id="questionForm">
        <p class="question"><?php echo htmlspecialchars($current_question['question']); ?></p>
        <?php
        $optionLabels = ['1', '2', '3', '4'];
        $index = 0;
        foreach ($validOptions as $option) {
            echo '<div class="option">';
            echo '<input type="radio" id="option_' . $optionLabels[$index] . '" name="answer" value="' . $optionLabels[$index] . '">';
            echo '<label for="option_' . $optionLabels[$index] . '">' . $optionLabels[$index] . '. ' . htmlspecialchars($option) . '</label>';
            echo '</div>';
            $index++;
        }
        ?>
    </form>
    <div class="links">
        <a href="select_chapter.php?chapter=<?php echo $_SESSION['selected_chapter']; ?>">返回选择章节</a>
        <a href="logout.php">退出登录</a>
    </div>
    <div id="right-float-container">
        <p id="consecutive-correct">连续答对题目数量: <?php echo $_SESSION['consecutive_correct']; ?></p>
        <div id="countdown"></div>
        <div id="bonus-message"></div>
    </div>

    <script>
        window.onload = function () {
        	// 浏览器检测函数
            function detectBrowser() {
                const userAgent = navigator.userAgent.toLowerCase();
    
			    // 检查是否为Chrome或Edge（基于Chromium）
			    const isChrome = /chrome/.test(userAgent) && !/edge|edg/.test(userAgent);
			    const isChromiumEdge = /edg/.test(userAgent);
			    
			    // 检查是否为Firefox
			    const isFirefox = /firefox/.test(userAgent);
			    
			    // 检查是否为360浏览器
			    // 360浏览器通常会包含"360se"或"360ee"标识
			    const is360 = /360se|360ee/.test(userAgent);
			    
			    return isChrome || isChromiumEdge || isFirefox || is360;
            }
        
            // 检查浏览器兼容性
            if (!detectBrowser()) {
                alert('本系统仅支持 Edge、Chrome、火狐 和 360 浏览器，请使用这些浏览器访问。');
                window.location.href = 'select_chapter.php?chapter=<?php echo $_SESSION['selected_chapter']; ?>';
            }
        	
	        // 改进的fetch请求函数，添加错误处理
	        async function secureFetch(url, options = {}) {
	            try {
	                // 确保使用相对路径
	                const relativeUrl = url.startsWith('/') ? url : `./${url}`;
	                
	                const response = await fetch(relativeUrl, {
	                    method: options.method || 'GET',
	                    headers: {
	                        'Content-Type': 'application/x-www-form-urlencoded',
	                        ...options.headers
	                    },
	                    body: options.body,
	                    credentials: 'same-origin' // 确保发送同源凭证
	                });
	
	                if (!response.ok) {
	                    throw new Error(`HTTP错误! 状态: ${response.status}`);
	                }
	
	                return await response.text();
	            } catch (error) {
	                console.error(`请求 ${url} 失败:`, error);
	                alert(`操作失败: ${error.message}`);
	                throw error; // 继续抛出错误以便上层处理
	            }
	        }
	        
	        // 显示违规警告并跳转
	        function showViolationAlert() {
	            const alertBox = document.getElementById('violation-alert');
	            if (alertBox) {
	                alertBox.style.display = 'block';
	                
	                // 2秒后跳转
	                setTimeout(() => {
	                    window.location.href = 'select_chapter.php?chapter=<?php echo $_SESSION['selected_chapter']; ?>';
	                }, 2000);
	            }
	        }
	        
	        // 核心：禁止浏览器刷新相关操作
	        document.addEventListener('keydown', function (e) {
	            // 阻止 F5 刷新
	            if (e.key === 'F5' || e.ctrlKey && e.key.toLowerCase() === 'r') {
	                e.preventDefault();
	                showViolationAlert();
	                return false;
	            }
	            // 阻止其他可能的刷新组合键（如 Ctrl+Shift+R 强制刷新）
	            if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'r') {
	                e.preventDefault();
	                showViolationAlert();
	                return false;
	            }
	            // 阻止其他功能键
	            if (e.key.startsWith('F') && /^\d+$/.test(e.key.slice(1))) {
	                e.preventDefault();
	                showViolationAlert();
	                return false;
	            }
	        });
	
	        // 额外防护：禁用浏览器历史导航（防止后退/前进导致的页面重置）
	        history.pushState(null, null, window.location.href);
	        window.addEventListener('popstate', function (e) {
	            e.preventDefault();
	            showViolationAlert();
	        });
	        
	        // 禁止鼠标右键
	        document.addEventListener('contextmenu', function (e) {
	            e.preventDefault();
	            showViolationAlert();
	        });
	        
	        // 禁止选择文本
	        document.addEventListener('selectstart', function (e) {
	            e.preventDefault();
	        });
	        
	        // 禁止复制
	        document.addEventListener('copy', function (e) {
	            e.preventDefault();
	        });
	        
	        // 禁止剪切
	        document.addEventListener('cut', function (e) {
	            e.preventDefault();
	        });
	        
	        // 禁止粘贴
	        document.addEventListener('paste', function (e) {
	            e.preventDefault();
	        });
	        
	        // 禁止拖拽
	        document.addEventListener('dragstart', function (e) {
	            e.preventDefault();
	        });
	        
	        // 禁止键盘按键（除了方向键和空格键）
	        document.addEventListener('keydown', function (e) {
	            // 允许 Tab 键在选项间切换
	            if (e.key === 'Tab') {
	                return;
	            }
	            
	            // 允许方向键和空格键（用于选择选项）
	            if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', ' '].includes(e.key)) {
	                return;
	            }
	            
	            // 阻止所有其他按键事件
	            e.preventDefault();
	            
	            // 记录异常行为
	            console.log('阻止按键:', e.key, e.code);
	        });
	        
	        // 监听窗口大小变化（可能是用户尝试切换窗口）
	        let windowSizeCheckInterval;
	        let initialWidth = window.innerWidth;
	        let initialHeight = window.innerHeight;
	        
	        function startWindowSizeCheck() {
	            windowSizeCheckInterval = setInterval(() => {
	                const currentWidth = window.innerWidth;
	                const currentHeight = window.innerHeight;
	                
	                // 如果窗口大小变化超过阈值，视为异常行为
	                const widthChange = Math.abs(currentWidth - initialWidth);
	                const heightChange = Math.abs(currentHeight - initialHeight);
	                
	                if (widthChange > 100 || heightChange > 100) {
	                    console.log('检测到窗口大小异常变化，可能尝试切换窗口');
	                    showViolationAlert();
	                    clearInterval(windowSizeCheckInterval);
	                }
	            }, 1000);
	        }
	        
	        startWindowSizeCheck();
	        
	        /*
	        // 监听全屏状态变化
	        document.addEventListener('fullscreenchange', function () {
	            if (!document.fullscreenElement) {
	                // 退出全屏时，视为异常行为
	                console.log('检测到退出全屏，可能尝试绕过限制');
	                showViolationAlert();
	            }
	        });
	        
	        // 创建全屏按钮
	        const fullscreenButton = document.createElement('button');
	        fullscreenButton.textContent = '进入全屏模式';
	        fullscreenButton.className = 'fullscreen-button';
	        
	        // 全屏函数
	        function enterFullscreen() {
	            const docEl = document.documentElement;
	            if (docEl.requestFullscreen) {
	                docEl.requestFullscreen();
	            } else if (docEl.mozRequestFullScreen) { // Firefox
	                docEl.mozRequestFullScreen();
	            } else if (docEl.webkitRequestFullscreen) { // Chrome, Safari
	                docEl.webkitRequestFullscreen();
	            } else if (docEl.msRequestFullscreen) { // IE/Edge
	                docEl.msRequestFullscreen();
	            }
	            
	            // 进入全屏后隐藏按钮
	            fullscreenButton.style.display = 'none';
	        }
	        
	        // 为按钮添加点击事件
	        fullscreenButton.addEventListener('click', enterFullscreen);
	        
	        // 将按钮添加到页面
	        document.body.appendChild(fullscreenButton);
	        
	        // 检查是否已有全屏状态
	        if (document.fullscreenElement) {
	            fullscreenButton.style.display = 'none';
	        }
	        */
	        
	        // 开发者工具检测逻辑
            const devtoolsWarning = document.getElementById('devtools-warning');
            const backToChapterBtn = document.getElementById('back-to-chapter');
            
            // 返回章节选择
            backToChapterBtn.addEventListener('click', function() {
                window.location.href = 'select_chapter.php?chapter=<?php echo $_SESSION['selected_chapter']; ?>';
            });
            
            // 检测开发者工具打开状态
            function detectDevTools() {
                const threshold = 160;
                const widthThreshold = window.outerWidth - window.innerWidth > threshold;
                const heightThreshold = window.outerHeight - window.innerHeight > threshold;
                
                // 检测 console 对象是否被修改
                const consoleModified = typeof console.log !== 'function';
                
                // 检测 performance.now() 时间间隔异常
                let t1 = performance.now();
                let t2 = performance.now();
                const timeDiff = t2 - t1;
                const timeAnomaly = timeDiff > 100;
                
                // 综合判断开发者工具是否打开
                const isOpen = widthThreshold || heightThreshold || consoleModified || timeAnomaly;
                
                if (isOpen) {
                    // 显示警告并记录违规行为
                    devtoolsWarning.style.display = 'flex';
                }
                
                // 持续检测
                setTimeout(detectDevTools, 1000);
            }
            
            // 开始检测
            detectDevTools();

            const options = document.querySelectorAll('input[type="radio"]');
            const encryptedAnswer = <?php echo json_encode($encryptedAnswer); ?>;
            let currentQuestionNumber = <?php echo $current_question_number; ?>;
            const totalQuestions = <?php echo $total_questions; ?>;
            const consecutiveCorrectElement = document.getElementById('consecutive-correct');
            const countdownElement = document.getElementById('countdown');
            const bonusMessageElement = document.getElementById('bonus-message');
            let countdownInterval;
            let isCountdownActive = false;

            // 检查字符串是否为有效的 Base64 编码
            function isValidBase64(str) {
                try {
                    return btoa(atob(str)) === str;
                } catch (error) {
                    return false;
                }
            }

            // 使用Fetch API调用后台解密
            async function decrypt(encrypted, key) {
                try {
                    if (!isValidBase64(encrypted)) {
                        throw new Error('传入的字符串不是有效的 Base64 编码');
                    }
                    const [timestamp, ivBase64, encryptedDataBase64] = atob(encrypted).split(':');
                    if (!isValidBase64(ivBase64) || !isValidBase64(encryptedDataBase64)) {
                        throw new Error('IV 或加密数据不是有效的 Base64 编码');
                    }
                    const currentTimestamp = Math.floor(Date.now() / 1000);
                    // 检查时间戳是否在合理范围内（例如，10 分钟内）
                    if (currentTimestamp - timestamp > 600) {
                        throw new Error('Timestamp expired');
                    }

                    const response = await secureFetch('decrypt_answer.php', {
                        method: 'POST',
                        body: `encrypted=${encodeURIComponent(encryptedDataBase64)}&iv=${encodeURIComponent(ivBase64)}&key=${encodeURIComponent(key)}`
                    });

                    return response;
                } catch (error) {
                    console.error('解密过程中出现错误:', error);
                    throw error;
                }
            }

            // 使用定义的密钥
            const encryptionKey = "<?php echo ENCRYPTION_KEY; ?>";
            let correctAnswer;

            decrypt(encryptedAnswer, encryptionKey)
              .then((answer) => {
                    correctAnswer = answer;
                })
              .catch((error) => {
                    console.error('解密过程中出现错误:', error);
                });

            // 倒计时函数
            function startCountdown(timeLeft) {
                isCountdownActive = true;
                if (countdownElement) {
                    countdownElement.style.visibility = 'visible';
                    countdownElement.textContent = `倒计时: ${timeLeft} 秒`;
                }
                countdownInterval = setInterval(() => {
                    timeLeft--;
                    localStorage.setItem('countdownTimeLeft', timeLeft);
                    if (timeLeft >= 0 && countdownElement) {
                        countdownElement.textContent = `倒计时: ${timeLeft} 秒`;
                    }
                    if (timeLeft < 0) {
                        clearInterval(countdownInterval);
                        if (countdownElement) {
                            countdownElement.style.visibility = 'hidden';
                        }
                        localStorage.removeItem('countdownTimeLeft');
                        isCountdownActive = false;
                    }
                }, 1000);
            }

            // 使用Fetch API替代postAndRedirect
            async function submitAndRedirect(url, data) {
                try {
                    const response = await secureFetch(url, {
                        method: 'POST',
                        body: new URLSearchParams(data).toString()
                    });

                    // 处理响应并跳转
                    window.location.href = url;
                } catch (error) {
                    console.error('提交数据时出错:', error);
                }
            }

            // 正确初始化 correctCount 变量
            let correctCount = <?php echo $_SESSION['correct_count']; ?>;
            let consecutiveCorrectMax = <?php echo $_SESSION['consecutive_correct_max']; ?>;
            let wrongQuestionIds = <?php echo json_encode($_SESSION['wrong_question_ids']); ?>;

            // 记录当前题目的开始时间（毫秒）
            const currentQuestionStartTime = performance.now();
            
            // 获取总答题时间（如果有）
            let totalAnswerTime = parseInt(localStorage.getItem('totalAnswerTime')) || 0;

            options.forEach(option => {
                option.addEventListener('change', async function () {
                    const selectedAnswer = this.value;
                    options.forEach(opt => {
                        const label = opt.nextElementSibling;
                        label.classList.remove('correct', 'wrong');
                    });

                    let isCorrect = false;
                    if (selectedAnswer === correctAnswer) {
                        this.nextElementSibling.classList.add('correct');
                        isCorrect = true;
                        correctCount++;
                        
                        // 更新 PHP 会话中的正确答题数量
                        try {
                            await secureFetch(`update_correct_count.php?correctCount=${correctCount}`);
                        } catch (error) {
                            console.error('更新正确答题数量时出错:', error);
                        }
                        
                        let addScore = isCountdownActive? 0.3 : 0.2;
                        if (isCountdownActive) {
                            if (bonusMessageElement) {
                                bonusMessageElement.style.visibility = 'visible';
                                bonusMessageElement.textContent = '答对 +0.3 分';
                                setTimeout(() => {
                                    if (bonusMessageElement) {
                                        bonusMessageElement.style.visibility = 'hidden';
                                    }
                                }, 2000);
                            }
                        }
                        
                        // 发送 Fetch 请求更新分数
                        try {
                            const response = await secureFetch(`update_score.php?addScore=${addScore}`);
                            const newScore = parseFloat(response);
                            document.title = '答题中……第' + currentQuestionNumber + '题，共' + totalQuestions + '题，当前分数: ' + newScore.toFixed(1);
                            const questionTitle = document.getElementById('question-title');
                            if (questionTitle) {
                                questionTitle.textContent = '答题中……第' + currentQuestionNumber + '题，共' + totalQuestions + '题，当前分数: ' + newScore.toFixed(1);
                            }
                        } catch (error) {
                            console.error('更新分数时出错:', error);
                        }
                        
                        // 增加连续答对题目的计数器
                        try {
                            const response = await secureFetch('update_consecutive.php?action=increase');
                            const newConsecutive = parseInt(response);
                            if (newConsecutive > consecutiveCorrectMax) {
                                consecutiveCorrectMax = newConsecutive;
                                try {
                                    await secureFetch(`update_consecutive_max.php?max=${consecutiveCorrectMax}`);
                                } catch (error) {
                                    console.error('更新连续答对最高记录时出错:', error);
                                }
                            }
                            if (consecutiveCorrectElement) {
                                consecutiveCorrectElement.textContent = '连续答对题目数量: ' + newConsecutive;
                            }

                            let timeLeft = localStorage.getItem('countdownTimeLeft');
                            if (newConsecutive === 3) {
                                if (timeLeft === null) {
                                    timeLeft = 10;
                                }
                                startCountdown(timeLeft);
                            } else if (newConsecutive === 6) {
                                if (timeLeft!== null) {
                                    timeLeft = parseInt(timeLeft) + 15;
                                } else {
                                    timeLeft = 15;
                                }
                                if (countdownInterval) {
                                    clearInterval(countdownInterval);
                                }
                                startCountdown(timeLeft);
                            }
                        } catch (error) {
                            console.error('更新连续答对次数时出错:', error);
                        }
                    } else {
                        this.nextElementSibling.classList.add('wrong');
                        options.forEach(opt => {
                            if (opt.value === correctAnswer) {
                                opt.nextElementSibling.classList.add('correct');
                            }
                        });
                        
                        // 重置连续答对题目的计数器
                        try {
                            const response = await secureFetch('update_consecutive.php?action=reset');
                            const newConsecutive = parseInt(response);
                            if (consecutiveCorrectElement) {
                                consecutiveCorrectElement.textContent = '连续答对题目数量: ' + newConsecutive;
                            }
                        } catch (error) {
                            console.error('重置连续答对次数时出错:', error);
                        }

                        // 记录错题 ID
                        const currentQuestionId = <?php echo $current_question['id']; ?>;
                        if (!wrongQuestionIds.includes(currentQuestionId)) {
                            wrongQuestionIds.push(currentQuestionId);
                        }
                        
                        try {
                            await secureFetch(`update_wrong_question_ids.php?wrongQuestionIds=${JSON.stringify(wrongQuestionIds)}`);
                        } catch (error) {
                            console.error('记录错题ID时出错:', error);
                        }
                    }

                    // 禁用所有选项，防止再次选择
                    options.forEach(opt => {
                        opt.disabled = true;
                    });

                    // 计算当前题目的答题时间并累加到总时间
                    const currentQuestionEndTime = performance.now();
                    const currentQuestionTime = currentQuestionEndTime - currentQuestionStartTime;
                    totalAnswerTime += currentQuestionTime;
                    
                    // 保存总答题时间到localStorage
                    localStorage.setItem('totalAnswerTime', totalAnswerTime);

                    // 计算正确率
                    const correctRate = correctCount / currentQuestionNumber;
                    
                    // 进入下一题或结束答题
                    <?php
                    $chapter = $_SESSION['selected_chapter'];
                    // 只有在当前题目不是最后一题时才增加索引
                    if ($current_index < count($_SESSION['questions']) - 1) {
                        echo 'setTimeout(async () => {';
                        echo 'const countdownTimeLeft = localStorage.getItem(\'countdownTimeLeft\');';
                        echo 'const data = {';
                        echo 'countdownTimeLeft: countdownTimeLeft,';
                        echo 'correctRate: correctRate,';
                        echo 'answerTime: Math.floor(totalAnswerTime / 1000)';
                        echo '};';
                        echo 'await submitAndRedirect(\'question.php\', data);';
                        echo '}, 2000);';
                    } else {
                        echo 'setTimeout(async () => {';
                        echo 'const score = parseFloat(document.title.match(/当前分数: (\\d+\\.\\d+)/)[1]);';
                        echo 'const accuracy = (correctCount / totalQuestions) * 100;';
                        echo 'const answerTime = Math.floor(totalAnswerTime / 1000);';
                        
                        // 弹出alert信息
                        echo 'alert(\'答题结束！\\n回答题目数: \' + totalQuestions + \' 题\\n答对题目数: \' + correctCount + \' 题\\n连续答对最高记录: \' + consecutiveCorrectMax + \' 题\\n正确率: \' + accuracy.toFixed(2) + \'%\\n分数: \' + score.toFixed(1) + \'\\n用时: \' + answerTime + \' 秒\');';
                        
                        // 发送记录到服务器
                        echo 'try {';
                        echo 'await secureFetch(\'insert_record.php?chapter='.$chapter.'&accuracy=\' + accuracy.toFixed(2) + \'&consecutive_correct_max=\' + consecutiveCorrectMax + \'&wrong_question_ids=\' + JSON.stringify(wrongQuestionIds) + \'&answered_questions=\' + totalQuestions + \'&correct_questions=\' + correctCount + \'&answer_time=\' + answerTime);';
                        echo '} catch (error) {';
                        echo 'console.error(\'记录答题结果时出错:\', error);';
                        echo '}';
                        
                        // 发送积分记录请求，包含正确率和答题时间
                        echo 'try {';
                        echo 'const response = await secureFetch(\'record_point_log.php?score=\' + score + \'&chapter='.$chapter.'&correct_rate=\' + correctRate + \'&answer_time=\' + answerTime + \'&answered_questions=\' + totalQuestions);';
                        echo 'try {';
                        echo 'const responseData = JSON.parse(response);';
                        echo 'if (responseData.status === \'success\') {';
                        echo 'alert(responseData.message);';
                        echo '} else {';
                        echo 'alert(\'积分记录失败: \' + responseData.message);';
                        echo '}';
                        echo '} catch (parseError) {';
                        echo 'console.error(\'解析积分记录响应时出错:\', parseError, \'响应内容:\', response);';
                        echo 'alert(\'处理积分记录响应时出错: \' + parseError.message);';
                        echo '}';
                        echo '} catch (error) {';
                        echo 'console.error(\'积分记录请求失败:\', error);';
                        echo 'alert(\'积分记录请求失败: \' + error.message);';
                        echo '}';
                        
                        // 跳转回章节选择页面
                        echo 'window.location.href = \'./select_chapter.php?chapter='.$chapter.'\';';
                        echo '}, 2000);';
                    }
                    ?>
                });
            });

            // 页面加载时直接检查并显示倒计时
            const countdownTimeLeft = localStorage.getItem('countdownTimeLeft');
            if (countdownTimeLeft!== null && parseInt(countdownTimeLeft) > 0) {
                isCountdownActive = true;
                if (countdownElement) {
                    countdownElement.style.visibility = 'visible';
                    countdownElement.textContent = `倒计时: ${countdownTimeLeft} 秒`;
                }
                startCountdown(parseInt(countdownTimeLeft));
            }
        };
    </script>
</body>

</html>    