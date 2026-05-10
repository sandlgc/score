<?php
header("Content-Type: text/html; charset=utf-8");

// 1. 视频存储配置（保持不变，与Nginx配置对应）
define('VIDEO_STORE_PATH', 'C:/xampp/htdocs/score2026/videos/');
define('ALLOWED_EXTENSIONS', ['mp4']);

// 2. 安全过滤（保持不变，防止路径遍历攻击）
function safeFilter($str) {
    $str = trim($str);
    $str = str_replace(['../', '..\\'], '', $str);
    return $str;
}

// 3. 获取视频文件名（保持不变，前端传参逻辑不变）
$videoName = isset($_GET['video_name']) ? safeFilter($_GET['video_name']) : '';
if (empty($videoName)) {
    http_response_code(400);
    exit('错误：缺少视频文件名');
}

// 4. 验证视频文件是否存在（保持不变，确保视频真实存在）
$videoFile = VIDEO_STORE_PATH . $videoName;
$videoExt = '';
foreach (ALLOWED_EXTENSIONS as $ext) {
    if (file_exists("{$videoFile}.{$ext}")) {
        $videoExt = $ext;
        $videoFile .= ".{$ext}";
        break;
    }
}
if (empty($videoExt) || !file_exists($videoFile)) {
    http_response_code(404);
    exit('错误：视频不存在');
}

// 5. 关键修改：重定向路径改为 score2026/videos/（匹配Nginx配置）
// 步骤1：获取带后缀的完整视频文件名
$videoFileName = basename($videoFile);
// 步骤2：拼接正确的Nginx 8080端口路径（指向score2026/videos/）
$nginxVideoUrl = "http://192.168.103.108:8080/score2026/videos/{$videoFileName}";
// 步骤3：302重定向，让浏览器直接请求Nginx获取视频
header("Location: {$nginxVideoUrl}");
// 可选：补充缓存头，提升播放体验
header("Cache-Control: max-age=604800"); // 7天缓存，与Nginx配置一致
exit;
?>