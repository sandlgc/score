<?php
// ========== 第一步：将PHP代码移到文件最顶部，无任何前置输出 ==========
session_start(); // 必须是文件第一行有效代码，无任何空格/换行/HTML
include 'config.php';

// 验证管理员身份（提前到最顶部，避免输出后跳转）
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit(); // 跳转后立即退出，避免后续输出
}

// 功能名称，这里以游戏积分为例
$functionName = 'game_points';
$message = '';
$messageType = '';


// 检查是否有状态切换请求
if (isset($_GET['toggle'])) {
    $toggle = $_GET['toggle'];
    if (in_array($toggle, ['enable', 'disable'])) {
        $newStatus = ($toggle == 'enable') ? 'enabled' : 'disabled';
        // 防SQL注入
        $functionNameEscaped = mysqli_real_escape_string($conn, $functionName);
        $newStatusEscaped = mysqli_real_escape_string($conn, $newStatus);
        
        // 更新状态
        $sql = "UPDATE points_status SET status = '$newStatusEscaped' WHERE function_name = '$functionNameEscaped'";
        if (mysqli_query($conn, $sql)) {
            $message = $toggle == 'enable' ? '游戏积分功能已开启' : '游戏积分功能已关闭';
            $messageType = 'success';
        } else {
            $message = '操作失败：' . mysqli_error($conn);
            $messageType = 'error';
        }
    }
}

// 获取当前状态
$functionNameEscaped = mysqli_real_escape_string($conn, $functionName);
$result = mysqli_query($conn, "SELECT status FROM points_status WHERE function_name = '$functionNameEscaped'");

