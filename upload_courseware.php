<?php
session_start();
include 'config.php';

// 管理员权限验证
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = 'success';

// 上传配置
$upload_dir = 'courseware/';
$allowed_types = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'image/jpeg', 'image/png', 'image/gif', 'image/webp'
];
$allowed_exts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
$max_size = 10 * 1024 * 1024;

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 处理上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    $title = trim($_POST['title']);
    $file = $_FILES['courseware_file'];

    if (empty($title)) {
        $message = "请输入课件名称";
        $message_type = "error";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "文件上传失败";
        $message_type = "error";
    } elseif ($file['size'] > $max_size) {
        $message = "文件不能超过10MB";
        $message_type = "error";
    } elseif (!in_array($file['type'], $allowed_types)) {
        $message = "不支持该文件类型";
        $message_type = "error";
    } else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), $allowed_exts)) {
            $message = "不支持的文件格式";
            $message_type = "error";
        } else {
            $filename = uniqid() . '.' . $ext;
            $dest_path = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                $stmt = $conn->prepare("INSERT INTO courseware (title, filename, original_name, upload_time) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("sss", $title, $filename, $file['name']);

                if ($stmt->execute()) {
                    $message = "课件上传成功！";
                } else {
                    $message = "保存失败：" . $conn->error;
                    $message_type = "error";
                    unlink($dest_path);
                }
                $stmt->close();
            } else {
                $message = "文件保存失败，请检查目录权限";
                $message_type = "error";
            }
        }
    }
}

// 删除
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $res = $conn->query("SELECT filename FROM courseware WHERE id=$id");
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $file = $upload_dir . $row['filename'];
        if (file_exists($file)) unlink($file);
        $conn->query("DELETE FROM courseware WHERE id=$id");
        $message = "删除成功";
    }
}

// 分页
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$total = $conn->query("SELECT COUNT(*) AS cnt FROM courseware")->fetch_assoc()['cnt'];
$total_pages = ceil($total / $limit);
$list = $conn->query("SELECT * FROM courseware ORDER BY id DESC LIMIT $offset, $limit");
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>课件上传管理</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Microsoft YaHei', 'Segoe UI', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            color: #fff;
            padding: 24px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .nav a {
            color: #fff;
            text-decoration: none;
            margin-left: 16px;
            padding: 6px 12px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            transition: 0.3s;
        }

        .nav a:hover {
            background: rgba(255,255,255,0.3);
        }

        .content {
            padding: 30px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-weight: 500;
        }

        .alert.success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .alert.error {
            background: #ffebee;
            color: #c62828;
        }

        .card {
            border: 1px solid #eef2f5;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            background: #fdfdfd;
        }

        .card h3 {
            margin-bottom: 18px;
            color: #333;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        form .form-group {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-width: 500px;
        }

        form label {
            font-weight: 500;
            color: #333;
        }

        form input, form input[type="file"] {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 100%;
            font-size: 14px;
        }

        form input[type="submit"] {
            background: #007bff;
            color: #fff;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: 0.3s;
            max-width: 150px;
        }

        form input[type="submit"]:hover {
            background: #0056b3;
        }

        form small {
            color: #666;
            font-size: 13px;
        }

        /* 表格核心优化：固定列宽、对齐、不换行 */
        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            table-layout: fixed; /* 关键：固定列宽，防止内容撑开 */
        }

        th {
            background: #f5f7fa;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            color: #444;
            font-size: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            vertical-align: middle;
            word-break: break-word; /* 长文本自动换行，不撑破表格 */
            overflow: hidden;
        }

        /* 列宽分配：按需调整 */
        th:nth-child(1), td:nth-child(1) { width: 60px; text-align: center; } /* ID */
        th:nth-child(2), td:nth-child(2) { width: 35%; } /* 课件名称 */
        th:nth-child(3), td:nth-child(3) { width: 35%; } /* 原文件名 */
        th:nth-child(4), td:nth-child(4) { width: 150px; text-align: center; } /* 上传时间 */
        th:nth-child(5), td:nth-child(5) { width: 140px; text-align: center; } /* 操作 */

        .btn-view {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            margin-right: 12px;
        }

        .btn-del {
            color: #e53935;
            text-decoration: none;
            font-weight: 500;
        }

        .btn-view:hover, .btn-del:hover {
            text-decoration: underline;
        }

        .pagination {
            margin-top: 20px;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-start;
        }

        .pagination a, .pagination b {
            padding: 8px 14px;
            border-radius: 8px;
            background: #f5f7fa;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            min-width: 40px;
            text-align: center;
        }

        .pagination b {
            background: #007bff;
            color: #fff;
        }

        .pagination a:hover {
            background: #e9ecef;
        }

        /* 响应式适配 */
        @media (max-width: 768px) {
            table {
                table-layout: auto;
            }
            th, td {
                padding: 10px 8px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📁 上课课件管理</h1>
            <div class="nav">
                <a href="admin_menu.php">返回主菜单</a>
                <a href="logout.php">退出登录</a>
            </div>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert <?=$message_type?>">
                    <?=$message?>
                </div>
            <?php endif; ?>

            <div class="card">
                <h3>⬆️ 上传新课件</h3>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>课件名称</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group">
                        <label>选择文件</label>
                        <input type="file" name="courseware_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp" required>
                        <small>支持：PDF、Word、PPT、图片，最大10MB</small>
                    </div>
                    <input type="submit" name="upload_file" value="上传课件">
                </form>
            </div>

            <div class="card">
                <h3>📋 课件列表</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>课件名称</th>
                            <th>原文件名</th>
                            <th>上传时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $list->fetch_assoc()): ?>
                        <tr>
                            <td><?=$row['id']?></td>
                            <td><?=htmlspecialchars($row['title'])?></td>
                            <td><?=htmlspecialchars($row['original_name'])?></td>
                            <td><?=$row['upload_time']?></td>
                            <td>
                                <a href="courseware/<?=$row['filename']?>" target="_blank" class="btn-view">查看/下载</a>
                                <a href="?delete_id=<?=$row['id']?>" class="btn-del" onclick="return confirm('确定删除？')">删除</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($total == 0): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:20px;">暂无课件</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="pagination">
                    <?php for($i=1;$i<=$total_pages;$i++): ?>
                        <?php if($i==$page): ?>
                            <b><?=$i?></b>
                        <?php else: ?>
                            <a href="?page=<?=$i?>"><?=$i?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>