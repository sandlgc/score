<?php
// 引入公共函数库
include 'student_common.php';

// 初始化（自动验证登录）
$conn = studentInit();
$student_id = $_SESSION["user_id"];

// 查询学生姓名（自动存入session）
$student_name = getStudentInfo($conn, $student_id);

// 检测承诺书状态
$commitment_check = checkStudentCommitment($conn, $student_id);
$need_recommit = $commitment_check['need_recommit'];
$force_agree = $commitment_check['force_agree'];
$has_agreed = $commitment_check['has_agreed'];
$commitment_warning = $commitment_check['warning'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生菜单</title>
    <style>
        /* 全局样式重置与变量定义 */
        :root {
            --primary: #007BFF;
            --primary-hover: #0056b3;
            --warning: #ffc107;
            --warning-bg: #fff3cd;
            --warning-border: #ffeeba;
            --warning-text: #856404;
            --danger: #dc3545;
            --danger-hover: #c82333;
            --success: #28a745;
            --success-hover: #218838;
            --gray: #6c757d;
            --gray-hover: #5a6268;
            --light-bg: #f4f4f9;
            --white: #ffffff;
            --text-dark: #333333;
            --text-muted: #666666;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 4px 15px rgba(0, 0, 0, 0.15);
            --radius: 10px;
            --transition: all 0.3s ease;
            --info-bg: #e8f4fd;
            --info-border: #b8daff;
            --info-text: #0c5460;
            --info-accent: #17a2b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Microsoft YaHei', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* 页面容器 */
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }

        /* 头部区域 */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background-color: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        h1 {
            font-size: 2em;
            color: var(--primary);
            margin-bottom: 10px;
        }

        h2 {
            font-size: 1.3em;
            color: var(--text-muted);
            font-weight: normal;
        }

        /* 提示框样式 */
        .prompt-box {
            background: var(--white);
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            text-align: center;
        }

        .prompt-box.warning {
            background-color: var(--warning-bg);
            border: 1px solid var(--warning-border);
            color: var(--warning-text);
            border-left: 4px solid var(--warning);
        }

        /* 浏览器提示框样式 */
        .browser-tip {
            background-color: var(--info-bg);
            border: 1px solid var(--info-border);
            color: var(--info-text);
            border-left: 4px solid var(--info-accent);
            padding: 15px 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            font-size: 0.95em;
            line-height: 1.8;
        }

        .browser-tip strong {
            color: var(--info-accent);
        }

        .prompt-box h3 {
            font-size: 1.2em;
            margin-bottom: 10px;
        }

        .prompt-btn {
            background: var(--primary);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
            transition: var(--transition);
        }

        .prompt-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        /* 菜单列表 */
        .menu-list {
            list-style-type: none;
            width: 100%;
            display: grid;
            gap: 15px;
            margin-bottom: 30px;
        }

        .menu-item {
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .menu-item a {
            display: block;
            background-color: var(--white);
            padding: 20px;
            text-decoration: none;
            color: var(--text-dark);
            font-size: 1.1em;
            transition: var(--transition);
            position: relative;
        }

        .menu-item a:hover {
            background-color: var(--primary);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        /* 强制签署入口样式 */
        .menu-item a.commitment-required {
            background-color: var(--warning-bg);
            border-left: 4px solid var(--warning);
            color: var(--warning-text);
            font-weight: 500;
        }

        .menu-item a.commitment-required:hover {
            background-color: #ffeeba;
            color: #735c03;
        }

        /* 禁用菜单项样式 */
        .menu-disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* 退出按钮 */
        .logout-btn {
            background-color: var(--danger);
            color: var(--white);
            padding: 12px 30px;
            border-radius: var(--radius);
            text-decoration: none;
            font-size: 1.1em;
            transition: var(--transition);
            display: inline-block;
            margin-top: 10px;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }

        .logout-btn:hover {
            background-color: var(--danger-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* 底部区域 */
        .footer {
            margin-top: auto;
            padding: 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9em;
        }

        /* 焦点状态样式（提升可访问性） */
        a:focus, button:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* 响应式适配 */
        @media (max-width: 480px) {
            h1 {
                font-size: 1.8em;
            }
            
            .menu-item a {
                padding: 15px;
                font-size: 1em;
            }
            
            .container {
                padding: 0 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 头部区域 -->
        <div class="header">
            <h1>欢迎，<?php echo $student_name; ?></h1>
            <h2>学生功能菜单</h2>
        </div>

        <!-- 承诺书提示框 -->
        <?php if ($need_recommit): ?>
        <div class="prompt-box warning">
            <h3>⚠️ <?php echo $commitment_warning; ?></h3>
            <p>您之前提交的承诺书选择了"不同意"，请立即重新签署并同意，否则无法使用系统功能</p>
            <a href="student_commitment.php" class="prompt-btn">立即重新签署</a>
        </div>
        <?php elseif (!$has_agreed && $force_agree == '0'): ?>
        <div class="prompt-box warning">
            <p>⚠️ <?php echo $commitment_warning; ?></p>
        </div>
        <?php endif; ?>

        <!-- 浏览器兼容提示框 -->
        <div class="browser-tip">
		    <strong>浏览器兼容提示：</strong>如果当前浏览器无法正常答题，请<a href="./download/firefox浏览器，解压后打开firefoxEXE文件.rar" target="_blank" style="color:var(--primary);font-weight:bold;">点击下载推荐浏览器</a>。<br>
		    使用说明：下载后解压压缩包，找到并打开“Firefox.exe”文件即可使用。
		</div>
		
		<!-- 浏览器兼容提示框 -->
        <div class="browser-tip">
		    <strong>运行软件提示：</strong>如果当前系统没有Python运行软件，请<a href="./download/thonny-4.1.7-windows-portable.zip" target="_blank" style="color:var(--primary);font-weight:bold;">点击下载Python语言运行软件</a>。<br>
		    使用说明：下载后解压压缩包，找到并打开“thonny.exe”文件即可使用。
		</div>

        <!-- 菜单列表 -->
        <ul class="menu-list">
            <li class="menu-item">
                <a href="student_dashboard.php" class="<?php echo $need_recommit ? 'menu-disabled' : ''; ?>">积分情况</a>
            </li>
            <li class="menu-item">
                <a href="select_chapter.php" class="<?php echo $need_recommit ? 'menu-disabled' : ''; ?>">选择题答题</a>
            </li>
            <li class="menu-item">
                <a href="student_homework.php" class="<?php echo $need_recommit ? 'menu-disabled' : ''; ?>">我的作业</a>
            </li>
            <li class="menu-item">
                <a href="wrong-questions-review.php" class="<?php echo $need_recommit ? 'menu-disabled' : ''; ?>">错题修炼手册</a>
            </li>
            <li class="menu-item">
                <a href="get_yuanAi.php" class="<?php echo $need_recommit ? 'menu-disabled' : ''; ?>">Python学习精灵智能体</a>
            </li>
            <li class="menu-item">
                <a href="pet.php" class="<?php echo $need_recommit ? 'menu-disabled' : ''; ?>">我的宠物（测试版）</a>
            </li>
            <li class="menu-item">
                <a href="video_list.php" class="<?php echo $need_recommit ? 'menu-disabled' : ''; ?>">操作视频</a>
            </li>
            <li class="menu-item">
                <a href="student_personal_analysis.php" class="<?php echo $need_recommit ? 'menu-disabled' : ''; ?>">我的学习报告</a>
            </li>
            
            <li class="menu-item">
                <a href="student_commitment.php" class="<?php echo $need_recommit ? 'commitment-required' : ''; ?>">
                    <?php echo $need_recommit ? '⚠️ 重新签署承诺书（必须同意）' : '查看/重新签署承诺书'; ?>
                </a>
            </li>
        </ul>

        <!-- 退出按钮 -->
        <div style="text-align: center;">
            <button class="logout-btn" onclick="location.href='logout.php'">退出登录</button>
        </div>
    </div>

    <!-- 底部信息 -->
    <div class="footer">
        <p>学生学习管理系统 | 茂名市人工智能赋能教学项目 &copy; <?php echo date('Y'); ?></p>
    </div>
</body>
</html>