if ($row = mysqli_fetch_assoc($result)) {
    $currentStatus = $row['status'];
} else {
    // 如果记录不存在，默认设置为禁用并插入记录
    $currentStatus = 'disabled';
    $insertSql = "INSERT INTO points_status (function_name, status) VALUES ('$functionNameEscaped', 'disabled')";
    mysqli_query($conn, $insertSql);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员后台 - 主菜单</title>
    <style>
        /* 全局样式重置 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Microsoft YaHei', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }

        /* 容器样式 */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* 头部样式 */
        .header {
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            color: #fff;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 2em;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info span {
            font-size: 1.1em;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: #fff;
        }

        /* 主内容区 */
        .main-content {
            padding: 30px;
        }

        /* 状态控制区 */
        .status-control {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #28a745;
        }

        .status-control h3 {
            font-size: 1.2em;
            color: #495057;
        }

        .status-btn {
            padding: 10px 25px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .status-btn.enable {
            background: #28a745;
            color: #fff;
        }

        .status-btn.enable:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
        }

        .status-btn.disable {
            background: #6c757d;
            color: #fff;
        }

        .status-btn.disable:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
        }

        /* 菜单分类样式 */
        .menu-section {
            margin-bottom: 35px;
        }

        .menu-section h2 {
            font-size: 1.5em;
            color: #007bff;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        /* 菜单网格布局 */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        /* 菜单项样式 */
        .menu-item {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: #333;
        }

        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
            border-color: #007bff;
        }

        .menu-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #e3f2fd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: #007bff;
        }

        .menu-text h3 {
            font-size: 1.1em;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .menu-text p {
            font-size: 0.9em;
            color: #6c757d;
        }

        /* 消息提示样式 */
        #message {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            font-size: 1.1em;
            text-align: center;
            display: none;
        }

        #message.success {
            display: block;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        #message.error {
            display: block;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* 响应式适配 */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .status-control {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <!-- 引入图标库（可选，提升视觉效果） -->
    <link rel="stylesheet" href="fontawesome-free-6.4.0-web/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- 头部区域 -->
        <div class="header">
            <h1>管理员后台管理系统</h1>
            <div class="user-info">
                <span>当前登录：管理员</span>
                <a href="logout.php" class="logout-btn">退出登录</a>
            </div>
        </div>

        <!-- 主内容区域 -->
        <div class="main-content">
            <!-- 消息提示 -->
            <div id="message" class="<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>

            <!-- 积分功能状态控制 -->
            <div class="status-control">
                <h3>游戏积分功能状态：<?php echo $currentStatus == 'enabled' ? '✅ 已开启' : '❌ 已关闭'; ?></h3>
                <?php if ($currentStatus == 'disabled'): ?>
                    <a href="?toggle=enable">
                        <button class="status-btn enable">开启游戏积分</button>
                    </a>
                <?php else: ?>
                    <a href="?toggle=disable">
                        <button class="status-btn disable">关闭游戏积分</button>
                    </a>
                <?php endif; ?>
            </div>

            <!-- 积分管理分类 -->
            <div class="menu-section">
                <h2>📊 积分管理</h2>
                <div class="menu-grid">
                    <a href="manage_points.php" class="menu-item">
                        <div class="menu-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="menu-text">
                            <h3>学生加减积分</h3>
                            <p>手动调整学生积分、记录积分变动</p>
                        </div>
                    </a>
                    <a href="#" onclick="updatePoints(); return false;" class="menu-item">
                        <div class="menu-icon">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <div class="menu-text">
                            <h3>更新学生总积分</h3>
                            <p>批量更新学生累计积分数据</p>
                        </div>
                    </a>
                    <a href="index.php?debug=1&force_update=1" class="menu-item">
                        <div class="menu-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="menu-text">
                            <h3>强制更新排行榜缓存</h3>
                            <p>刷新答题排行榜数据</p>
                        </div>
                    </a>
                    <!-- 新增：学习行为管理菜单 -->
                    <a href="admin_overall_analysis.php" class="menu-item">
                        <div class="menu-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="menu-text">
                            <h3>学习行为分析</h3>
                            <p>查看和管理学生学习行为记录</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- 学生管理分类 -->
            <div class="menu-section">
                <h2>👨‍🎓 学生管理</h2>
                <div class="menu-grid">
                    <a href="add_student.php" class="menu-item">
                        <div class="menu-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="menu-text">
                            <h3>添加学生</h3>
                            <p>新增学生信息到系统</p>
                        </div>
                    </a>
                    <a href="manage_students.php" class="menu-item">
                        <div class="menu-icon">
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <div class="menu-text">
                            <h3>修改学生信息</h3>
                            <p>编辑学生基本信息</p>
                        </div>
                    </a>
                    <a href="delete_student.php" class="menu-item">
                        <div class="menu-icon">
                            <i class="fas fa-user-minus"></i>
                        </div>
                        <div class="menu-text">
                            <h3>删除学生</h3>
                            <p>移除系统中的学生记录</p>
                        </div>
                    </a>
                    <a href="import_excel.php" class="menu-item">
                        <div class="menu-icon">
                            <i class="fas fa-file-import"></i>
                        </div>
                        <div class="menu-text">
                            <h3>导入学生</h3>
                            <p>通过Excel批量导入学生信息</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- 系统功能分类 -->
			<div class="menu-section">
			    <h2>⚙️ 系统功能</h2>
			    <div class="menu-grid">
			        <a href="random_roll_call.php" class="menu-item">
			            <div class="menu-icon">
			                <i class="fas fa-random"></i>
			            </div>
			            <div class="menu-text">
			                <h3>随机点名</h3>
			                <p>随机抽取学生进行点名</p>
			            </div>
			        </a>
			        <a href="admin_config.php" class="menu-item">
			            <div class="menu-icon">
			                <i class="fas fa-cog"></i>
			            </div>
			            <div class="menu-text">
			                <h3>系统配置</h3>
			                <p>配置系统全局参数</p>
			            </div>
			        </a>
			        <a href="admin_commitment_manage.php" class="menu-item">
			            <div class="menu-icon">
			                <i class="fas fa-file-signature"></i>
			            </div>
			            <div class="menu-text">
			                <h3>课堂纪律承诺书统计</h3>
			                <p>查看和管理学生承诺书签署情况</p>
			            </div>
			        </a>
			        <a href="http://192.168.103.108/QVS2026/import_questions.html" class="menu-item">
			            <div class="menu-icon">
			                <i class="fas fa-question-circle"></i>
			            </div>
			            <div class="menu-text">
			                <h3>导入题目</h3>
			                <p>批量导入答题系统题目</p>
			            </div>
			        </a>
			        <!-- ✅ 新增：课件上传管理 -->
			        <a href="upload_courseware.php" class="menu-item">
			            <div class="menu-icon">
			                <i class="fas fa-file-upload"></i>
			            </div>
			            <div class="menu-text">
			                <h3>课件上传管理</h3>
			                <p>上传Word/PPT/PDF/图片课件，支持查看与下载</p>
			            </div>
			        </a>
			        <a href="admin_release_homework.php" class="menu-item">
			            <div class="menu-icon">
			                <i class="fas fa-paper-plane"></i>
			            </div>
			            <div class="menu-text">
			                <h3>发布作业</h3>
			                <p>发布文字/图片作业，指定班级提交</p>
			            </div>
			        </a>
			        <a href="admin_homework_stats.php" class="menu-item">
					    <div class="menu-icon">
					        <i class="fas fa-tasks"></i>
					    </div>
					    <div class="menu-text">
					        <h3>作业完成统计</h3>
					        <p>查看作业提交率、已提交/未提交名单</p>
					    </div>
					</a>
					<a href="admin_pet_manage.php" class="menu-item">
					    <div class="menu-icon">
					        <i class="fas fa-cat"></i>
					    </div>
					    <div class="menu-text">
					        <h3>宠物系统管理</h3>
					        <p>添加宠物、管理学生宠物、清空数据</p>
					    </div>
					</a>
			        
			        <a href="admin_total_evaluation.php" class="menu-item">
			            <div class="menu-icon">
			                <i class="fas fa-chart-bar"></i>
			            </div>
			            <div class="menu-text">
			                <h3>导出学生总评成绩</h3>
			                <p>导出学生总评成绩</p>
			            </div>
			        </a>
			    </div>
			</div>
        </div>
    </div>

    <script>
        // 更新积分函数
        function updatePoints() {
            const messageDiv = document.getElementById('message');
            
            // 清空原有消息
            messageDiv.className = '';
            messageDiv.textContent = '正在更新积分...';
            messageDiv.style.display = 'block';

            fetch('update_points.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('网络请求失败');
                    }
                    return response.json();
                })
                .then(data => {
                    messageDiv.textContent = data.message;
                    if (data.error) {
                        messageDiv.className = 'error';
                    } else {
                        messageDiv.className = 'success';
                    }
                    // 3秒后自动隐藏消息
                    setTimeout(() => {
                        messageDiv.style.display = 'none';
                    }, 3000);
                })
                .catch(error => {
                    messageDiv.textContent = '更新积分时发生错误：' + error.message;
                    messageDiv.className = 'error';
                });
        }

        // 页面加载后自动隐藏初始消息（如果有）
        window.onload = function() {
            const messageDiv = document.getElementById('message');
            if (messageDiv.textContent && messageDiv.className) {
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 3000);
            }
        }
    </script>
</body>
</html>