<?php
include 'student_common.php';
$conn = studentInit();
$student_id = $_SESSION["user_id"];
$student_name = getStudentInfo($conn, $student_id);

// 获取作业ID
$homework_id = (int)$_GET['id'];
$hw = $conn->query("SELECT * FROM homework WHERE id=$homework_id")->fetch_assoc();
if (!$hw) exit("作业不存在");

$class = $conn->query("SELECT class FROM students WHERE student_id='$student_id'")->fetch_assoc()['class'];
$msg = '';

// 查询是否已提交 + 批改状态
$submit_record = $conn->query("SELECT * FROM homework_submit WHERE homework_id=$homework_id AND student_id='$student_id'")->fetch_assoc();
$has_submitted = $submit_record ? true : false;
$is_checked = $has_submitted ? $submit_record['checked_status'] : 0; // 🔥 新增：是否批改

// 🔥 已批改 → 禁止提交
if ($is_checked) {
    // 这里什么都不用做，下面页面会自动隐藏表单
}
// 提交逻辑（支持覆盖提交）
else if (isset($_POST['submit'])) {
    $type = $hw['submit_type'];
    $content = '';

    // 作业独立文件夹
    $homework_title = trim($hw['title']);
    $base_dir = 'homework_uploads/';
    $hw_dir = $base_dir . $homework_title . '/';

    if (!is_dir($base_dir)) mkdir($base_dir, 0755, true);
    if (!is_dir($hw_dir)) mkdir($hw_dir, 0755, true);

    // 文本提交
    if ($type == 'text') {
        $content = trim($_POST['content']);
    } 
    // 图片上传
    else {
        if ($_FILES['img']['error'] == 0) {
            $ext = pathinfo($_FILES['img']['name'], PATHINFO_EXTENSION);
            $filename = $homework_title . '_' . $class . '_' . $student_name . '.' . $ext;
            $save_path = $hw_dir . $filename;

            // 如果旧文件存在，先删除（覆盖）
            if (file_exists($save_path)) {
                unlink($save_path);
            }

            if (move_uploaded_file($_FILES['img']['tmp_name'], $save_path)) {
                $content = $save_path;
            }
        }
    }

    if ($content) {
        // 已提交 → 更新（覆盖）
        if ($has_submitted) {
            // 如果是图片，删除旧文件
            if ($submit_record['submit_type'] != 'text' && file_exists($submit_record['submit_content'])) {
                unlink($submit_record['submit_content']);
            }

            // 更新记录
            $conn->query("UPDATE homework_submit SET 
                submit_content='$content', 
                submit_type='$type' 
                WHERE homework_id=$homework_id AND student_id='$student_id'");

            $msg = "<div style='color:green'>✅ 覆盖提交成功！</div>";
        } 
        // 未提交 → 新增
        else {
            $conn->query("INSERT INTO homework_submit (homework_id,student_id,student_name,class,submit_content,submit_type)
                      VALUES ($homework_id,'$student_id','$student_name','$class','$content','$type')");

            $p = $hw['reward_points'];
            $conn->query("UPDATE students SET points=points+$p WHERE student_id='$student_id'");
            $conn->query("INSERT INTO point_logs (student_id,action,points_change) VALUES ('$student_id','提交作业',$p)");

            $msg = "<div style='color:green'>✅ 提交成功！获得 $p 积分</div>";
        }

        // 刷新显示最新内容
        header("Refresh:0");
        exit;
    } else {
        $msg = "<div style='color:red'>请输入内容</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>提交作业</title>
<style>
    body {background:#f4f4f9;font-family:Microsoft YaHei;padding:20px;}
    .box {
        width: 66.666%;
        max-width: 900px;
        margin:0 auto;
        background:#fff;
        padding:25px;
        border-radius:10px;
        box-shadow:0 2px 10px #0000001a;
    }
    h2 {color:#007BFF;margin-bottom:15px;}
    .hw-content {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 6px;
        margin: 12px 0 22px 0;
        line-height: 1.7;
        border-left: 3px solid #007BFF;
        font-size: 15px;
    }
    .submit-preview {
        background: #e3f2fd;
        padding: 15px;
        border-radius: 6px;
        margin: 12px 0;
        line-height: 1.7;
        border-left: 3px solid #2196f3;
        font-size: 15px;
    }
    .checked-info {
        background: #d1ecf1;
        padding: 12px 15px;
        border-radius: 6px;
        color: #0c5460;
        font-weight: bold;
        margin: 10px 0;
        border-left: 3px solid #0c5460;
    }
    .score-info {
        color: #007bff;
        font-weight: bold;
        margin: 5px 0;
    }
    .comment-info {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
        margin-top: 8px;
        color: #333;
    }
    .hw-content img, .submit-preview img {
        max-width: 100%;
        height: auto;
        border-radius: 6px;
        margin-top: 10px;
    }
    input,textarea,button {
        width:100%;
        padding:12px;
        margin:6px 0;
        border:1px solid #ddd;
        border-radius:6px;
        font-size:14px;
    }
    textarea{
        resize: vertical;
    }
    button {
        background:#007BFF;
        color:white;
        border:none;
        cursor:pointer;
        font-size:15px;
        padding:12px;
    }
    .back {margin-bottom:12px;}
    .back a {color:#007BFF;text-decoration:none;font-size:14px;}
</style>

<div class="box">
    <div class="back"><a href="student_homework.php">← 返回作业列表</a></div>
    <h2>提交作业：<?php echo $hw['title']; ?></h2>

    <div class="hw-content">
        <strong>📝 作业内容：</strong><br>
        <?php if(!empty($hw['text_content'])): ?>
            <?php echo nl2br($hw['text_content']); ?><br>
        <?php endif; ?>
        <?php if(!empty($hw['image_content'])): ?>
            <img src="<?php echo htmlspecialchars($hw['image_content']); ?>" alt="作业图片">
        <?php endif; ?>
    </div>

    <?php echo $msg; ?>

    <?php if($has_submitted): ?>
        <div class="submit-preview">
            <strong>✅ 你已提交的作业：</strong><br>
            <?php
            $my_content = trim($submit_record['submit_content']);
            $my_type = $submit_record['submit_type'];

            if($my_type == 'text'){
                echo nl2br($my_content);
            } else {
                ?>
                <img src="<?php echo htmlspecialchars($my_content); ?>" alt="我的提交">
            <?php } ?>
        </div>

        <!-- 🔥 已批改 → 显示批改信息 + 禁止提交 -->
        <?php if($is_checked): ?>
            <div class="checked-info">
                🎉 作业已批改，不可重新提交<br>
                <div class="score-info">得分：<?php echo $submit_record['score']; ?> 分</div>
                <div class="comment-info">
                    教师评语：<?php echo $submit_record['teacher_comment'] ? $submit_record['teacher_comment'] : '暂无评语'; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- 🔥 未批改 → 显示提交表单；已批改 → 隐藏表单 -->
    <?php if(!$is_checked): ?>
        <form method="post" enctype="multipart/form-data">
            <?php if ($hw['submit_type'] == 'text'): ?>
                <textarea name="content" rows="6" placeholder="可重新提交覆盖原有内容" required></textarea>
            <?php else: ?>
                <input type="file" name="img" accept="image/*" required>
            <?php endif; ?>
            <button type="submit" name="submit">
                <?php echo $has_submitted ? '重新提交（覆盖）' : '提交'; ?>
            </button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>