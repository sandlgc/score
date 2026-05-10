<?php
/**
 * 视频列表自动生成页面（带学生登录验证 + 观看行为记录）
 */
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


// ========== 视频列表核心逻辑 ==========
header("Content-Type: text/html; charset=utf-8");

$videoDir = 'C:/xampp/htdocs/score2026/videos/';
$allowedExts = ['mp4', 'flv', 'm3u8', 'ts'];
$playUrl = 'video_play.php';

if (!is_dir($videoDir)) {
    die("<h2 style='color:red;'>错误：视频目录不存在 → {$videoDir}</h2>");
}

$videoFiles = [];
$dirHandle = opendir($videoDir);
if ($dirHandle) {
    while (($file = readdir($dirHandle)) !== false) {
        if ($file == '.' || $file == '..') continue;
        $fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowedExts)) {
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            $videoFiles[] = [
                'name' => $fileName,
                'full_name' => $file,
                'size' => round(filesize($videoDir . $file) / 1024 / 1024, 2)
            ];
        }
    }
    closedir($dirHandle);
}

usort($videoFiles, function($a, $b) {
    return strnatcmp($a['full_name'], $b['full_name']);
});
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>视频点播</title>
    <style>
        /* 保留原有样式，此处省略（和原代码一致） */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft Yahei", "PingFang SC", sans-serif;
        }
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
            height: 100vh;
            overflow: hidden;
        }
        .user-info-bar {
            position: absolute;
            top: 10px;
            right: 20px;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px 15px;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            font-size: 14px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logout-link {
            color: #dc3545;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .logout-link:hover {
            color: #c82333;
            text-decoration: underline;
        }
        .commitment-warning {
            position: absolute;
            top: 60px;
            left: 50%;
            transform: translateX(-50%);
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            font-size: 14px;
            z-index: 9998;
            max-width: 80%;
            text-align: center;
        }
        .commitment-warning a {
            color: #856404;
            font-weight: 500;
            text-decoration: underline;
        }
        .container {
            display: flex;
            gap: 20px;
            height: calc(100vh - 40px);
            max-width: 1920px;
            margin: 0 auto;
            margin-top: 20px;
        }
        .video-player-section {
            flex: 2;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
            padding: 25px;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .player-title {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 15px;
            font-weight: 500;
            border-left: 4px solid #4299e1;
            padding-left: 10px;
        }
        .video-player-wrapper {
            flex: 1;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: #000;
            min-height: 0;
        }
        #video-player {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .video-list-section {
            flex: 1;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .list-title {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 20px;
            font-weight: 500;
            border-left: 4px solid #38b2ac;
            padding-left: 10px;
        }
        .video-list-wrapper {
            flex: 1;
            overflow-y: auto;
            padding-right: 8px;
        }
        .video-list-wrapper::-webkit-scrollbar {
            width: 6px;
        }
        .video-list-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .video-list-wrapper::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }
        .video-list-wrapper::-webkit-scrollbar-thumb:hover {
            background: #4299e1;
        }
        .video-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .video-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            <?php if ($need_recommit): ?>
                opacity: 0.5;
                pointer-events: none;
            <?php endif; ?>
        }
        .video-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: #4299e1;
        }
        .video-item.active {
            border-color: #4299e1;
            background: #e8f4f8;
        }
        .video-name {
            font-size: 16px;
            color: #2d3748;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }
        .video-name:hover {
            color: #4299e1;
        }
        .video-info {
            font-size: 14px;
            color: #718096;
            display: flex;
            align-items: center;
        }
        .video-info::before {
            content: "📄";
            margin-right: 6px;
        }
        .empty-tip {
            text-align: center;
            color: #718096;
            font-size: 18px;
            padding: 40px 0;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        .page-title {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 28px;
            color: #2d3748;
            font-weight: 600;
            z-index: 10;
            background: rgba(245, 247, 250, 0.9);
            padding: 8px 24px;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        @media (max-width: 1200px) {
            .container {
                flex-direction: column;
                height: auto;
                margin-top: 80px;
            }
            body {
                overflow-y: auto;
                height: auto;
            }
            .video-player-section, .video-list-section {
                height: 60vh;
                margin-bottom: 20px;
            }
            .page-title {
                position: relative;
                top: 0;
                left: 0;
                transform: none;
                margin-bottom: 20px;
                text-align: center;
            }
            .user-info-bar {
                position: relative;
                top: 0;
                right: 0;
                margin: 0 auto 10px;
                width: fit-content;
            }
            .commitment-warning {
                position: relative;
                top: 0;
                left: 0;
                transform: none;
                margin: 0 auto 15px;
                max-width: 100%;
            }
        }
        @media (max-width: 768px) {
            .video-player-section, .video-list-section {
                height: 40vh;
            }
            .player-title, .list-title {
                font-size: 18px;
            }
            .video-name {
                font-size: 14px;
            }
            .page-title {
                font-size: 24px;
            }
        }
    </style>
    <script>
        // 全局变量：当前观看日志ID（用于更新结束时间）
        let currentLogId = 0;
        
        // 新增：调用PHP接口记录开始观看
        function recordWatchStart(videoName, videoFullName) {
            // 仅承诺书已同意时记录
            <?php if (!$need_recommit): ?>
                return new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'record_watch_start.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            if (xhr.status === 200) {
                                const response = JSON.parse(xhr.responseText);
                                if (response.code === 200) {
                                    resolve(response.data.log_id);
                                } else {
                                    console.error('记录观看开始失败：', response.msg);
                                    resolve(0);
                                }
                            } else {
                                console.error('请求失败：', xhr.status);
                                resolve(0);
                            }
                        }
                    };
                    xhr.send(`video_name=${encodeURIComponent(videoName)}&video_full_name=${encodeURIComponent(videoFullName)}`);
                });
            <?php else: ?>
                return Promise.resolve(0);
            <?php endif; ?>
        }

        // 新增：调用PHP接口更新结束观看
        function updateWatchEnd(logId) {
            if (logId <= 0) return;
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'update_watch_end.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status !== 200) {
                        console.error('更新观看结束时间失败：', xhr.status);
                    }
                }
            };
            xhr.send(`log_id=${logId}`);
        }

        async function playVideo(videoName, element) {
		    <?php if ($need_recommit): ?>
		        alert("请先签署并同意课堂纪律承诺书后再播放视频！");
		        return;
		    <?php endif; ?>
		
		    // 1. 优先停止当前正在播放/加载的视频（关键修复：确保旧视频完全停止）
		    const player = document.getElementById('video-player');
		    const source = document.getElementById('video-source');
		    if (!player.paused) {
		        player.pause(); // 暂停播放
		    }
		    source.src = ''; // 清空旧视频源，终止加载
		    player.load(); // 触发资源卸载
		
		    // 2. 停止上一个视频的观看记录（原有逻辑保留）
		    if (currentLogId > 0) {
		        updateWatchEnd(currentLogId);
		    }
		
		    // 3. 获取视频完整名称（原有逻辑保留）
		    const videoFullName = element.querySelector('.video-name').textContent.split('. ')[1];
		    
		    // 4. 记录本次观看开始（原有逻辑保留）
		    currentLogId = await recordWatchStart(videoName, videoFullName);
		
		    // 5. 加载并播放新视频（确保旧资源清空后再执行）
		    const playUrl = "<?php echo $playUrl; ?>?video_name=" + encodeURIComponent(videoName);
		    source.src = playUrl;
		    
		    // 关键修改1：强制设置静音，防止静音状态失效
		    player.muted = true;
		    
		    // 等待视频元数据加载完成后再播放，避免加载中断
		    player.onloadedmetadata = function() {
		        // 关键修改2：再次确认静音状态（双重保障）
		        player.muted = true;
		        player.play().catch(err => {
		            console.log('自动播放失败，请手动点击播放:', err);
		            // 可选：自动播放失败时，提示用户手动点击
		            alert('自动播放已触发，请点击视频播放器中的"播放"按钮继续观看');
		        });
		    };
		    player.load(); // 加载新视频源
		
		    // 6. 高亮选中项（原有逻辑保留）
		    const videoItems = document.querySelectorAll('.video-item');
		    videoItems.forEach(item => {
		        item.classList.remove('active');
		    });
		    element.classList.add('active');
		}

        // 页面关闭/离开时更新结束时间
        window.addEventListener('beforeunload', function() {
            if (currentLogId > 0) {
                updateWatchEnd(currentLogId);
            }
        });

        // 视频暂停/结束时也更新（可选：更精准记录）
        window.onload = function() {
		    const player = document.getElementById('video-player');
		    // 关键修改：页面加载完成后，直接设置静音（初始状态保障）
		    player.muted = true;
		
		    // 视频结束时更新
		    player.addEventListener('ended', function() {
		        if (currentLogId > 0) {
		            updateWatchEnd(currentLogId);
		            currentLogId = 0;
		        }
		    });
		    // 视频暂停时更新（可选）
		    player.addEventListener('pause', function() {
		        if (currentLogId > 0 && !player.paused) return; // 排除播放中的暂停（比如缓冲）
		        updateWatchEnd(currentLogId);
		    });
		
		    // 默认播放第一个视频（原有逻辑）
		    const emptyTip = document.querySelector('.empty-tip');
		    if (!emptyTip && !<?php echo $need_recommit ? 'true' : 'false'; ?>) {
		        const firstVideoItem = document.querySelector('.video-item');
		        if (firstVideoItem) {
		            const firstVideoName = firstVideoItem.getAttribute('data-video-name');
		            // 播放前已通过 player.muted = true 设置静音，无需额外操作
		            playVideo(firstVideoName, firstVideoItem);
		        }
		    }
		};
    </script>
