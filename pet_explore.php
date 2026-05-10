<?php
session_start();
include 'config.php';

// 登录验证
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$student = $conn->query("SELECT * FROM students WHERE student_id='$student_id'")->fetch_assoc();
$my_points = $student['points'];

// 风格继承
$style = 'clean';
if (isset($_COOKIE['pet_style'])) {
    $style = $_COOKIE['pet_style'];
}

// 获取所有同学的宠物（包括自己）
$all_pets = $conn->query("
    SELECT sp.*, pc.pet_name, pc.pet_type, pc.pet_image, s.name as owner_name
    FROM student_pets sp
    JOIN pet_config pc ON sp.pet_id = pc.id
    JOIN students s ON sp.student_id = s.student_id
    ORDER BY sp.student_id = '$student_id' DESC, sp.level DESC
");

// 选择组队宠物
$team_pet_ids = $_POST['team_pets'] ?? [];
if (!is_array($team_pet_ids)) $team_pet_ids = [];

// 开始探索
$message = '';
$msg_type = '';

if (isset($_POST['start_explore'])) {
    $cost = 5;
    
    // 1. 至少选1只
    if (count($team_pet_ids) < 1) {
        $message = "⚠️ 请至少选择1只宠物组队探索！";
        $msg_type = "error";
    } 
    // 2. 必须包含自己的宠物
    else {
        $has_my_pet = false;
        foreach ($team_pet_ids as $pid) {
            $check = $conn->query("SELECT student_id FROM student_pets WHERE id=$pid")->fetch_assoc();
            if ($check['student_id'] == $student_id) {
                $has_my_pet = true;
                break;
            }
        }
        
        if (!$has_my_pet) {
            $message = "⚠️ 必须带上自己的宠物才能组队，不能只选择其他同学的宠物！";
            $msg_type = "error";
        } elseif ($my_points < $cost) {
            $message = "❌ 积分不足，探索需要消耗 $cost 积分！";
            $msg_type = "error";
        } else {
            // 扣除积分
            $conn->query("UPDATE students SET points = points - $cost WHERE student_id='$student_id'");
            $conn->query("INSERT INTO point_logs (student_id, action, points_change) VALUES ('$student_id', '宠物组队探索', -$cost)");

            $team_strength = 0;
            $pet_count = count($team_pet_ids);
            foreach ($team_pet_ids as $pid) {
                $p = $conn->query("SELECT level FROM student_pets WHERE id=$pid")->fetch_assoc();
                $team_strength += $p['level'] ?? 1;
            }

            $base_reward = rand(6, 12);
            $team_bonus = $pet_count * 2;
            $total_reward = $base_reward + $team_bonus;

            $conn->query("UPDATE students SET points = points + $total_reward WHERE student_id='$student_id'");
            $conn->query("INSERT INTO point_logs (student_id, action, points_change) VALUES ('$student_id', '宠物探索获得积分', +$total_reward)");

            $events = [
                "团队友善互助，顺利完成文明探索任务！",
                "大家和谐合作，共同守护班级荣誉！",
                "宠物们团结一心，践行公正与诚信精神！",
                "队伍展现强大爱国精神，探索一路顺利！",
                "团队互相帮助，体现敬业与奉献品质！",
                "自由探索、平等协作，收获满满正能量！"
            ];
            $event = $events[array_rand($events)];

            $message = "✅ 探索成功！\n事件：$event\n消耗：$cost 积分 | 获得：$total_reward 积分\n团队力量：$team_strength | 组队数：$pet_count 只";
            $msg_type = "success";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>🔍 宠物团队寻宝（全班组队）</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .style-game {
            --bg: #121a2e;
            --card: #1a243f;
            --card2: #243052;
            --border: #3e4d77;
            --text: #fff;
            --text2: #a8b1c7;
            --accent: #00e5ff;
            --points: #ffd60a;
        }

        .style-clean {
            --bg: #f8f9fc;
            --card: #ffffff;
            --card2: #f1f5f9;
            --border: #e2e8f0;
            --text: #2d3748;
            --text2: #64748b;
            --accent: #3b82f6;
            --points: #f59e0b;
        }

        .style-cute {
            --bg: #fffafc;
            --card: #ffffff;
            --card2: #fef2f8;
            --border: #fbcfe8;
            --text: #553146;
            --text2: #9f6a8c;
            --accent: #ec4899;
            --points: #f97316;
        }

        body {
            font-family: 'Microsoft YaHei', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 15px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .game-header {
            text-align: center;
            padding: 12px;
            margin-bottom: 15px;
            background: var(--card);
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .game-title {
            font-size: 22px;
            color: var(--accent);
        }

        .user-bar {
            display: flex;
            justify-content: space-between;
            padding: 10px 14px;
            background: var(--card);
            border-radius: 10px;
            border: 1px solid var(--border);
            margin-bottom: 12px;
            font-size: 14px;
        }

        .points {
            color: var(--points);
            font-weight: bold;
        }

        .msg {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-weight: bold;
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-line;
        }

        .msg-success {
            background: #e6ffed;
            color: #00b42a;
            border: 1px solid #b6f5d0;
        }

        .msg-error {
            background: #fff1f0;
            color: #ff4d4f;
            border: 1px solid #ffccc7;
        }

        .panel {
            background: var(--card);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid var(--border);
        }

        .panel h2 {
            color: var(--accent);
            font-size: 16px;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--border);
        }

        .pet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
        }

        .pet-card {
            background: var(--card2);
            border-radius: 10px;
            padding: 12px 8px;
            text-align: center;
            border: 1px solid var(--border);
            transition: 0.2s;
            cursor: pointer;
            position: relative;
        }

        .pet-card.selected {
            border-color: var(--accent);
            background: rgba(0,150,255,0.15);
        }

        .pet-card img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            margin-bottom: 6px;
        }

        .pet-name {
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .pet-info {
            font-size: 12px;
            color: var(--text2);
            margin: 2px 0;
        }

        .owner-tag {
            font-size: 11px;
            color: #888;
            margin-top: 3px;
        }

        .rule-text {
            font-size: 13px;
            color: var(--text2);
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .explore-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }

        .back-btn {
            display: block;
            width: fit-content;
            margin: 10px auto;
            padding: 8px 16px;
            background: var(--accent);
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .pet-select-input {
            margin-top: 8px;
            transform: scale(1.3);
            cursor: pointer;
        }
    </style>
</head>

<body class="<?= 'style-' . $style ?>">
<div class="container">
    <div class="game-header">
        <h1 class="game-title">🔍 宠物团队寻宝（全班组队）</h1>
    </div>

    <div class="user-bar">
        <span>学员：<?= $student['name'] ?></span>
        <span class="points">⭐ 积分：<?= $my_points ?></span>
    </div>

    <?php if ($message): ?>
        <div class="msg msg-<?= $msg_type ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="panel">
        <h2>📜 探索规则（团队精神 · 价值观）</h2>
        <div class="rule-text">
            1. 必须选择【至少1只自己的宠物】<br>
            2. 可额外选择【全班同学的宠物】一起组队<br>
            3. 每次探索消耗 5 积分，可获得更多积分奖励<br>
            4. 组队越多，团队力量越强，奖励越高<br>
            5. 体现互帮互助、合作共赢的班级精神
        </div>
    </div>

    <form method="post">
        <div class="panel">
            <h2>🐾 全班宠物列表（点击组队）</h2>
            <div class="pet-grid">
                <?php if ($all_pets->num_rows > 0): ?>
                    <?php while ($pet = $all_pets->fetch_assoc()): ?>
                        <div class="pet-card" onclick="togglePet(this)">
                            <img src="<?= $pet['pet_image'] ?>">
                            <div class="pet-name"><?= $pet['pet_nickname'] ?></div>
                            <div class="pet-info">Lv.<?= $pet['level'] ?></div>
                            <div class="pet-info">类型：<?= $pet['pet_type'] ?></div>
                            <div class="owner-tag">主人：<?= $pet['owner_name'] ?></div>
                            <input type="checkbox" name="team_pets[]" value="<?= $pet['id'] ?>" class="pet-select-input">
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align:center; padding:20px;">暂无宠物，先去解锁吧！</div>
                <?php endif; ?>
            </div>

            <?php if ($all_pets->num_rows > 0): ?>
                <button type="submit" name="start_explore" class="explore-btn">🚀 开始团队寻宝（消耗5积分）</button>
            <?php endif; ?>
        </div>
    </form>

    <a href="pet.php" class="back-btn">返回宠物基地</a>
</div>

<script>
function togglePet(el) {
    const checkbox = el.querySelector('.pet-select-input');
    checkbox.checked = !checkbox.checked;
    
    if(checkbox.checked) {
        el.classList.add('selected');
    } else {
        el.classList.remove('selected');
    }
}
</script>
</body>
</html>