<?php
session_start();
// 引入数据库配置（根据实际路径调整）
include 'config.php';
include 'check_commitment.php';
// 验证学生是否登录，未登录则跳转到登录页
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "student") {
    header("Location: login.php");
    exit();
}
$student_id = $_SESSION["user_id"];

// 检测承诺书
$commitment_check = checkStudentCommitment($conn, $student_id);
$commitment_warning = $commitment_check['warning'];


// 获取学生信息（姓名、班级等）
$student_info = [];
$sql = "SELECT name, class FROM students WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $student_info = $result->fetch_assoc();
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>错题修炼手册</title>
  <script src="js/tailwindcss.js"></script>
  <link href="fontawesome-free-6.4.0-web/css/all.min.css" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#165DFF',
            secondary: '#36CFC9',
            success: '#52C41A',
            danger: '#FF4D4F',
            warning: '#FAAD14',
            dark: '#1F2937',
            light: '#F9FAFB'
          },
          fontFamily: {
            inter: ['Inter', 'system-ui', 'sans-serif'],
          },
        },
      }
    }
    
    // 转义HTML特殊字符
    function escapeHtml(text) {
      return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
  </script>
  <style type="text/tailwindcss">
    @layer utilities {
      .content-auto {
        content-visibility: auto;
      }
      .card-shadow {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
      }
      .btn-hover {
        @apply transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5;
      }
      .scale-hover {
        @apply transition-transform duration-300 hover:scale-105;
      }
      .progress-animation {
        transition: width 0.5s ease-in-out;
      }
      /* 新增：禁用按钮的样式（强制生效） */
      .btn-disabled {
        @apply opacity-50 cursor-not-allowed pointer-events-none !important;
      }
      /* 积分说明样式 */
      .points-badge {
        background: linear-gradient(135deg, #165DFF 0%, #36CFC9 100%);
        @apply inline-flex items-center px-3 py-1 rounded-full text-white text-sm font-medium shadow-md hover:shadow-lg transition-all duration-300;
      }
      .points-icon {
        @apply w-5 h-5 rounded-full bg-white/20 flex items-center justify-center mr-2;
      }
      /* 新增：tooltip样式 */
      .tooltip {
        @apply relative inline-block;
      }
      .tooltip:hover .tooltip-text {
        @apply opacity-100 visible;
      }
      .tooltip-text {
        @apply invisible absolute opacity-0 transition-opacity duration-300 bg-dark text-white text-xs rounded py-1 px-2 z-10 whitespace-nowrap;
      }
      .tooltip-bottom {
        @apply bottom-full left-1/2 transform -translate-x-1/2 mb-2;
      }
      /* 进度卡片优化样式 */
      .progress-stat {
        @apply inline-flex items-center px-2 py-1 rounded-md text-sm;
      }
      .progress-stat-success {
        @apply bg-success/10 text-success;
      }
      .progress-stat-total {
        @apply bg-gray-100 text-gray-700;
      }
      .progress-stat-chapter {
        @apply bg-primary/10 text-primary font-medium;
      }
      /* 今日积分提示样式 */
      #daily-points-tip {
        @apply transition-all duration-300 hover:bg-primary/10;
      }
      #points-progress-text {
        @apply font-medium;
      }
      /* 积分Toast提示优化 */
      .bg-success {
        background: linear-gradient(135deg, #52C41A 0%, #73d13d 100%);
      }
    }
  </style>
</head>
<body class="font-inter bg-gray-50 text-gray-800 min-h-screen flex flex-col">
	<?php if ($commitment_warning): ?>
    <div class="commitment-warning">
        <?php echo $commitment_warning; ?>
        <a href="student_commitment.php" style="color: #dc3545; margin-left: 10px;">点击重新签署</a>
    </div>
   <?php endif; ?>
	
  <!-- 顶部导航栏 - 新增返回菜单按钮 -->
  <header class="bg-white shadow-sm sticky top-0 z-50">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between flex-wrap">
      <div class="flex items-center space-x-4 mb-2 md:mb-0">
        <div class="flex items-center space-x-2">
          <i class="fa fa-graduation-cap text-primary text-2xl"></i>
          <h1 class="text-xl font-bold text-primary">错题修炼手册</h1>
        </div>
        <!-- 美化后的积分说明 -->
        <div class="points-badge">
          <div class="points-icon">
            <i class="fa fa-star text-xs"></i>
          </div>
          <span>每更正一题可得0.1积分，每天积分限制10分。</span>
        </div>
        <!-- 今日积分进度提示 -->
        <div id="daily-points-tip" class="hidden md:flex items-center space-x-2 text-sm text-primary bg-primary/5 px-3 py-1 rounded-full">
          <i class="fa fa-coins"></i>
          <span id="points-progress-text">今日积分：0.0/10.0分</span>
        </div>
      </div>
      <div class="flex items-center space-x-4">
        <div class="hidden md:flex items-center space-x-1 text-sm">
          <i class="fa fa-user-circle-o text-gray-500"></i>
          <span id="student-name"><?php echo htmlspecialchars($student_info['name'] ?? '未知学生'); ?></span>
          <span class="text-gray-400">|</span>
          <span id="student-class"><?php echo htmlspecialchars($student_info['class'] ?? '未知班级'); ?></span>
        </div>
        <!-- 新增：返回菜单按钮 -->
        <button id="menu-btn" class="text-gray-600 hover:text-primary transition-colors">
          <i class="fa fa-bars"></i>
          <span class="hidden md:inline ml-1">菜单</span>
        </button>
        <button id="logout-btn" class="text-gray-600 hover:text-primary transition-colors">
          <i class="fa fa-sign-out"></i>
          <span class="hidden md:inline ml-1">退出</span>
        </button>
      </div>
    </div>
  </header>

  <!-- 主内容区 -->
  <main class="flex-grow container mx-auto px-4 py-6">
    
    <!-- 章节选择 -->
    <div class="mb-8">
      <h2 class="text-lg font-semibold mb-4">选择章节</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3" id="chapter-list">
        <!-- 章节按钮将通过JavaScript动态生成 -->
      </div>
    </div>
    
    <!-- 优化后的进度卡片 -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
      <div class="flex flex-col gap-4">
        <!-- 进度标题和统计信息 -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <h2 class="text-lg font-semibold text-gray-800">错题重做进度</h2>
          <!-- 统计信息行 - 适配长章节名称 -->
          <div class="flex flex-wrap items-center gap-2">
            <!-- 章节信息 - 增加tooltip和换行适配 -->
            <div class="tooltip progress-stat progress-stat-chapter">
              <i class="fa fa-book mr-1"></i>
              <span id="current-chapter" class="truncate max-w-[180px]">全部章节</span>
              <span class="tooltip-text tooltip-bottom" id="chapter-tooltip">全部章节</span>
            </div>
            <!-- 总错题数 -->
            <div class="progress-stat progress-stat-total">
              <i class="fa fa-question-circle mr-1"></i>
              总错题: <span id="total-wrong-count" class="font-medium">0</span>
            </div>
            <!-- 已答对数量 -->
            <div class="progress-stat progress-stat-success">
              <i class="fa fa-check-circle mr-1"></i>
              已答对: <span id="correct-count" class="font-medium">0</span>
            </div>
          </div>
        </div>
        
        <!-- 进度条区域 - 全宽适配 -->
        <div class="w-full">
          <div class="flex items-center gap-3">
            <div class="w-full bg-gray-200 rounded-full h-2.5">
              <div id="progress-bar" class="bg-primary h-2.5 rounded-full progress-animation" style="width: 0%"></div>
            </div>
            <span id="progress-text" class="text-sm font-medium text-primary min-w-[40px] text-center">0%</span>
          </div>
          <!-- 进度百分比说明（移动端可见） -->
          <p class="text-xs text-gray-500 mt-1 hidden sm:block">
            完成进度：<span id="progress-desc">0/0 题 (0%)</span>
          </p>
        </div>
      </div>
    </div>

    <!-- 题目区域 -->
    <div id="question-container" class="bg-white rounded-xl shadow-sm p-6 mb-8">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold" id="question-number">题目 1/0</h2>
        <div class="flex flex-wrap gap-2">
          <span class="text-sm bg-gray-100 text-gray-600 px-3 py-1 rounded-full tooltip">
            <i class="fa fa-book mr-1"></i> 
            <span class="truncate max-w-[120px]" id="question-chapter-short">章节名称</span>
            <span class="tooltip-text tooltip-bottom" id="question-chapter-full">章节名称</span>
          </span>
          <span class="text-sm bg-gray-100 text-gray-600 px-3 py-1 rounded-full" id="question-id">
            <i class="fa fa-tag mr-1"></i> 题目ID: 0
          </span>
        </div>
      </div>

      <div id="question-content" class="mb-6 text-lg">
        <!-- 题目内容将通过JavaScript动态生成 -->
      </div>

      <div id="options-container" class="space-y-3 mb-8">
        <!-- 选项将通过JavaScript动态生成 -->
      </div>

      <div class="flex justify-between">
        <button id="prev-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hidden btn-hover">
          <i class="fa fa-chevron-left mr-1"></i> 上一题
        </button>
        <div class="flex space-x-3">
          <button id="skip-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg btn-hover">
            <i class="fa fa-forward mr-1"></i> 跳过
          </button>
          <button id="submit-btn" class="px-4 py-2 bg-primary text-white rounded-lg btn-hover">
            提交答案 <i class="fa fa-check ml-1"></i>
          </button>
        </div>
        <button id="next-btn" class="px-4 py-2 bg-primary text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hidden btn-hover">
          下一题 <i class="fa fa-chevron-right ml-1"></i>
        </button>
      </div>
    </div>

    <!-- 结果反馈 -->
    <div id="feedback-container" class="bg-white rounded-xl shadow-sm p-6 mb-8 hidden">
      <div id="correct-feedback" class="hidden">
        <div class="flex items-center mb-4">
          <div class="w-12 h-12 rounded-full bg-success/20 flex items-center justify-center text-success">
            <i class="fa fa-check text-2xl"></i>
          </div>
          <div class="ml-4">
            <h3 class="text-xl font-bold text-success">回答正确！</h3>
            <p class="text-gray-600">恭喜你，答对了这道题，该错题记录已清除。</p>
            <p class="text-sm text-primary mt-1"><i class="fa fa-star mr-1"></i> 获得0.1积分奖励</p>
          </div>
        </div>
      </div>

      <div id="incorrect-feedback" class="hidden">
        <div class="flex items-center mb-4">
          <div class="w-12 h-12 rounded-full bg-danger/20 flex items-center justify-center text-danger">
            <i class="fa fa-times text-2xl"></i>
          </div>
          <div class="ml-4">
            <h3 class="text-xl font-bold text-danger">回答错误</h3>
            <p class="text-gray-600">正确答案是：<span id="correct-answer" class="font-medium"></span></p>
          </div>
        </div>
      </div>

      <div class="mt-6">
        <button id="continue-btn" class="px-6 py-3 bg-primary text-white rounded-lg btn-hover">
          继续练习 <i class="fa fa-arrow-right ml-1"></i>
        </button>
      </div>
    </div>

    <!-- 完成提示 -->
    <div id="completion-message" class="bg-white rounded-xl shadow-sm p-6 mb-8 hidden text-center">
      <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-secondary/20 flex items-center justify-center">
        <i class="fa fa-trophy text-4xl text-secondary"></i>
      </div>
      <h2 class="text-2xl font-bold mb-2">太棒了！</h2>
      <p class="text-gray-600 mb-3">你已完成当前章节的所有错题练习。</p>
      <p class="text-primary font-medium mb-6"><i class="fa fa-star mr-1"></i> 本次共获得 <span id="total-points" class="text-lg">0</span> 积分</p>
      <button id="restart-btn" class="px-6 py-3 bg-primary text-white rounded-lg btn-hover">
        <i class="fa fa-refresh mr-1"></i> 重新练习
      </button>
    </div>
  </main>

  <!-- 页脚 -->
  <footer class="bg-white border-t border-gray-200 py-6">
    <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
      <p>© 2025 错题修炼手册 | 茂名市人工智能实践项目  | 信宜市职业技术学校-教研室</p>
    </div>
  </footer>

  <!-- JavaScript -->
  <script>
    // 应用状态（从PHP获取学生ID）
    const state = {
      currentChapter: "全部章节",
      currentQuestionIndex: 0,
      selectedOption: null,
      questions: [],
      totalWrongCount: 0, // 新增：记录当前章节总错题数（初始值）
      correctAnswers: 0,  // 已答对的错题数
      studentId: "<?php echo htmlspecialchars($student_id); ?>", // 从PHP传入学生ID
      isCurrentQuestionCorrect: false, // 标记当前题目是否答对
      totalPointsEarned: 0, // 新增：记录获得的总积分
      // 新增积分字段
      todayUsedPoints: 0,   // 今日已用积分
      remainingPoints: 10.0,// 今日剩余积分（默认10分）
      dailyMaxPoints: 10.0  // 每日积分上限
    };

    // DOM 元素
    const elements = {
      chapterList: document.getElementById('chapter-list'),
      questionContainer: document.getElementById('question-container'),
      questionNumber: document.getElementById('question-number'),
      questionChapter: document.getElementById('question-chapter-short'), // 短显示
      questionChapterFull: document.getElementById('question-chapter-full'), // 完整tooltip
      questionId: document.getElementById('question-id'),
      questionContent: document.getElementById('question-content'),
      optionsContainer: document.getElementById('options-container'),
      prevBtn: document.getElementById('prev-btn'),
      nextBtn: document.getElementById('next-btn'),
      skipBtn: document.getElementById('skip-btn'),
      submitBtn: document.getElementById('submit-btn'),
      feedbackContainer: document.getElementById('feedback-container'),
      correctFeedback: document.getElementById('correct-feedback'),
      incorrectFeedback: document.getElementById('incorrect-feedback'),
      correctAnswer: document.getElementById('correct-answer'),
      continueBtn: document.getElementById('continue-btn'),
      completionMessage: document.getElementById('completion-message'),
      restartBtn: document.getElementById('restart-btn'),
      progressBar: document.getElementById('progress-bar'),
      progressText: document.getElementById('progress-text'),
      progressDesc: document.getElementById('progress-desc'), // 新增：进度描述
      currentChapter: document.getElementById('current-chapter'),
      chapterTooltip: document.getElementById('chapter-tooltip'), // 章节tooltip
      logoutBtn: document.getElementById('logout-btn'),
      menuBtn: document.getElementById('menu-btn'), // 新增：返回菜单按钮
      totalWrongCount: document.getElementById('total-wrong-count'), // 新增：总错题数显示
      correctCount: document.getElementById('correct-count'),         // 新增：已答对数显示
      totalPoints: document.getElementById('total-points'),           // 新增：总积分显示
      dailyPointsTip: document.getElementById('daily-points-tip'),    // 今日积分提示
      pointsProgressText: document.getElementById('points-progress-text') // 积分进度文本
    };

    // ========== 新增：获取今日积分统计数据 ==========
    async function fetchTodayPoints() {
      try {
        const response = await fetch(`/score2026/api.php/students/${state.studentId}/today-points`);
        if (!response.ok) throw new Error('获取今日积分失败');
        
        const data = await response.json();
        // 更新全局积分状态（使用后端返回的真实数据）
        state.todayUsedPoints = data.todayUsedPoints ? Number(data.todayUsedPoints).toFixed(1) : 0;
        state.remainingPoints = data.remainingPoints ? Number(data.remainingPoints).toFixed(1) : 10.0;
        state.dailyMaxPoints = data.dailyMaxPoints ? Number(data.dailyMaxPoints).toFixed(1) : 10.0;
        
        // 立即更新页面显示
        updateDailyPointsTip();
      } catch (error) {
        console.warn('获取今日积分失败（使用默认值）:', error.message);
        // 失败时仍显示默认值，不影响页面使用
        updateDailyPointsTip();
      }
    }

    // 初始化应用
    async function initApp() {
      try {
        // ========== 优先获取今日积分数据 ==========
        await fetchTodayPoints();
        
        // 从数据库获取章节列表
        const chapters = await fetchChapters();
        initChapters(chapters);
        
        // 默认加载所有章节的错题
        await loadQuestionsByChapter("全部章节");
        
        // 绑定事件处理函数
        bindEventListeners();
        
        // 初始化积分提示（此时已有真实数据）
        updateDailyPointsTip();
      } catch (error) {
        showToast('初始化失败: ' + error.message, 'error');
        console.error('初始化应用失败:', error);
      }
    }

    // 从数据库获取章节列表
    async function fetchChapters() {
      try {
        const response = await fetch('/score2026/api.php/chapters'); // 请根据实际API路径调整
        if (!response.ok) throw new Error('获取章节列表失败');
        const data = await response.json();
        return ['全部章节', ...data];
      } catch (error) {
        showToast('获取章节列表失败', 'error');
        console.error('获取章节列表失败:', error);
        return ['全部章节'];
      }
    }

    // 从数据库获取指定章节的错题
    async function fetchWrongQuestions(chapter) {
      try {
        const endpoint = chapter === "全部章节" 
          ? `/score2026/api.php/students/${state.studentId}/wrong-questions` 
          : `/score2026/api.php/students/${state.studentId}/wrong-questions?chapter=${encodeURIComponent(chapter)}`;
        
        const response = await fetch(endpoint); // 请根据实际API路径调整
        if (!response.ok) throw new Error('获取错题列表失败');
        
        const wrongQuestions = await response.json();
        return wrongQuestions.map(q => ({
          id: q.id,
          chapter: q.chapter_name, // 修正：后端返回的是chapter_name
          question: q.question,
          options: [
            { id: 1, text: q.option1 },
            { id: 2, text: q.option2 },
            { id: 3, text: q.option3 },
            { id: 4, text: q.option4 }
          ],
          answer: q.answer
        }));
      } catch (error) {
        showToast('获取错题列表失败', 'error');
        console.error('获取错题列表失败:', error);
        return [];
      }
    }

    // 初始化章节列表
    function initChapters(chapters) {
      chapters.forEach(chapter => {
        const button = document.createElement('button');
        // 章节按钮适配长文本
        button.className = `px-4 py-2 rounded-lg text-center scale-hover ${
          chapter === "全部章节" ? "bg-primary text-white" : "bg-gray-100 text-gray-700 hover:bg-gray-200"
        }`;
        // 章节按钮文本处理：超长文本换行
        button.innerHTML = `<span class="whitespace-normal break-words">${escapeHtml(chapter)}</span>`;
        button.addEventListener('click', () => selectChapter(chapter));
        elements.chapterList.appendChild(button);
      });
    }

    // 选择章节
    async function selectChapter(chapter) {
      try {
        state.currentChapter = chapter;
        state.currentQuestionIndex = 0;
        state.correctAnswers = 0; // 重置已答对数
        state.isCurrentQuestionCorrect = false; // 重置
        state.totalPointsEarned = 0; // 重置积分
        
        // 更新章节显示和tooltip
        elements.currentChapter.textContent = truncateText(chapter, 15); // 截断长文本
        elements.chapterTooltip.textContent = chapter; // tooltip显示完整名称
        
        await loadQuestionsByChapter(chapter);
        
        Array.from(elements.chapterList.children).forEach(btn => {
          if (btn.textContent.trim() === chapter) {
            btn.className = "px-4 py-2 rounded-lg text-center scale-hover bg-primary text-white";
          } else {
            btn.className = "px-4 py-2 rounded-lg text-center scale-hover bg-gray-100 text-gray-700 hover:bg-gray-200";
          }
        });
      } catch (error) {
        showToast('切换章节失败: ' + error.message, 'error');
        console.error('切换章节失败:', error);
      }
    }

    // 文本截断辅助函数
    function truncateText(text, maxLength = 15) {
      if (text.length <= maxLength) return text;
      return text.substring(0, maxLength) + '...';
    }

    // 按章节加载问题
    async function loadQuestionsByChapter(chapter) {
      try {
        state.questions = await fetchWrongQuestions(chapter);
        state.totalWrongCount = state.questions.length; // 记录当前章节总错题数
        updateProgress(); // 更新进度（包含显示总错题数和已答对数）
        renderCurrentQuestion();
      } catch (error) {
        showToast('加载题目失败: ' + error.message, 'error');
        console.error('加载题目失败:', error);
      }
    }

    // 渲染当前问题
    function renderCurrentQuestion() {
      // 核心修复1：先检查题目列表是否为空
      if (state.questions.length === 0) {
        elements.questionContainer.classList.add('hidden');
        elements.completionMessage.classList.remove('hidden');
        elements.totalPoints.textContent = (state.totalPointsEarned).toFixed(1);
        return;
      }
      
      // 核心修复2：确保currentQuestionIndex在有效范围内
      if (state.currentQuestionIndex < 0) {
        state.currentQuestionIndex = 0;
      } else if (state.currentQuestionIndex >= state.questions.length) {
        state.currentQuestionIndex = state.questions.length - 1;
      }
      
      elements.questionContainer.classList.remove('hidden');
      elements.feedbackContainer.classList.add('hidden');
      elements.completionMessage.classList.add('hidden');
      
      const question = state.questions[state.currentQuestionIndex];
      
      // 更新问题信息
      elements.questionNumber.textContent = `题目 ${state.currentQuestionIndex + 1}/${state.questions.length}`;
      // 题目章节名称处理：短显示 + tooltip完整显示
      elements.questionChapter.textContent = truncateText(question.chapter || '未分类章节', 8);
      elements.questionChapterFull.textContent = question.chapter || '未分类章节';
      elements.questionId.textContent = `\u{1F4D1} 题目ID: ${question.id}`;
      elements.questionContent.textContent = question.question;
      
      // 清空选项容器
      elements.optionsContainer.innerHTML = '';
      
      // 渲染选项
      question.options.forEach(option => {
        const optionElement = document.createElement('div');
        optionElement.className = "option bg-gray-50 rounded-lg p-4 border border-gray-200 hover:bg-gray-100 transition-colors cursor-pointer";
        const escapedText = escapeHtml(option.text);
        optionElement.innerHTML = `
          <div class="flex items-start">
            <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center mr-3 mt-0.5 option-radio flex-shrink-0">
              <div class="w-3 h-3 rounded-full bg-primary hidden"></div>
            </div>
            <span class="option-text break-words">${String.fromCharCode(64 + option.id)}. ${escapedText}</span>
          </div>
        `;
        
        optionElement.addEventListener('click', () => selectOption(optionElement, option.id));
        elements.optionsContainer.appendChild(optionElement);
      });
      
      // 更新按钮状态
      elements.prevBtn.classList.toggle('hidden', state.currentQuestionIndex === 0);
      elements.nextBtn.classList.toggle('hidden', state.currentQuestionIndex === state.questions.length - 1);
      
      // 核心：强制重置提交按钮状态（移除禁用样式+启用）
      elements.submitBtn.disabled = false;
      elements.submitBtn.classList.remove('btn-disabled');
      
      // 重置状态
      state.selectedOption = null;
      state.isCurrentQuestionCorrect = false;
    }

    // 选择选项
    function selectOption(element, optionId) {
      // 仅当当前题目未答对时，允许选择选项
      if (state.isCurrentQuestionCorrect) return;
      
      Array.from(elements.optionsContainer.children).forEach(optionEl => {
        optionEl.classList.remove('bg-primary/10', 'border-primary');
        optionEl.classList.add('bg-gray-50', 'border-gray-200');
        optionEl.querySelector('.option-radio').classList.remove('border-primary');
        optionEl.querySelector('.option-radio').classList.add('border-gray-300');
        optionEl.querySelector('.option-radio > div').classList.add('hidden');
      });
      
      element.classList.remove('bg-gray-50', 'border-gray-200');
      element.classList.add('bg-primary/10', 'border-primary');
      element.querySelector('.option-radio').classList.remove('border-gray-300');
      element.querySelector('.option-radio').classList.add('border-primary');
      element.querySelector('.option-radio > div').classList.remove('hidden');
      
      state.selectedOption = optionId;
    }

    // 提交答案
    async function submitAnswer() {
      // 核心拦截：如果当前题目已答对，直接返回
      if (state.isCurrentQuestionCorrect) {
        showToast('该题目已答对，无需重复提交', 'warning');
        return;
      }
      
      if (state.selectedOption === null) {
        showToast('请选择一个选项', 'warning');
        return;
      }
      
      const question = state.questions[state.currentQuestionIndex];
      const isCorrect = state.selectedOption === question.answer;
      state.isCurrentQuestionCorrect = isCorrect; // 标记是否答对
      
      try {
        await submitAnswerToServer(question.id, state.selectedOption, isCorrect);
        
        if (isCorrect) {
          state.correctAnswers++; // 仅答对时增加已答对数
          state.totalPointsEarned += 0.1; // 增加积分
          // 核心修复3：先记录当前索引，再删除题目
          const currentIndex = state.currentQuestionIndex;
          // 移除答对的题目
          state.questions = state.questions.filter(q => q.id !== question.id);
          
          // 核心修复4：正确更新索引（关键！）
          // 如果删除的是最后一题且还有剩余题目，索引设为最后一位；否则保持原索引
          if (currentIndex >= state.questions.length && state.questions.length > 0) {
            state.currentQuestionIndex = state.questions.length - 1;
          } else if (state.questions.length === 0) {
            state.currentQuestionIndex = 0; // 无题目时重置索引
          } else {
            state.currentQuestionIndex = currentIndex; // 有题目时保持原索引
          }
        }
        
        showFeedback(isCorrect, question.answer);
        updateProgress(); // 提交后更新进度（仅答对时进度变化）
      } catch (error) {
        showToast('提交答案失败: ' + error.message, 'error');
        console.error('提交答案失败:', error);
        // 提交失败时重置标记
        state.isCurrentQuestionCorrect = false;
      }
    }

    // 提交答案到服务器
    async function submitAnswerToServer(questionId, selectedOption, isCorrect) {
      try {
        const response = await fetch(`/score2026/api.php/students/${state.studentId}/answer`, { // 请根据实际API路径调整
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            questionId: Number(questionId),
            selectedOption: Number(selectedOption),
            isCorrect: Boolean(isCorrect),
            chapter: state.currentChapter,
            pointsEarned: isCorrect ? 0.1 : 0 // 传递获得的积分
          })
        });
        
        if (!response.ok) {
          const errorData = await response.json().catch(() => ({}));
          throw new Error(errorData.error || `请求失败（${response.status}）`);
        }

        // 解析后端返回的积分字段
        const data = await response.json();
        // 更新全局积分状态
        if (data.todayUsedPoints !== undefined) {
          state.todayUsedPoints = Number(data.todayUsedPoints).toFixed(1);
        }
        if (data.remainingPoints !== undefined) {
          state.remainingPoints = Number(data.remainingPoints).toFixed(1);
        }
        if (data.dailyMaxPoints !== undefined) {
          state.dailyMaxPoints = Number(data.dailyMaxPoints).toFixed(1);
        }
        // 更新页面积分提示
        updateDailyPointsTip();
        
        return data;
      } catch (error) {
        if (error.message.includes('Failed to fetch')) {
             throw new Error('服务器连接失败，请检查网络或后端服务');
        } else {
             throw new Error(error.message);
        }
      }
    }

    // 更新今日积分进度提示
    function updateDailyPointsTip() {
      // 显示积分提示栏
      elements.dailyPointsTip.classList.remove('hidden');
      // 生成积分提示文本
      const pointsText = `今日积分：${state.todayUsedPoints}/${state.dailyMaxPoints}分（剩余${state.remainingPoints}分）`;
      elements.pointsProgressText.textContent = pointsText;
    }

    // 显示反馈
    function showFeedback(isCorrect, correctAnswerId) {
      elements.feedbackContainer.classList.remove('hidden');
      elements.correctFeedback.classList.toggle('hidden', !isCorrect);
      elements.incorrectFeedback.classList.toggle('hidden', isCorrect);
      
      if (!isCorrect) {
        const correctOption = state.questions[state.currentQuestionIndex].options.find(o => o.id === correctAnswerId);
        elements.correctAnswer.textContent = `${String.fromCharCode(64 + correctAnswerId)}. ${correctOption.text}`;
        
        // 答错：启用提交按钮 + 清空选项选中 + 启用跳过按钮
        elements.submitBtn.disabled = false;
        elements.submitBtn.classList.remove('btn-disabled');
        // 清空选项选中状态
        Array.from(elements.optionsContainer.children).forEach(optionEl => {
          optionEl.classList.remove('bg-primary/10', 'border-primary');
          optionEl.classList.add('bg-gray-50', 'border-gray-200');
          optionEl.querySelector('.option-radio').classList.remove('border-primary');
          optionEl.querySelector('.option-radio').classList.add('border-gray-300');
          optionEl.querySelector('.option-radio > div').classList.add('hidden');
        });
        state.selectedOption = null;
        // 启用跳过按钮
        elements.skipBtn.disabled = false;
        elements.skipBtn.classList.remove('btn-disabled');
      } else {
        // 答对：强制禁用提交按钮 + 保持选项选中 + 禁用跳过按钮
        elements.submitBtn.disabled = true;
        elements.submitBtn.classList.add('btn-disabled'); // 应用强制禁用样式
        // 禁用跳过按钮
        elements.skipBtn.disabled = true;
        elements.skipBtn.classList.add('btn-disabled');
        
        // 整合剩余积分提示
        let pointsTip = '';
        if (state.remainingPoints <= 0) {
          pointsTip = `今日错题重做积分已获得${state.todayUsedPoints}分（上限${state.dailyMaxPoints}分），剩余0分可领取`;
        } else {
          pointsTip = `今日错题重做积分已获得${state.todayUsedPoints}分，剩余${state.remainingPoints}分可领取（上限${state.dailyMaxPoints}分）`;
        }
        // 显示积分获得提示 + 剩余积分信息
        showToast(`恭喜获得0.1积分！${pointsTip} 累计积分：${state.totalPointsEarned.toFixed(1)}`, 'success');
      }
    }

    // 继续下一题
    function continueToNextQuestion() {
      // 核心修复5：先检查是否真的没有题目
      if (state.questions.length === 0) {
        elements.questionContainer.classList.add('hidden');
        elements.feedbackContainer.classList.add('hidden');
        elements.completionMessage.classList.remove('hidden');
        elements.totalPoints.textContent = (state.totalPointsEarned).toFixed(1);
        return;
      }
      
      // 核心修复6：正确计算下一题索引
      if (state.currentQuestionIndex < state.questions.length - 1) {
        state.currentQuestionIndex++;
      } else {
        // 如果已是最后一题，保持在最后一题（不跳转到完成提示）
        state.currentQuestionIndex = state.questions.length - 1;
      }
      
      renderCurrentQuestion();
    }

    // 更新进度（核心修复：仅基于答对数/总错题数计算）
    function updateProgress() {
      // 显示总错题数和已答对数
      elements.totalWrongCount.textContent = state.totalWrongCount;
      elements.correctCount.textContent = state.correctAnswers;
      
      // 进度计算逻辑：已答对数 / 总错题数（而非剩余错题数）
      const progress = state.totalWrongCount > 0 
        ? Math.round((state.correctAnswers / state.totalWrongCount) * 100) 
        : 0;
      
      // 确保进度不超过100%
      const finalProgress = Math.min(progress, 100);
      
      elements.progressBar.style.width = `${finalProgress}%`;
      elements.progressText.textContent = `${finalProgress}%`;
      
      // 更新进度描述文本
      elements.progressDesc.textContent = `${state.correctAnswers}/${state.totalWrongCount} 题 (${finalProgress}%)`;
      
      // 当进度100%且无剩余题目时，显示完成提示
      if (finalProgress === 100 && state.questions.length === 0) {
        elements.questionContainer.classList.add('hidden');
        elements.feedbackContainer.classList.add('hidden');
        elements.completionMessage.classList.remove('hidden');
        elements.totalPoints.textContent = (state.totalPointsEarned).toFixed(1);
      }
    }

    // 显示提示消息
    function showToast(message, type = 'info') {
      const toast = document.createElement('div');
      toast.className = `fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg transform transition-all duration-500 ease-in-out z-50 ${
        type === 'success' ? 'bg-success text-white' : 
        type === 'error' ? 'bg-danger text-white' : 
        type === 'warning' ? 'bg-warning text-white' : 'bg-primary text-white'
      }`;
      toast.textContent = message;
      
      document.body.appendChild(toast);
      
      setTimeout(() => {
        toast.classList.add('translate-y-0');
      }, 10);
      
      setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-[-20px]');
        setTimeout(() => {
          document.body.removeChild(toast);
        }, 500);
      }, 3000);
    }

    // 新增：返回学生菜单函数（可自定义跳转地址）
    function goToStudentMenu() {
      // 这里修改为实际的学生菜单页面地址
      const menuUrl = 'student_menu.php'; // 请根据实际路径调整
      
      // 确认是否离开当前页面（如果有未提交的答题）
      if (state.selectedOption !== null && !state.isCurrentQuestionCorrect) {
        if (confirm('你有未提交的答案，确定要返回菜单吗？')) {
          window.location.href = menuUrl;
        }
      } else {
        window.location.href = menuUrl;
      }
    }

    // 绑定事件监听器
    function bindEventListeners() {
      elements.prevBtn.addEventListener('click', () => {
        if (state.currentQuestionIndex > 0) {
          state.currentQuestionIndex--;
          renderCurrentQuestion();
        }
      });
      
      elements.nextBtn.addEventListener('click', () => {
        if (state.currentQuestionIndex < state.questions.length - 1) {
          state.currentQuestionIndex++;
          renderCurrentQuestion();
        }
      });
      
      elements.skipBtn.addEventListener('click', continueToNextQuestion);
      
      // 提交按钮点击事件（增加拦截）
      elements.submitBtn.addEventListener('click', submitAnswer);
      
      elements.continueBtn.addEventListener('click', continueToNextQuestion);
      
      elements.restartBtn.addEventListener('click', () => {
        selectChapter(state.currentChapter);
      });
      
      elements.logoutBtn.addEventListener('click', () => {
        if (confirm('确定要退出吗？')) {
          // 跳转到退出登录接口
          window.location.href = 'logout.php';
        }
      });
      
      // 新增：绑定返回菜单按钮事件
      elements.menuBtn.addEventListener('click', goToStudentMenu);
    }

    // 页面加载完成后初始化应用
    document.addEventListener('DOMContentLoaded', initApp);
  </script>
</body>
</html>