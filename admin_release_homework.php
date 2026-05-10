<?php
session_start();
include 'config.php';

// 管理员权限验证
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$msg = '';

// 定义上传目录（自动创建文件夹）
$upload_dir = 'homework_uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 发布作业
if (isset($_POST['action']) && $_POST['action'] == 'publish') {
    $title = trim($_POST['title']);
    $submit_type = $_POST['submit_type'];
    $class_name = trim($_POST['class_name']);
    $file_size_limit = (int)$_POST['file_size_limit'];
    $reward_points = (float)$_POST['reward_points'];
    $text_content = trim($_POST['text_content']);
    $text_content = htmlspecialchars($text_content);
    $image_content = '';
    $is_published = 1;

    // 上传图片
    if ($_FILES['image_content']['error'] === 0) {
        $ext = pathinfo($_FILES['image_content']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('hw_') . '.' . $ext;
        $dest = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image_content']['tmp_name'], $dest)) {
            $image_content = $dest;
        }
    }

    // 必须有文字 或 图片
    if ($title && ($text_content || $image_content) && $class_name) {
        $stmt = $conn->prepare("INSERT INTO homework 
            (title, text_content, image_content, submit_type, class_name, file_size_limit, admin_id, reward_points, is_published) 
            VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssiidi", $title, $text_content, $image_content, $submit_type, $class_name, $file_size_limit, $admin_id, $reward_points, $is_published);
        
        if ($stmt->execute()) {
            $msg = "<div style='color:#065f46;padding:14px;background:#ecfdf5;border-radius:8px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.1);font-weight:500;'>✅ 作业发布成功！学生提交奖励：$reward_points 积分</div>";
        } else {
            $msg = "<div style='color:#991b1b;padding:14px;background:#fef2f2;border-radius:8px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.1);'>❌ 发布失败：".$stmt->error."</div>";
        }
        $stmt->close();
    } else {
        $msg = "<div style='color:#991b1b;padding:14px;background:#fef2f2;border-radius:8px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.1);'>请填写标题、班级，并至少输入文字或上传一张图片</div>";
    }
}

// 编辑作业（保存）
if (isset($_POST['action']) && $_POST['action'] == 'update') {
    $id = (int)$_POST['id'];
    $title = trim($_POST['title']);
    $submit_type = $_POST['submit_type'];
    $class_name = trim($_POST['class_name']);
    $file_size_limit = (int)$_POST['file_size_limit'];
    $reward_points = (float)$_POST['reward_points'];
    $text_content = htmlspecialchars(trim($_POST['text_content']));

    // 获取旧数据
    $old_row = $conn->query("SELECT image_content FROM homework WHERE id=$id")->fetch_assoc();
    $old_image = $old_row['image_content'];
    $image_content = $old_image; // 默认保留旧图片

    // 只有上传了新图片，才替换
    if ($_FILES['image_content']['error'] === 0) {
        $ext = pathinfo($_FILES['image_content']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('hw_') . '.' . $ext;
        $dest = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image_content']['tmp_name'], $dest)) {
            $image_content = $dest;
            
            // 删除旧图片
            if ($old_image && file_exists($old_image)) {
                @unlink($old_image);
            }
        }
    }

    $stmt = $conn->prepare("UPDATE homework SET 
        title=?, text_content=?, image_content=?, submit_type=?, class_name=?, file_size_limit=?, reward_points=? 
        WHERE id=?");
    $stmt->bind_param("sssssiid", $title, $text_content, $image_content, $submit_type, $class_name, $file_size_limit, $reward_points, $id);
    
    if ($stmt->execute()) {
        $msg = "<div style='color:#065f46;padding:14px;background:#ecfdf5;border-radius:8px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.1);'>✅ 作业已保存修改</div>";
    } else {
        $msg = "<div style='color:#991b1b;padding:14px;background:#fef2f2;border-radius:8px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.1);'>❌ 修改失败：".$stmt->error."</div>";
    }
    $stmt->close();
}

// 切换是否发布
if (isset($_GET['set_publish'])) {
    $id = (int)$_GET['set_publish'];
    $val = (int)$_GET['val'];
    $conn->query("UPDATE homework SET is_published = $val WHERE id = $id");
    $msg = "<div style='color:#065f46;padding:14px;background:#ecfdf5;border-radius:8px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.1);'>✅ 作业状态已更新</div>";
}

// 获取编辑数据
$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editRow = $conn->query("SELECT * FROM homework WHERE id=$id")->fetch_assoc();
}

// 获取班级
$classes = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");

// 获取作业
$homework_list = $conn->query("SELECT * FROM homework ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发布作业 - 学习积分管理系统</title>
    <link rel="stylesheet" href="fontawesome-free-6.4.0-web/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Microsoft YaHei', sans-serif;
        }

        body {
            background: #f8fafc;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 28px;
            margin-bottom: 28px;
            transition: all 0.2s;
        }

        .card:hover {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }

        .card-title {
            font-size: 22px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back {
            margin-bottom: 20px;
        }

        .back a {
            color: #2563eb;
            text-decoration: none;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .back a:hover {
            background: #eff6ff;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 16px;
            align-items: center;
            margin-bottom: 18px;
        }

        label {
            font-weight: 500;
            color: #334155;
            font-size: 15px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: border 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        button {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        button:hover {
            background: #1d4ed8;
        }

        .btn-cancel {
            margin-left: 12px;
            color: #64748b;
            text-decoration: none;
            font-size: 15px;
            padding: 12px 20px;
        }

        /* 🔥 图片预览优化：默认更大 + 鼠标悬停放大 */
        .preview-img {
            max-height: 280px; /* 默认更大 */
            max-width: 100%;
            border-radius: 10px;
            margin-top: 10px;
            border: 1px solid #e2e8f0;
            padding: 6px;
            background: #fafafa;
            cursor: zoom-in;
            transition: transform 0.3s ease;
        }
        .preview-img:hover {
            transform: scale(1.6); /* 鼠标移上去放大1.6倍 */
            z-index: 999;
            position: relative;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .img-container {
            margin-top: 10px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            display: inline-block;
            max-width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
        }

        th {
            background: #f1f5f9;
            padding: 14px;
            text-align: left;
            font-weight: 600;
            color: #334155;
            font-size: 15px;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
        }

        .badge-green {
            background: #dcfce7;
            color: #166534;
        }

        .badge-gray {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            color: #fff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-right: 4px;
        }

        .btn-blue {
            background: #2563eb;
        }

        .btn-orange {
            background: #f59e0b;
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="back">
        <a href="admin_menu.php"><i class="fas fa-arrow-left"></i> 返回管理菜单</a>
    </div>

    <div class="card">
        <div class="card-title">
            <i class="fas fa-paper-plane"></i>
            <?php if ($editRow): ?>编辑作业<?php else: ?>发布作业<?php endif; ?>
        </div>
        <?=$msg?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $editRow ? 'update' : 'publish'; ?>">
            <?php if ($editRow): ?>
                <input type="hidden" name="id" value="<?=$editRow['id']?>">
            <?php endif; ?>

            <div class="form-grid">
                <label>作业标题</label>
                <input type="text" name="title" value="<?=$editRow ? htmlspecialchars($editRow['title']) : ''?>" required placeholder="请输入作业标题">
            </div>

            <div class="form-grid">
                <label>文字内容</label>
                <textarea name="text_content" placeholder="请输入作业文字内容（选填）"><?=$editRow ? htmlspecialchars($editRow['text_content']) : ''?></textarea>
            </div>

            <!-- 图片上传 -->
            <div class="form-grid" style="align-items:flex-start;">
                <label>作业图片</label>
                <div>
                    <input type="file" name="image_content" accept="image/*">
                    <?php if ($editRow && !empty($editRow['image_content'])): ?>
                        <div class="img-container">
                            <small>当前图片（鼠标悬停放大）：</small><br>
                            <img src="<?=htmlspecialchars($editRow['image_content'])?>" class="preview-img">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-grid">
                <label>提交格式</label>
                <select name="submit_type" required>
                    <option value="text" <?php if ($editRow && $editRow['submit_type']=='text') echo 'selected' ?>>文字</option>
                    <option value="image" <?php if ($editRow && $editRow['submit_type']=='image') echo 'selected' ?>>图片</option>
                </select>
            </div>

            <div class="form-grid">
                <label>指定班级</label>
                <select name="class_name" required>
                    <option value="">请选择班级</option>
                    <?php 
                    $classes->data_seek(0);
                    while($c = $classes->fetch_assoc()): ?>
                        <option value="<?=$c['class']?>" <?=$editRow && $editRow['class_name']==$c['class'] ? 'selected' : ''?>><?=$c['class']?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-grid">
                <label>大小限制(MB)</label>
                <input type="number" name="file_size_limit" value="<?=$editRow ? $editRow['file_size_limit'] : '2'?>" min="1" required>
            </div>

            <div class="form-grid">
                <label>提交奖励积分</label>
                <input type="number" step="0.5" name="reward_points" value="<?=$editRow ? $editRow['reward_points'] : '2'?>" min="0" required>
            </div>

            <div style="margin-top: 24px;">
                <button type="submit">
                    <i class="fas fa-paper-plane"></i>
                    <?php echo $editRow ? '保存修改' : '发布作业'; ?>
                </button>
                <?php if ($editRow): ?>
                    <a href="admin_release_homework.php" class="btn-cancel">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-title"><i class="fas fa-list"></i> 作业列表</div>
        <table>
            <tr>
                <th>作业标题</th>
                <th>班级</th>
                <th>奖励积分</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
            <?php while($hw = $homework_list->fetch_assoc()): ?>
            <tr>
                <td><?=htmlspecialchars($hw['title'])?></td>
                <td><?=$hw['class_name']?></td>
                <td style="color:#16a34a; font-weight:500;"><?=$hw['reward_points']?> 分</td>
                <td>
                    <?php if ($hw['is_published']): ?>
                        <span class="badge badge-green">已发布</span>
                    <?php else: ?>
                        <span class="badge badge-gray">未发布</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="?edit=<?=$hw['id']?>" class="btn-action btn-blue"><i class="fas fa-edit"></i> 编辑</a>
                    <?php if ($hw['is_published']): ?>
                        <a href="?set_publish=<?=$hw['id']?>&val=0" class="btn-action btn-orange" onclick="return confirm('确定取消发布？学生将看不到此作业')">取消发布</a>
                    <?php else: ?>
                        <a href="?set_publish=<?=$hw['id']?>&val=1" class="btn-action btn-blue" onclick="return confirm('确定发布？学生将看到此作业')">发布</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

</body>
</html>