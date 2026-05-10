<?php
session_start();
include 'config.php';

// 管理员权限验证
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit();
}

$message = '';
$messageType = '';

// ============== 1. 添加新宠物 ==============
if (isset($_POST['add_pet'])) {
    $pet_name = mysqli_real_escape_string($conn, $_POST['pet_name']);
    $pet_type = mysqli_real_escape_string($conn, $_POST['pet_type']);
    $pet_image = mysqli_real_escape_string($conn, $_POST['pet_image']);
    $pet_image_stage2 = mysqli_real_escape_string($conn, $_POST['pet_image_stage2']);
    $pet_image_stage3 = mysqli_real_escape_string($conn, $_POST['pet_image_stage3']);
    $unlock_points = (int)$_POST['unlock_points'];
    $is_rare = (int)$_POST['is_rare'];
    $need_card_count = (int)$_POST['need_card_count'];
    
    // 修复：同时获取 总限量 + 库存
    $total_limit = (int)$_POST['total_limit'];
    $stock = (int)$_POST['stock'];

    $sql = "INSERT INTO pet_config 
    (pet_name, pet_type, pet_image, pet_image_stage2, pet_image_stage3, 
    unlock_points, is_rare, need_card_count, total_limit, stock)
    VALUES 
    ('$pet_name','$pet_type','$pet_image','$pet_image_stage2','$pet_image_stage3',
    '$unlock_points','$is_rare','$need_card_count','$total_limit','$stock')";

    if (mysqli_query($conn, $sql)) {
        $message = "✅ 宠物添加成功！";
        $messageType = "success";
    } else {
        $message = "❌ 添加失败：" . mysqli_error($conn);
        $messageType = "error";
    }
}

// ============== 2. 修改宠物信息 ==============
if (isset($_POST['update_pet'])) {
    $id = (int)$_POST['id'];
    $pet_name = mysqli_real_escape_string($conn, $_POST['pet_name']);
    $pet_type = mysqli_real_escape_string($conn, $_POST['pet_type']);
    $pet_image = mysqli_real_escape_string($conn, $_POST['pet_image']);
    $pet_image_stage2 = mysqli_real_escape_string($conn, $_POST['pet_image_stage2']);
    $pet_image_stage3 = mysqli_real_escape_string($conn, $_POST['pet_image_stage3']);
    $unlock_points = (int)$_POST['unlock_points'];
    $is_rare = (int)$_POST['is_rare'];
    $need_card_count = (int)$_POST['need_card_count'];
    
    // 修复：同时获取 总限量 + 库存
    $total_limit = (int)$_POST['total_limit'];
    $stock = (int)$_POST['stock'];

    $sql = "UPDATE pet_config SET 
            pet_name='$pet_name',
            pet_type='$pet_type',
            pet_image='$pet_image',
            pet_image_stage2='$pet_image_stage2',
            pet_image_stage3='$pet_image_stage3',
            unlock_points='$unlock_points',
            is_rare='$is_rare',
            need_card_count='$need_card_count',
            total_limit='$total_limit',
            stock='$stock'
            WHERE id=$id";

    if (mysqli_query($conn, $sql)) {
        $message = "✅ 宠物信息修改成功！";
        $messageType = "success";
    } else {
        $message = "❌ 修改失败：" . mysqli_error($conn);
        $messageType = "error";
    }
}

// ============== 3. 删除宠物配置 ==============
if (isset($_GET['del_pet'])) {
    $id = (int)$_GET['del_pet'];
    mysqli_query($conn, "DELETE FROM pet_config WHERE id=$id");
    $message = "✅ 宠物已删除";
    $messageType = "success";
}

// ============== 4. 删除学生宠物 ==============
if (isset($_GET['del_student_pet'])) {
    $id = (int)$_GET['del_student_pet'];
    mysqli_query($conn, "DELETE FROM student_pets WHERE id=$id");
    $message = "✅ 已删除该学生宠物";
    $messageType = "success";
}

// ============== 5. 清空所有学生宠物 ==============
if (isset($_GET['clear_all_pets'])) {
    mysqli_query($conn, "TRUNCATE TABLE student_pets");
    mysqli_query($conn, "TRUNCATE TABLE pet_interact_logs");
    $message = "✅ 已清空所有学生宠物数据";
    $messageType = "success";
}

// ============== 获取要编辑的宠物信息 ==============
$edit_pet = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_pet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pet_config WHERE id=$id"));
}