</head>
<body>
    <!-- 学生登录信息栏 -->
    <div class="user-info-bar">
        <span>当前登录：<?php echo $student_name; ?>（学号：<?php echo $student_id; ?>）</span>
        <a href="student_menu.php" class="logout-link">返回菜单</a>
        <a href="logout.php" class="logout-link">退出登录</a>
    </div>

    <!-- 承诺书提示（仅未同意时显示） -->
    <?php if ($commitment_warning): ?>
    <div class="commitment-warning">
        ⚠️ <?php echo $commitment_warning; ?>
        <?php if ($need_recommit): ?>
            <a href="student_commitment.php">立即重新签署</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <h1 class="page-title">操作视频点播</h1>
    
    <div class="container">
        <!-- 左侧：视频播放区域 -->
		<div class="video-player-section">
		    <h2 class="player-title">视频播放</h2>
		    <div class="video-player-wrapper">
		        <!-- 关键修改：添加 muted 属性（强制静音），补充 playsinline 兼容移动端 -->
		        <video id="video-player" controls muted playsinline>
		            <source id="video-source" src="" type="video/mp4">
		            <div style="color: #fff; padding: 20px; text-align: center;">
		                您的浏览器不支持HTML5视频播放，请升级浏览器
		            </div>
		        </video>
		    </div>
		</div>

        <!-- 右侧：视频列表区域 -->
        <div class="video-list-section">
            <h2 class="list-title">视频列表</h2>
            
            <div class="video-list-wrapper">
                <?php if (empty($videoFiles)): ?>
                    <div class="empty-tip">暂无可用视频文件</div>
                <?php else: ?>
                    <div class="video-list">
                        <?php foreach ($videoFiles as $index => $video): ?>
                            <div class="video-item" 
                                data-video-name="<?php echo $video['name']; ?>"
                                onclick="playVideo(this.getAttribute('data-video-name'), this)">
                                <div class="video-name">
                                    <?php echo $index + 1 . '. ' . $video['full_name']; ?>
                                </div>
                                <div class="video-info">
                                    大小：<?php echo $video['size']; ?> MB
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>