// ============== 加载数据 ==============
$pet_list = mysqli_query($conn, "SELECT * FROM pet_config ORDER BY id DESC");
$student_pets = mysqli_query($conn, "
    SELECT sp.*, s.name, s.class, pc.pet_name 
    FROM student_pets sp
    LEFT JOIN students s ON sp.student_id = s.student_id
    LEFT JOIN pet_config pc ON sp.pet_id = pc.id
    ORDER BY sp.id DESC
");
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>宠物管理系统</title>
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
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
        }

        .back-btn {
            color: #fff;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
        }

        .main {
            padding: 30px;
        }

        .msg {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .msg.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .msg.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #007bff;
        }

        .box h2 {
            color: #007bff;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }

        .form-item label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 14px;
        }

        .form-item input, .form-item select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .btn {
            background: #007bff;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-danger {
            background: #dc3545;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 10px;
        }

        th, td {
            padding: 10px 12px;
            text-align: center;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        th {
            background: #007bff;
            color: #fff;
        }

        tr:hover {
            background: #f5f7ff;
        }

        .img-preview {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .action-bar {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }

        a {
            text-decoration: none;
        }

        .rare-tag {
            color: #ff5722;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🐾 宠物积分系统管理</h1>
        <a href="admin_menu.php" class="back-btn">返回管理菜单</a>
    </div>

    <div class="main">
        <?php if ($message): ?>
            <div class="msg <?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- ====================== 添加/修改宠物 ====================== -->
        <div class="box">
            <?php if ($edit_pet): ?>
                <h2>✏️ 修改宠物信息</h2>
                <form method="post">
                    <input type="hidden" name="id" value="<?php echo $edit_pet['id']; ?>">
                    <div class="form-grid">
                        <div class="form-item">
                            <label>宠物名称</label>
                            <input name="pet_name" value="<?php echo $edit_pet['pet_name']; ?>" required>
                        </div>
                        <div class="form-item">
                            <label>类型（猫/狗/兔）</label>
                            <input name="pet_type" value="<?php echo $edit_pet['pet_type']; ?>" required>
                        </div>
                        <div class="form-item">
                            <label>形态1图片</label>
                            <input name="pet_image" value="<?php echo $edit_pet['pet_image']; ?>" required>
                        </div>
                        <div class="form-item">
                            <label>形态2图片</label>
                            <input name="pet_image_stage2" value="<?php echo $edit_pet['pet_image_stage2']; ?>">
                        </div>
                        <div class="form-item">
                            <label>形态3图片</label>
                            <input name="pet_image_stage3" value="<?php echo $edit_pet['pet_image_stage3']; ?>">
                        </div>
                        <div class="form-item">
                            <label>解锁需要积分</label>
                            <input name="unlock_points" type="number" value="<?php echo $edit_pet['unlock_points']; ?>" required>
                        </div>
                        <div class="form-item">
                            <label>是否稀有宠物</label>
                            <select name="is_rare" required>
                                <option value="0" <?php if($edit_pet['is_rare']==0)echo'selected';?>>普通宠物</option>
                                <option value="1" <?php if($edit_pet['is_rare']==1)echo'selected';?>>稀有宠物</option>
                            </select>
                        </div>
                        <div class="form-item">
                            <label>需多少张卡片解锁</label>
                            <input name="need_card_count" type="number" value="<?php echo $edit_pet['need_card_count']; ?>" required>
                        </div>

                        <!-- 修复：总限量 + 库存 两个都显示 -->
                        <div class="form-item">
                            <label>全服总限量（0=无限）</label>
                            <input name="total_limit" type="number" value="<?php echo $edit_pet['total_limit']; ?>" required>
                        </div>
                        <div class="form-item">
                            <label>当前剩余库存</label>
                            <input name="stock" type="number" value="<?php echo $edit_pet['stock']; ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="update_pet" class="btn btn-warning">💾 保存修改</button>
                    <a href="?" class="btn" style="margin-left:10px;">取消</a>
                </form>
            <?php else: ?>
                <h2>➕ 添加新宠物（支持三形态进化 + 稀有库存限制）</h2>
                <form method="post">
                    <div class="form-grid">
                        <div class="form-item">
                            <label>宠物名称</label>
                            <input name="pet_name" required>
                        </div>
                        <div class="form-item">
                            <label>类型（猫/狗/兔）</label>
                            <input name="pet_type" required>
                        </div>
                        <div class="form-item">
                            <label>形态1图片</label>
                            <input name="pet_image" value="pets/cat.png" required>
                        </div>
                        <div class="form-item">
                            <label>形态2图片</label>
                            <input name="pet_image_stage2" value="pets/cat2.png">
                        </div>
                        <div class="form-item">
                            <label>形态3图片</label>
                            <input name="pet_image_stage3" value="pets/cat3.png">
                        </div>
                        <div class="form-item">
                            <label>解锁需要积分</label>
                            <input name="unlock_points" type="number" value="10" required>
                        </div>
                        <div class="form-item">
                            <label>是否稀有宠物</label>
                            <select name="is_rare" required>
                                <option value="0">普通宠物</option>
                                <option value="1">稀有宠物</option>
                            </select>
                        </div>
                        <div class="form-item">
                            <label>需多少张卡片解锁</label>
                            <input name="need_card_count" type="number" value="0" required>
                        </div>

                        <!-- 修复：总限量 + 库存 两个都显示 -->
                        <div class="form-item">
                            <label>全服总限量（0=无限）</label>
                            <input name="total_limit" type="number" value="0" required>
                        </div>
                        <div class="form-item">
                            <label>当前剩余库存</label>
                            <input name="stock" type="number" value="0" required>
                        </div>
                    </div>
                    <button type="submit" name="add_pet" class="btn">✅ 添加宠物</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- ====================== 宠物配置列表 ====================== -->
        <div class="box">
            <h2>📋 系统宠物列表</h2>
            <table>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>类型</th>
                    <th>形态1</th>
                    <th>形态2</th>
                    <th>形态3</th>
                    <th>解锁积分</th>
                    <th>宠物品质</th>
                    <th>需卡片</th>
                    <th>总限量</th>
                    <th>库存</th>
                    <th>操作</th>
                </tr>
                <?php while ($p = mysqli_fetch_assoc($pet_list)): ?>
                <tr>
                    <td><?php echo $p['id']; ?></td>
                    <td><?php echo $p['pet_name']; ?></td>
                    <td><?php echo $p['pet_type']; ?></td>
                    <td><img src="<?php echo $p['pet_image']; ?>" class="img-preview"></td>
                    <td><img src="<?php echo $p['pet_image_stage2']; ?>" class="img-preview"></td>
                    <td><img src="<?php echo $p['pet_image_stage3']; ?>" class="img-preview"></td>
                    <td><?php echo $p['unlock_points']; ?></td>
                    <td class="<?php echo $p['is_rare'] ? 'rare-tag' : ''; ?>">
                        <?php echo $p['is_rare'] ? '⭐ 稀有' : '普通'; ?>
                    </td>
                    <td><?php echo $p['need_card_count']; ?> 张</td>
                    
                    <!-- 修复：显示总限量 + 剩余库存 -->
                    <td><?php echo $p['total_limit'] == 0 ? '♾️ 无限' : $p['total_limit'] ?></td>
                    <td><?php echo $p['stock'] ?> 个</td>

                    <td>
                        <a href="?edit=<?php echo $p['id']; ?>" class="btn btn-warning" style="padding:5px 10px;font-size:12px;margin-right:5px;">编辑</a>
                        <a href="?del_pet=<?php echo $p['id']; ?>" onclick="return confirm('确定删除？')" class="btn btn-danger" style="padding:5px 10px;font-size:12px;">删除</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <!-- ====================== 学生宠物管理 ====================== -->
        <div class="box">
            <h2>👨‍🎓 学生宠物管理</h2>
            <div class="action-bar">
                <a href="?clear_all_pets=1" onclick="return confirm('确定清空所有学生宠物？不可恢复！')" class="btn btn-danger">清空所有学生宠物</a>
            </div>
            <table>
                <tr>
                    <th>ID</th>
                    <th>学生</th>
                    <th>班级</th>
                    <th>宠物</th>
                    <th>昵称</th>
                    <th>等级</th>
                    <th>阶段</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
                <?php while ($sp = mysqli_fetch_assoc($student_pets)): ?>
                <tr>
                    <td><?php echo $sp['id']; ?></td>
                    <td><?php echo $sp['name']; ?>(<?php echo $sp['student_id']; ?>)</td>
                    <td><?php echo $sp['class']; ?></td>
                    <td><?php echo $sp['pet_name']; ?></td>
                    <td><?php echo $sp['pet_nickname']; ?></td>
                    <td>Lv.<?php echo $sp['level']; ?></td>
                    <td><?php echo $sp['stage']; ?>阶</td>
                    <td><?php echo $sp['is_sick'] ? '🤒 生病' : '😊 正常'; ?></td>
                    <td>
                        <a href="?del_student_pet=<?php echo $sp['id']; ?>" onclick="return confirm('确定删除？')" class="btn btn-danger" style="padding:5px 10px;font-size:12px;">删除</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

    </div>
</div>

</body>
</html>