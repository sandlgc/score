<?php
session_start();
include 'config.php';

// 🔥 实时获取PK题目（修复：题目永不失效）
if (isset($_GET['get_question'])) {
    header('Content-Type: application/json');
    $q = $conn->query("SELECT id,question,option1,option2,option3,option4,answer FROM questions ORDER BY RAND() LIMIT 1")->fetch_assoc();
    echo json_encode($q);
    exit;
}

// 🔥 新增：供AJAX刷新卡片面板（纯数据返回，不返回HTML，避免布局破坏）
if (isset($_GET['refresh_cards_data'])) {
    header('Content-Type: application/json');
    $student_id = $_SESSION['user_id'];
    
    $my_cards = $conn->query("SELECT c.*, sc.obtained_at FROM student_cards sc JOIN core_values_cards c ON sc.card_id = c.id WHERE sc.student_id='$student_id' ORDER BY sc.id DESC LIMIT 12");
    $collected = $conn->query("SELECT COUNT(DISTINCT card_id) as cnt FROM student_cards WHERE student_id='$student_id'")->fetch_assoc()['cnt'];
    
    $cards = [];
    while ($card = $my_cards->fetch_assoc()) {
        $cards[] = $card;
    }
    
    echo json_encode([
        'cards' => $cards,
        'collected' => $collected,
        'total' => 12
    ]);
    exit;
}

// 关闭错误输出，避免污染 JSON（保留日志）
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 自动创建消息表
$conn->query("
CREATE TABLE IF NOT EXISTS pet_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_student_id VARCHAR(50) NOT NULL,
    from_student_id VARCHAR(50) NOT NULL,
    pet_name VARCHAR(100) NOT NULL,
    result VARCHAR(20) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

");

// 登录验证
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 清空稀有宠物解锁SESSION（取消用）
if (isset($_POST['clear_rare_session'])) {
    unset($_SESSION['rare_unlock_pet']);
    unset($_SESSION['rare_unlock_cards']);
    exit;
}

// 风格切换
$style = 'clean';
if (isset($_POST['style'])) {
    $style = $_POST['style'];
    setcookie('pet_style', $style, time() + 3600 * 24 * 7);
} elseif (isset($_COOKIE['pet_style'])) {
    $style = $_COOKIE['pet_style'];
}

$student_id = $_SESSION['user_id'];
$student = $conn->query("SELECT * FROM students WHERE student_id='$student_id'")->fetch_assoc();
$my_points = $student['points'];

// ======================
// 判断今日任务
// ======================
function isTodayTaskCompleted($student_id, $conn) {
    $today = date('Y-m-d');
    
    $quiz_done = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM point_logs 
        WHERE student_id = '$student_id' 
          AND action LIKE '%章答题结束%' 
          AND DATE(created_at) = '$today'
    ")->fetch_assoc()['cnt'] > 0;
    
    $homework_done = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM homework_submit 
        WHERE student_id = '$student_id' 
          AND DATE(submit_time) = '$today'
    ")->fetch_assoc()['cnt'] > 0;
    
    return $quiz_done && $homework_done;
}

// ------------------------------
// 每日签到
// ------------------------------
if (isset($_POST['sign'])) {
    $today = date('Y-m-d');
    $signed = $conn->query("SELECT id FROM sign_logs WHERE student_id='$student_id' AND sign_date='$today'")->num_rows;
    if ($signed) {
        $_SESSION['msg'] = "⚠️ 今天已经签到过啦！";
    } else {
        $reward = rand(3, 8);
        $conn->query("UPDATE students SET points = points + $reward WHERE student_id='$student_id'");
        $conn->query("INSERT INTO sign_logs (student_id, sign_date, reward_points) VALUES ('$student_id','$today',$reward)");
        $_SESSION['msg'] = "✅ 签到成功！获得积分：$reward 分";
    }
    header("Location: pet.php");
    exit;
}

// ------------------------------
// 解锁宠物（无感AJAX）
// ------------------------------
if (isset($_POST['ajax_unlock_pet'])) {
    header("Content-Type: application/json; charset=utf-8");
    $pet_id = (int)$_POST['pet_id'];
    $pet = $conn->query("SELECT * FROM pet_config WHERE id=$pet_id")->fetch_assoc();
    $cost = $pet['unlock_points'];

    $is_rare = $pet['is_rare'] ?? 0;
    $need_card_count = $pet['need_card_count'] ?? 0;
    $my_card_count = $conn->query("SELECT COUNT(DISTINCT card_id) as cnt FROM student_cards WHERE student_id='$student_id'")->fetch_assoc()['cnt'];
    $exists = $conn->query("SELECT id FROM student_pets WHERE student_id='$student_id' AND pet_id=$pet_id")->num_rows;

    if ($exists) {
        echo json_encode(['code' => 0, 'msg' => "⚠️ 你已经拥有这只宠物"]);
        exit;
    }
    if ($my_points < $cost) {
        echo json_encode(['code' => 0, 'msg' => "❌ 积分不足，需要：$cost 分"]);
        exit;
    }

    // ====================== 【新增：限量判断】 ======================
    $total_limit = $pet['total_limit'] ?? 0;
    $stock = $pet['stock'] ?? 0;
    
    // 如果是限量宠物 && 库存为0
    if ($total_limit > 0 && $stock <= 0) {
        echo json_encode(['code' => 0, 'msg' => "❌ 该稀有宠物已被兑换完毕！下次再来吧～"]);
        exit;
    }
    // ===============================================================

    // 稀有宠物
    if ($is_rare == 1) {
        if ($my_card_count < $need_card_count) {
            echo json_encode(['code' => 0, 'msg' => "⚠️ 解锁稀有宠物需要 $need_card_count 张卡片，你只有 $my_card_count 张"]);
            exit;
        }
        $my_cards = $conn->query("SELECT c.*, sc.id as sc_id FROM student_cards sc JOIN core_values_cards c ON sc.card_id = c.id WHERE sc.student_id='$student_id'");
        $_SESSION['rare_unlock_pet'] = [
            'pet_id' => $pet_id,
            'need_card_count' => $need_card_count,
            'pet_name' => $pet['pet_name']
        ];
        $_SESSION['rare_unlock_cards'] = $my_cards->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['code' => 2, 'msg' => "请选择卡片解锁"]);
        exit;
    }

    // 普通宠物直接解锁
    // ====================== 【新增：扣库存】 ======================
    if ($total_limit > 0) {
        $conn->query("UPDATE pet_config SET stock = stock - 1 WHERE id=$pet_id");
    }
    // ==============================================================
    
    $conn->query("UPDATE students SET points = points - $cost WHERE student_id='$student_id'");
    $conn->query("INSERT INTO point_logs (student_id, action, points_change) VALUES ('$student_id', '解锁宠物【{$pet['pet_name']}】', -$cost)");
    $conn->query("INSERT INTO student_pets
        (student_id, pet_id, pet_nickname, hunger, mood, created_at, rename_count, is_sick, exp, level, stage)
        VALUES ('$student_id', $pet_id, '{$pet['pet_name']}', 100, 100, NOW(), 0, 0, 0, 1, 1)");

    $new_points = $conn->query("SELECT points FROM students WHERE student_id='$student_id'")->fetch_assoc()['points'];
    echo json_encode([
        'code' => 1,
        'msg' => "✅ 解锁成功！获得新宠物",
        'points' => $new_points,
        'refresh' => 1
    ]);
    exit;
}

// ------------------------------
// 确认解锁稀有宠物（AJAX）
// ------------------------------
if (isset($_POST['ajax_confirm_rare_unlock'])) {
    header("Content-Type: application/json; charset=utf-8");
    $pet_id = (int)$_POST['pet_id'];
    $selected_card_ids = $_POST['selected_cards'] ?? [];
    $pet = $conn->query("SELECT * FROM pet_config WHERE id=$pet_id")->fetch_assoc();
    $cost = $pet['unlock_points'];
    $need = $pet['need_card_count'];

    if (count($selected_card_ids) != $need) {
        echo json_encode(['code' => 0, 'msg' => "⚠️ 必须选择 $need 张卡片！"]);
        exit;
    }
    if ($my_points < $cost) {
        echo json_encode(['code' => 0, 'msg' => "❌ 积分不足"]);
        exit;
    }

    // ====================== 【新增：再次检查库存】 ======================
    $total_limit = $pet['total_limit'] ?? 0;
    $stock = $pet['stock'] ?? 0;
    if ($total_limit > 0 && $stock <= 0) {
        echo json_encode(['code' => 0, 'msg' => "❌ 来晚了！该稀有宠物已被兑换一空！"]);
        exit;
    }
    // ==================================================================

    $conn->query("UPDATE students SET points = points - $cost WHERE student_id='$student_id'");
    $conn->query("INSERT INTO point_logs (student_id, action, points_change) VALUES ('$student_id', '解锁稀有宠物【{$pet['pet_name']}】', -$cost)");

    foreach ($selected_card_ids as $scid) {
        $scid = (int)$scid;
        $conn->query("DELETE FROM student_cards WHERE id=$scid AND student_id='$student_id'");
    }

    // ====================== 【新增：扣库存】 ======================
    if ($total_limit > 0) {
        $conn->query("UPDATE pet_config SET stock = stock - 1 WHERE id=$pet_id");
    }
    // ==============================================================

    $conn->query("INSERT INTO student_pets
        (student_id, pet_id, pet_nickname, hunger, mood, created_at, rename_count, is_sick, exp, level, stage)
        VALUES ('$student_id', $pet_id, '{$pet['pet_name']}', 100, 100, NOW(), 0, 0, 0, 1, 1)");

    $new_points = $conn->query("SELECT points FROM students WHERE student_id='$student_id'")->fetch_assoc()['points'];
    unset($_SESSION['rare_unlock_pet']);
    unset($_SESSION['rare_unlock_cards']);
    
    echo json_encode([
        'code' => 1,
        'msg' => "✅ 稀有宠物解锁成功！",
        'points' => $new_points,
        'refresh' => 1
    ]);
    exit;
}

// ------------------------------
// 宠物改名（AJAX）
// ------------------------------
if (isset($_POST['rename_pet'])) {
    $pid = (int)$_POST['pet_id'];
    $new_name = trim($_POST['new_name']);
    $pet = $conn->query("SELECT rename_count FROM student_pets WHERE id=$pid AND student_id='$student_id'")->fetch_assoc();

    if (mb_strlen($new_name) < 2 || mb_strlen($new_name) > 8) {
        echo json_encode(['code'=>0, 'msg'=>"⚠️ 名字长度必须在2-8个字之间"]);
        exit;
    }

    $cost = 0;
    $count = $pet['rename_count'];
    if ($count >= 1) $cost = 5;

    if ($my_points < $cost) {
        echo json_encode(['code'=>0, 'msg'=>"❌ 积分不足，改名需要 $cost 积分"]);
        exit;
    }

    if ($cost > 0) {
        $conn->query("UPDATE students SET points = points - $cost WHERE student_id='$student_id'");
        $conn->query("INSERT INTO point_logs (student_id, action, points_change) VALUES ('$student_id', '宠物改名', -$cost)");
    }

    $conn->query("UPDATE student_pets SET pet_nickname='$new_name', rename_count=rename_count+1 WHERE id=$pid AND student_id='$student_id'");
    $new_points = $conn->query("SELECT points FROM students WHERE student_id='$student_id'")->fetch_assoc()['points'];

    echo json_encode([
        'code'=>1,
        'msg'=>$cost==0 ? "✅ 改名成功！首次免费～" : "✅ 改名成功！消耗 5 积分",
        'name'=>$new_name,
        'points'=>$new_points
    ]);
    exit;
}

// ------------------------------
// 喂食（AJAX）
// ------------------------------
if (isset($_POST['feed'])) {
    $pid = (int)$_POST['pet_id'];
    $cost = 1;

    $pet = $conn->query("SELECT hunger,is_sick,level FROM student_pets WHERE id=$pid AND student_id='$student_id'")->fetch_assoc();
    $current_hunger = $pet['hunger'];
    $is_sick = $pet['is_sick'];
    $level = $pet['level'];

    if ($is_sick) {
        echo json_encode(['code'=>0, 'msg'=>"⚠️ 宠物生病了，先治疗再喂食！"]);
        exit;
    }

    $max_hunger = 100 + min($level * 5, 100);
    if ($current_hunger >= $max_hunger) {
        echo json_encode(['code'=>0, 'msg'=>"🍖 宠物已经吃饱啦！无法喂食"]);
        exit;
    }

    if ($my_points >= $cost) {
        $conn->query("UPDATE students SET points = points - $cost WHERE student_id='$student_id'");
        $conn->query("INSERT INTO point_logs (student_id, action, points_change) VALUES ('$student_id', '宠物喂食', -$cost)");

        $add_hunger = 8 + floor($level / 2);
        $add_exp = 1;

        $conn->query("UPDATE student_pets SET hunger = LEAST($max_hunger, hunger + $add_hunger), exp = exp + $add_exp WHERE id=$pid AND student_id='$student_id'");
        $conn->query("INSERT INTO pet_interact_logs (student_id, pet_id, action, points_cost, exp_gain) VALUES ('$student_id',$pid,'feed',1,$add_exp)");

        $new = $conn->query("SELECT hunger, exp, level FROM student_pets WHERE id=$pid")->fetch_assoc();
        $new_points = $conn->query("SELECT points FROM students WHERE student_id='$student_id'")->fetch_assoc()['points'];
        echo json_encode([
            'code'=>1,
            'msg'=>"🍖 喂食成功！饥饿+$add_hunger，经验+$add_exp",
            'hunger'=>$new['hunger'],
            'exp'=>$new['exp'],
            'level'=>$new['level'],
            'points'=>$new_points
        ]);
    } else {
        echo json_encode(['code'=>0, 'msg'=>"❌ 积分不足，喂食需要 1 分！"]);
    }
    exit;
}

// ------------------------------
// 玩耍（AJAX）
// ------------------------------
if (isset($_POST['play'])) {
    $pid = (int)$_POST['pet_id'];
    $cost = 1;

    $pet = $conn->query("SELECT mood,is_sick,level FROM student_pets WHERE id=$pid AND student_id='$student_id'")->fetch_assoc();
    $is_sick = $pet['is_sick'];
    $current_mood = $pet['mood'];
    $level = $pet['level'];

    if ($is_sick) {
        echo json_encode(['code'=>0, 'msg'=>"⚠️ 宠物生病了，先治疗再玩耍！"]);
        exit;
    }

    $max_mood = 100 + min($level * 5, 100);
    if ($current_mood >= $max_mood) {
        echo json_encode(['code'=>0, 'msg'=>"🎾 宠物心情已经很好啦！无法玩耍"]);
        exit;
    }

    if ($my_points >= $cost) {
        $conn->query("UPDATE students SET points = points - $cost WHERE student_id='$student_id'");
        $conn->query("INSERT INTO point_logs (student_id, action, points_change) VALUES ('$student_id', '宠物玩耍', -$cost)");

        $add_mood = 8 + floor($level / 2);
        $add_exp = 1;

        $conn->query("UPDATE student_pets SET mood = LEAST($max_mood, mood + $add_mood), exp = exp + $add_exp WHERE id=$pid AND student_id='$student_id'");
        $conn->query("INSERT INTO pet_interact_logs (student_id, pet_id, action, points_cost, exp_gain) VALUES ('$student_id',$pid,'play',1,$add_exp)");

        $new = $conn->query("SELECT mood, exp, level FROM student_pets WHERE id=$pid")->fetch_assoc();
        $new_points = $conn->query("SELECT points FROM students WHERE student_id='$student_id'")->fetch_assoc()['points'];
        echo json_encode([
            'code'=>1,
            'msg'=>"🎾 玩耍成功！心情+$add_mood，经验+$add_exp",
            'mood'=>$new['mood'],
            'exp'=>$new['exp'],
            'level'=>$new['level'],
            'points'=>$new_points
        ]);
    } else {
        echo json_encode(['code'=>0, 'msg'=>"❌ 积分不足，玩耍需要 1 分！"]);
    }
    exit;
}

// ------------------------------
// 使用食物（AJAX）
// ------------------------------
if (isset($_POST['use_food'])) {
    $pid = (int)$_POST['pet_id'];
    $cost = 2;
    $pet = $conn->query("SELECT is_sick,hunger,level FROM student_pets WHERE id=$pid AND student_id='$student_id'")->fetch_assoc();

    if ($pet['is_sick']) {
        echo json_encode(['code'=>0, 'msg'=>"⚠️ 宠物生病了，无法使用食物！"]);
        exit;
    }

    $max_hunger = 100 + min($pet['level'] * 5, 100);
    if ($pet['hunger'] >= $max_hunger) {
        echo json_encode(['code'=>0, 'msg'=>"🍱 宠物已经吃饱啦！无法使用食物"]);
        exit;
    }

    if ($my_points >= $cost) {
        $add_hunger = 30 + $pet['level'] * 2;
        $add_exp = 3 + $pet['level'];

        $conn->query("UPDATE students SET points = points - $cost WHERE student_id='$student_id'");
        $conn->query("INSERT INTO point_logs (student_id, action, points_change) VALUES ('$student_id', '使用食物', -$cost)");
        $conn->query("UPDATE student_pets SET hunger = LEAST($max_hunger, hunger + $add_hunger), exp = exp + $add_exp WHERE id=$pid AND student_id='$student_id'");

        $new = $conn->query("SELECT hunger, exp, level FROM student_pets WHERE id=$pid")->fetch_assoc();
        $new_points = $conn->query("SELECT points FROM students WHERE student_id='$student_id'")->fetch_assoc()['points'];
        echo json_encode([
            'code'=>1,
            'msg'=>"🍱 使用食物成功！饥饿+$add_hunger，经验+$add_exp",
            'hunger'=>$new['hunger'],
            'exp'=>$new['exp'],
            'level'=>$new['level'],
            'points'=>$new_points
        ]);
    } else {
        echo json_encode(['code'=>0, 'msg'=>"❌ 积分不足，使用食物需要 2 分！"]);
    }
    exit;
}

// ------------------------------
// 使用玩具（AJAX）
// ------------------------------
if (isset($_POST['use_toy'])) {
    $pid = (int)$_POST['pet_id'];
    $cost = 3;
    $pet = $conn->query("SELECT is_sick,mood,level FROM student_pets WHERE id=$pid AND student_id='$student_id'")->fetch_assoc();

    if ($pet['is_sick']) {
        echo json_encode(['code'=>0, 'msg'=>"⚠️ 宠物生病了，无法使用玩具！"]);
        exit;
    }

    $max_mood = 100 + min($pet['level'] * 5, 100);
    if ($pet['mood'] >= $max_mood) {
        echo json_encode(['code'=>0, 'msg'=>"🎁 宠物心情已经很好啦！无法使用玩具"]);
        exit;
    }

    if ($my_points >= $cost) {
        $add_mood = 15 + $level;
        $add_exp = 2;

        $conn->query("UPDATE students SET points = points - $cost WHERE student_id='$student_id'");
        $conn->query("INSERT INTO point_logs (student_id, action, points_change) VALUES ('$student_id', '使用玩具', -$cost)");
        $conn->query("UPDATE student_pets SET mood = LEAST($max_mood, mood + $add_mood), exp = exp + $add_exp WHERE id=$pid AND student_id='$student_id'");

        $new = $conn->query("SELECT mood, exp, level FROM student_pets WHERE id=$pid")->fetch_assoc();
        $new_points = $conn->query("SELECT points FROM students WHERE student_id='$student_id'")->fetch_assoc()['points'];
        echo json_encode([
            'code'=>1,
            'msg'=>"🎁 使用玩具成功！心情+$add_mood，经验+$add_exp",
            'mood'=>$new['mood'],
            'exp'=>$new['exp'],
            'level'=>$new['level'],
            'points'=>$new_points
        ]);
    } else {
        echo json_encode(['code'=>0, 'msg'=>"❌ 积分不足，使用玩具需要 3 分！"]);
    }
    exit;
}

// ------------------------------
// 治疗（AJAX）
// ------------------------------
if (isset($_POST['cure'])) {
    $pid = (int)$_POST['pet_id'];
    $cost = 5;
    if ($my_points >= $cost) {
        $conn->query("UPDATE students SET points = points - $cost WHERE student_id='$student_id'");
        $conn->query("INSERT INTO point_logs (student_id, action, points_change) VALUES ('$student_id', '治疗宠物', -$cost)");
        $conn->query("UPDATE student_pets SET is_sick=0, hunger=GREATEST(30, hunger), mood=GREATEST(30, mood) WHERE id=$pid AND student_id='$student_id'");

        $new = $conn->query("SELECT is_sick, hunger, mood, exp, level FROM student_pets WHERE id=$pid")->fetch_assoc();
        $new_points = $conn->query("SELECT points FROM students WHERE student_id='$student_id'")->fetch_assoc()['points'];
        echo json_encode([
            'code'=>1,
            'msg'=>"✅ 治疗成功！恢复健康！",
            'is_sick'=>0,
            'hunger'=>$new['hunger'],
            'mood'=>$new['mood'],
            'exp'=>$new['exp'],
            'level'=>$new['level'],
            'points'=>$new_points
        ]);
    } else {
        echo json_encode(['code'=>0, 'msg'=>"❌ 治疗需要 5 积分！"]);
    }
    exit;
}

// ------------------------------
// 进化（AJAX）
// ------------------------------
if (isset($_POST['evolve'])) {
    $pid = (int)$_POST['pet_id'];
    $pet = $conn->query("SELECT level,is_sick,IFNULL(stage,1) as stage FROM student_pets WHERE id=$pid AND student_id='$student_id'")->fetch_assoc();

    if ($pet['is_sick']) {
        echo json_encode(['code'=>0, 'msg'=>"⚠️ 生病不能进化！"]);
        exit;
    }

    $current_stage = $pet['stage'];
    $level = $pet['level'];
    $msg = '';
    $refresh = false;

    if ($current_stage == 1 && $level >= 5) {
        $new_level = $level;
        $new_max = 100 + min($new_level * 5, 100);
        $conn->query("UPDATE student_pets SET
            stage=2, level=$new_level, exp=0,
            hunger=ROUND($new_max * 0.8), mood=ROUND($new_max * 0.8), is_sick=0
            WHERE id=$pid AND student_id='$student_id'");
        $msg = "🎉 进化成功！形态升级为【成熟期】，属性暴涨！";
        $refresh = true;
    } elseif ($current_stage == 2 && $level >= 15) {
        $new_level = $level;
        $new_max = 100 + min($new_level * 5, 100);
        $conn->query("UPDATE student_pets SET
            stage=3, level=$new_level, exp=0,
            hunger=ROUND($new_max * 0.8), mood=ROUND($new_max * 0.8), is_sick=0
            WHERE id=$pid AND student_id='$student_id'");
        $msg = "🌟 完全进化！形态升级为【终极体】，属性拉满！";
        $refresh = true;
    } else {
        $msg = "⚠️ 等级不足或已达最高形态！";
    }

    if($refresh){
        echo json_encode(['code'=>1, 'msg'=>$msg, 'refresh'=>true]);
    } else {
        echo json_encode(['code'=>0, 'msg'=>$msg]);
    }
    exit;
}

// ------------------------------
// 给好友宠物送礼（无感AJAX）
// ------------------------------
if (isset($_POST['ajax_send_gift'])) {
    header("Content-Type: application/json; charset=utf-8");
    $target_pet_id = (int)$_POST['target_pet_id'];
    $gift_type = $_POST['gift_type'];

    $pet = $conn->query("SELECT * FROM student_pets WHERE id=$target_pet_id")->fetch_assoc();
    if (!$pet || $pet['student_id'] == $student_id) {
        echo json_encode(['code' => 0, 'msg' => "⚠️ 不能给自己送礼！"]);
        exit;
    }

    $cost = 1;
    if ($gift_type == 'food') $cost = 2;
    if ($gift_type == 'toy') $cost = 3;

    if ($my_points < $cost) {
        echo json_encode(['code' => 0, 'msg' => "❌ 积分不足！"]);
        exit;
    }

    $conn->query("UPDATE students SET points = points - $cost WHERE student_id='$student_id'");
    $msg = "";
    if ($gift_type == 'feed') {
        $max = 100 + min($pet['level']*5,100);
        $conn->query("UPDATE student_pets SET hunger = LEAST($max, hunger+10) WHERE id=$target_pet_id");
        $msg = "✅ 你给同学的宠物喂食成功！";
    } elseif ($gift_type == 'food') {
        $max = 100 + min($pet['level']*5,100);
        $conn->query("UPDATE student_pets SET hunger = LEAST($max, hunger+25) WHERE id=$target_pet_id");
        $msg = "✅ 赠送食物成功！";
    } elseif ($gift_type == 'toy') {
        $max = 100 + min($pet['level']*5,100);
        $conn->query("UPDATE student_pets SET mood = LEAST($max, mood+25) WHERE id=$target_pet_id");
        $msg = "✅ 赠送玩具成功！";
    }

    $new_points = $conn->query("SELECT points FROM students WHERE student_id='$student_id'")->fetch_assoc()['points'];
    echo json_encode(['code' => 1, 'msg' => $msg, 'points' => $new_points]);
    exit;
}

// ------------------------------
// 🔥 PK 答题验证（弹窗显示卡片）
// ------------------------------
if (isset($_POST['pk_answer'])) {
    header("Content-Type: application/json; charset=utf-8");
    
    $my_pet_id = (int)$_POST['my_pet_id'];
    $target_pet_id = (int)$_POST['target_pet_id'];
    $qid = (int)$_POST['qid'];
    $user_ans = (int)$_POST['answer'];

    $q = $conn->query("SELECT answer FROM questions WHERE id=$qid")->fetch_assoc();
    $correct = $q ? ($q['answer'] == $user_ans) : false;

    $today = date('Y-m-d');
    $pkCount = $conn->query("SELECT COUNT(*) AS cnt FROM pk_logs WHERE student_id='$student_id' AND pk_date='$today'")->fetch_assoc()['cnt'];
    if ($pkCount >= 5) {
        echo json_encode(["status" => "limit", "msg" => "⚠️ 今日PK次数已用完！"]);
        exit;
    }

    $me = $conn->query("SELECT * FROM student_pets WHERE id=$my_pet_id AND student_id='$student_id'")->fetch_assoc();
    $you = $conn->query("SELECT * FROM student_pets WHERE id=$target_pet_id")->fetch_assoc();

    // 关联宠物配置获取解锁积分
    $pet_config_me = $conn->query("SELECT unlock_points, is_rare FROM pet_config WHERE id='{$me['pet_id']}'")->fetch_assoc();
    $pet_config_you = $conn->query("SELECT unlock_points, is_rare FROM pet_config WHERE id='{$you['pet_id']}'")->fetch_assoc();

    $me['unlock_points'] = $pet_config_me['unlock_points'] ?? 50;
    $me['is_rare'] = $pet_config_me['is_rare'] ?? 0;
    $you['unlock_points'] = $pet_config_you['unlock_points'] ?? 50;
    $you['is_rare'] = $pet_config_you['is_rare'] ?? 0;
    
    $msg = '';
    if (!$me) {
        $msg = "⚠️ 你的宠物不存在，无法PK！";
    } elseif (!$you) {
        $msg = "⚠️ 对方宠物不存在，无法PK！";
    } elseif ($me['is_sick']) {
        $msg = "⚠️ 你的宠物生病了，先治疗再PK！";
    } elseif ($you['is_sick']) {
        $msg = "⚠️ 对方宠物生病了，不能PK！";
    } elseif ($me['hunger'] <= 20) {
        $msg = "⚠️ 你的宠物太饿了（饥饿≤20），先喂食再PK！";
    } elseif ($you['hunger'] <= 20) {
        $msg = "⚠️ 对方宠物太饿了，无法PK！";
    }

    if (!empty($msg)) {
        echo json_encode(["status" => "error", "msg" => $msg]);
        exit;
    }

    $hunger_cost = 10;
    $conn->query("UPDATE student_pets SET hunger = hunger - $hunger_cost WHERE id=$my_pet_id");
    $conn->query("UPDATE student_pets SET hunger = hunger - $hunger_cost WHERE id=$target_pet_id");

    // 🔥 增强：价格越高、越稀有 → 基础战斗力越高
	// 普通宠物：1.0  稀有宠物：1.5
	$rare_bonus_me = ($me['is_rare'] == 1) ? 1.5 : 1.0;
	$rare_bonus_you = ($you['is_rare'] == 1) ? 1.5 : 1.0;
	
	// 积分价格加成：每10积分 +1战力
	$price_bonus_me = $me['unlock_points'] / 10;
	$price_bonus_you = $you['unlock_points'] / 10;
	
	// 最终战斗力 = 等级+经验+饥饿心情 + 形态 + 稀有加成 + 价格加成
	$base_me = ($me['level'] * 10 + $me['exp'] + $me['hunger'] + $me['mood'] + ($me['stage'] ?? 1) * 30)
	           * $rare_bonus_me + $price_bonus_me;
	
	$base_you = ($you['level'] * 10 + $you['exp'] + $you['hunger'] + $you['mood'] + ($you['stage'] ?? 1) * 30)
	           * $rare_bonus_you + $price_bonus_you;

    if ($correct) {
        $base_me = $base_me * 1.5;
    } else {
        $base_me = $base_me * 0.7;
    }

    $win = ($base_me > $base_you) || ($base_me == $base_you && rand(0,1) == 1);
    $card_reward = null;
    
    if ($win) {
        $exp_change = 3;
        $conn->query("UPDATE student_pets SET exp = exp + $exp_change WHERE id=$my_pet_id");
        $conn->query("UPDATE student_pets SET exp = exp -10 WHERE id=$target_pet_id");
        
        $target_data = $conn->query("SELECT level, exp FROM student_pets WHERE id=$target_pet_id")->fetch_assoc();
        $t_level = $target_data['level'];
        $t_exp = $target_data['exp'];
        while ($t_level > 1 && $t_exp < 0) {
            $t_level--;
            $t_exp = $t_level * 20 + $t_exp;
        }
        $conn->query("UPDATE student_pets SET level=$t_level, exp=$t_exp WHERE id=$target_pet_id");

        $mood_msg = "你的宠物心情+10，对方宠物心情-10";
        $max_mood_me = 100 + min($me['level'] * 5, 100);
		$max_mood_you = 100 + min($you['level'] * 5, 100);
		$conn->query("UPDATE student_pets SET mood = LEAST($max_mood_me, mood + 10) WHERE id=$my_pet_id");
		$conn->query("UPDATE student_pets SET mood = GREATEST(0, mood - 10) WHERE id=$target_pet_id");

        /// 每日任务限制 + 胜利发卡片（每日最多1张）
		if (isTodayTaskCompleted($student_id, $conn)) {
		    $today = date('Y-m-d');
		    
		    // 🔥 关键：判断今天是否已经通过PK获得过卡片
		    $todayGotCard = $conn->query("
		        SELECT id FROM student_cards 
		        WHERE student_id = '$student_id' 
		          AND DATE(obtained_at) = '$today'
		    ")->num_rows;
		
		    // 今天没拿过才发
		    if (!$todayGotCard) {
		        $card = $conn->query("SELECT * FROM core_values_cards ORDER BY RAND() LIMIT 1")->fetch_assoc();
		        if ($card) {
		            $card_id = $card['id'];
		            $card_name = $card['card_name'];
		            $card_image = $card['card_image'];
		            $card_desc = isset($card['description']) ? $card['description'] : '核心价值观卡片';
		            
		            $conn->query("INSERT INTO student_cards (student_id, card_id, obtained_at) VALUES ('$student_id', $card_id, NOW())");
		            $conn->query("INSERT INTO point_logs (student_id, action, points_change) VALUES ('$student_id', '获得价值观卡片【$card_name】', 0)");
		            
		            // 返回完整卡片信息给前端
		            $card_reward = [
		                'name' => $card_name,
		                'image' => $card_image,
		                'desc' => $card_desc
		            ];
		        }
		    }
		}
    } else {
        $exp_change = -10;
        $conn->query("UPDATE student_pets SET exp = exp + $exp_change WHERE id=$my_pet_id");
        
        $pet_data = $conn->query("SELECT level, exp FROM student_pets WHERE id=$my_pet_id")->fetch_assoc();
        $new_level = $pet_data['level'];
        $new_exp = $pet_data['exp'];
        while ($new_level > 1 && $new_exp < 0) {
            $new_level--;
            $new_exp = $new_level * 20 + $new_exp;
        }
        $conn->query("UPDATE student_pets SET level = $new_level, exp = $new_exp WHERE id=$my_pet_id");

        $mood_msg = "你的宠物心情-10，对方宠物心情+10";
        $conn->query("UPDATE student_pets SET mood = mood - 10 WHERE id=$my_pet_id");
        $conn->query("UPDATE student_pets SET mood = mood + 10 WHERE id=$target_pet_id");
    }

    $conn->query("INSERT INTO pk_logs (student_id, pk_date) VALUES ('$student_id','$today')");

    $to_uid = $you['student_id'];
    $from_uid = $student_id;
    $pet_name = $me['pet_nickname'];
    $res = $win ? 'win' : 'lose';
    
    $conn->query("INSERT INTO pet_notifications (to_student_id, from_student_id, pet_name, result) 
                  VALUES ('$to_uid', '$from_uid', '$pet_name', '$res')");

    $result = $win ? "win" : "lose";
    
    // 🔥 修复换行：使用 PHP_EOL，前端自动识别换行
    if($win){
        $msg = "🎉 [{$me['pet_nickname']}] 胜利！挑战成功 +$exp_change 经验" . PHP_EOL .
               "对方宠物被扣除10经验，可能降级！" . PHP_EOL .
               "双方宠物饥饿-10 | " . $mood_msg;
    }else{
        $msg = "💀 [{$me['pet_nickname']}] 失败！扣除 10 经验，可能降级！" . PHP_EOL .
               "双方宠物饥饿-10 | " . $mood_msg;
    }
    
    echo json_encode([
	    "status" => "ok",
	    "result" => $result,
	    "correct" => $correct,
	    "pet_name" => $me['pet_nickname'],
	    "exp" => abs($exp_change),
	    "exp_change" => $exp_change,
	    "hunger_cost" => $hunger_cost,
	    "card" => $card_reward,
	    "msg" => $msg
	]);
    exit;
}

// ------------------------------
// 无感刷新：积分（新增）
// ------------------------------
if (isset($_POST['ajax_refresh_points'])) {
    header("Content-Type: application/json; charset=utf-8");
    $p = $conn->query("SELECT points FROM students WHERE student_id='$student_id'")->fetch_assoc();
    echo json_encode(['points' => $p['points']]);
    exit;
}

// ------------------------------
// 无感刷新：单只宠物状态（新增）
// ------------------------------
if (isset($_POST['ajax_refresh_pet'])) {
    header("Content-Type: application/json; charset=utf-8");
    $pid = (int)$_POST['pet_id'];
    $pet = $conn->query("SELECT * FROM student_pets WHERE id=$pid AND student_id='$student_id'")->fetch_assoc();
    if ($pet) {
        echo json_encode([
            'code' => 1,
            'level' => $pet['level'],
            'exp' => $pet['exp'],
            'hunger' => $pet['hunger'],
            'mood' => $pet['mood'],
            'is_sick' => $pet['is_sick'],
            'stage' => $pet['stage'] ?? 1
        ]);
    } else {
        echo json_encode(['code' => 0]);
    }
    exit;
}

// 按缺席天数扣除（保底20，避免扣到生病线）
$today = date('Y-m-d');
$hunger_per_day = 2;
$mood_per_day = 2;

$pets = $conn->query("SELECT id, level, hunger, mood, last_decay FROM student_pets WHERE student_id='$student_id'");

while ($p = $pets->fetch_assoc()) {
    $pid = $p['id'];
    $lv = $p['level'];
    $hunger = $p['hunger'];
    $mood = $p['mood'];
    $last = $p['last_decay'];
    $max = 100 + min($lv * 5, 100);

    if ($last === NULL) {
        $conn->query("UPDATE student_pets SET last_decay='$today' WHERE id=$pid");
        continue;
    }

    $days = (strtotime($today) - strtotime($last)) / (60 * 60 * 24);
    $days = max(0, min((int)$days, 30));

    if ($days <= 0) continue;

    $total_hunger = $hunger_per_day * $days;
    $total_mood = $mood_per_day * $days;

    // 🔥 保底15，永远不会被扣到生病线
    $new_hunger = max(15, $hunger - $total_hunger);
    $new_mood = max(15, $mood - $total_mood);

    $conn->query("UPDATE student_pets SET hunger=$new_hunger, mood=$new_mood, last_decay='$today' WHERE id=$pid");
}

// 🔥 修复：只有饥饿<10才会小概率生病，且只对健康宠物生效
$conn->query("UPDATE student_pets 
              SET is_sick=1 
              WHERE student_id='$student_id' 
                AND is_sick = 0 
                AND hunger < 10 
                AND RAND() < 0.01");  

// 平衡升级：经验门槛从 level*10 → level*20
$conn->query("UPDATE student_pets SET level=level+1, exp=0 WHERE student_id='$student_id' AND exp>=(level*20)");

// 替换原来那行 SET is_sick=1 为这行
// 修复：宠物生病逻辑（仅对健康宠物、饥饿<15的宠物，极低概率生病）
$conn->query("UPDATE student_pets 
              SET is_sick=1 
              WHERE student_id='$student_id' 
                AND is_sick = 0  -- 只对健康宠物生效
                AND CAST(hunger AS UNSIGNED) < 15  -- 强制转数字比较，避免字符串坑
                AND RAND() < 0.01");  
                	
// 平衡升级：经验门槛从 level*10 → level*20
$conn->query("UPDATE student_pets SET level=level+1, exp=0 WHERE student_id='$student_id' AND exp>=(level*20)");

// 数据读取
$my_pets = $conn->query("SELECT sp.*, IFNULL(sp.stage,1) as stage, pc.pet_name, pc.pet_type, pc.pet_image, pc.pet_image_stage2, pc.pet_image_stage3, pc.unlock_points, pc.is_rare, pc.need_card_count, pc.pet_desc FROM student_pets sp JOIN pet_config pc ON sp.pet_id=pc.id WHERE sp.student_id='$student_id'");
$all_pets = $conn->query("SELECT * FROM pet_config");
$owned_ids = [];while ($p = $my_pets->fetch_assoc()) $owned_ids[] = $p['pet_id'];$my_pets->data_seek(0);
$my_pets_list = [];if($my_pets->num_rows>0){while($m=$my_pets->fetch_assoc())$my_pets_list[]=$m;$my_pets->data_seek(0);}
$all_student_pets = $conn->query("SELECT sp.*, pc.pet_name,pc.pet_type,pc.pet_image,pc.pet_image_stage2,pc.pet_image_stage3,s.name as owner_real_name FROM student_pets sp JOIN pet_config pc ON sp.pet_id=pc.id JOIN students s ON sp.student_id=s.student_id WHERE sp.student_id!='$student_id' ORDER BY sp.level DESC");
$rank_list = $conn->query("SELECT sp.*,s.name,pc.pet_name,pc.pet_type,pc.pet_image,pc.pet_image_stage2,pc.pet_image_stage3 FROM student_pets sp JOIN students s ON sp.student_id = s.student_id JOIN pet_config pc ON sp.pet_id=pc.id ORDER BY sp.level DESC LIMIT 20");
$today = date('Y-m-d');
$signed_today = $conn->query("SELECT id FROM sign_logs WHERE student_id='$student_id' AND sign_date='$today'")->num_rows;

// PK 答题实时获取
function getRandomQuestion($conn) {
    return $conn->query("SELECT id,question,option1,option2,option3,option4,answer FROM questions ORDER BY RAND() LIMIT 1")->fetch_assoc();
}
$question = getRandomQuestion($conn);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>🐾 宠物基地</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
	    * {margin:0;padding:0;box-sizing:border-box;}
	    .style-game {--bg:#121a2e;--card:#1a243f;--card2:#243052;--border:#3e4d77;--text:#fff;--text2:#a8b1c7;--accent:#00e5ff;--points:#ffd60a;}
	    .style-clean {--bg:#f8f9fc;--card:#fff;--card2:#f1f5f9;--border:#e2e8f0;--text:#2d3748;--text2:#64748b;--accent:#3b82f6;--points:#f59e0b;}
	    .style-cute {--bg:#fffafc;--card:#fff;--card2:#fef2f8;--border:#fbcfe8;--text:#553146;--text2:#9f6a8c;--accent:#ec4899;--points:#f97316;}
	    body {font-family:'Microsoft YaHei',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:15px;}
	    .container {max-width:900px;margin:0 auto;}
	    .style-switch {display:flex;justify-content:center;gap:8px;margin-bottom:12px;}
	    .style-btn {padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);cursor:pointer;font-size:14px;}
	    .style-btn.active {background:var(--accent);color:#fff;border-color:var(--accent);}
	    .sign-bar {text-align:center;margin-bottom:12px;}
	    .sign-btn {padding:8px 16px;background:var(--points);color:#fff;border:none;border-radius:10px;font-weight:bold;cursor:pointer;font-size:14px;}
	    .sign-btn:disabled {background:#999;}
	    .game-header {text-align:center;padding:12px;margin-bottom:15px;background:var(--card);border-radius:12px;border:1px solid var(--border);}
	    .game-title {font-size:22px;color:var(--accent);}
	    .user-bar {display:flex;justify-content:space-between;padding:10px 14px;background:var(--card);border-radius:10px;border:1px solid var(--border);margin-bottom:12px;font-size:14px;}
	    .points {color:var(--points);font-weight:bold;}
	    .panel {background:var(--card);border-radius:12px;padding:15px;margin-bottom:15px;border:1px solid var(--border);}
	    .panel h2 {color:var(--accent);font-size:16px;margin-bottom:12px;padding-bottom:6;border-bottom:1px solid var(--border);}
	    .pet-grid {display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;}
	    .pet-card {background:var(--card2);border-radius:10px;padding:12px 8px;text-align:center;border:1px solid var(--border);transition:0.2s;}
	    .pet-card:hover {transform:translateY(-2px);border-color:var(--accent);}
	    .pet-card img {width:70px;height:70px;object-fit:contain;margin-bottom:6px;}
	    .pet-name {font-weight:bold;margin-bottom:4px;font-size:14px;}
	    .pet-info {font-size:12px;color:var(--text2);margin:2px 0;}
	    .stage-badge {display:inline-block;padding:2px 5;border-radius:4px;font-size:11px;color:#fff;margin-bottom:4px;}
	    .stage1 {background:#64748b;}.stage2 {background:#3b82f6;}.stage3 {background:#ec4899;}
	    .btn {padding:4px 6px;border-radius:6px;color:#fff;font-weight:bold;border:none;cursor:pointer;margin:3px 2px;font-size:12px;}
	    .btn-feed {background:#0ea5e9;}.btn-play {background:#10b981;}.btn-cure {background:#ef4444;}.btn-evolve {background:#8b5cf6;}.btn-item {background:#14b8a6;}.btn-unlock {background:#8b5cf6;}.btn-gift {background:#ec4899;}.btn-pk {background:#f97316;}.btn-rename {background:#f59e0b;}
	    .btn:disabled {background:#999;}
	    .empty {grid-column:1/-1;text-align:center;color:var(--text2);padding:20px 0;font-size:14px;}
	    .back-btn {display:block;width:fit-content;margin:10px auto;padding:8px 16px;background:var(--accent);color:#fff;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px;}
	    .rename-input {width:90%;padding:4px;margin:4px 0;border-radius:6px;border:1px solid var(--border);text-align:center;background:var(--card);color:var(--text);}
	    .rename-tip {font-size:11px;color:var(--text2);margin:2px 0;}
	    .rank-list {display:flex;flex-direction:column;gap:8px;}
	    .rank-item {display:flex;align-items:center;padding:10px 12px;background:var(--card2);border-radius:10px;border:1px solid var(--border);transition:0.2s;}
	    .rank-item:hover {transform:translateX(2px);}
	    .rank-no {width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:13px;margin-right:10px;flex-shrink:0;}
	    .no-1 {background:#ffd700;color:#92400e;}.no-2 {background:#c0c0c0;color:#1e293b;}.no-3 {background:#cd7f32;color:#fff;}.other {background:var(--border);color:var(--text);}
	    .rank-pet-img {width:40px;height:40px;object-fit:contain;margin-right:10px;}
	    .rank-info {flex:1;}
	    .rank-name {font-weight:bold;font-size:14px;margin-bottom:2px;}
	    .rank-pet-name {font-size:12px;color:var(--text2);}
	    .rank-level {text-align:right;font-weight:bold;color:var(--accent);font-size:14px;}
	    .rank-exp-bar {width:100%;height:4px;background:var(--border);border-radius:2px;overflow:hidden;margin-top:3px;}
	    .rank-exp-progress {height:100%;background:var(--accent);border-radius:2px;}
	    .pk-modal,.q-modal {display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;}
	    .pk-modal.show,.q-modal.show {display:flex;}
	    .modal-content {background:var(--card);border-radius:15px;width:90%;max-width:500px;padding:20px;border:1px solid var(--border);max-height:80vh;overflow-y:auto;}
	    .modal-title {text-align:center;color:var(--accent);margin-bottom:15px;font-size:18px;}
	    .pet-select-grid {display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;margin-bottom:15px;}
	    .pet-select-card {background:var(--card2);border-radius:10px;padding:10px;text-align:center;cursor:pointer;border:2px solid var(--border);transition:0.2s;}
	    .pet-select-card:hover {border-color:var(--accent);}
	    .pet-select-card img {width:50px;height:50px;object-fit:contain;}
	    .pet-select-name {font-size:12px;font-weight:bold;margin-top:5px;}
	    .modal-close {display:block;margin:0 auto;padding:8px 16px;background:var(--accent);color:#fff;border:none;border-radius:8px;cursor:pointer;}
        .quiz-option {background:var(--card2);padding:10px;margin:8px 0;border-radius:8px;cursor:pointer;border:1px solid var(--border);}
        .quiz-option:hover {border-color:var(--accent);}

        .pk-result-modal {
            display: none;
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); z-index: 999999;
            align-items: center; justify-content: center;
            animation: fadeIn 0.3s;
        }
        .pk-result-modal.show {display: flex;}
        .pk-result-box {
            background: #fff; border-radius: 20px; width: 90%; max-width: 420px;
            padding: 30px 20px; text-align: center; position: relative;
            box-shadow: 0 0 30 rgba(0,0,0,0.3);
        }
        .pk-result-icon {font-size: 70px; margin-bottom: 15px;}
        .pk-result-title {font-size: 24px; font-weight: bold; margin-bottom: 10px;}
        .pk-result-desc {
		    font-size: 16px;
		    margin-bottom: 10px;
		    color: #555;
		    line-height: 1.8;
		    padding: 0 10px;
		    white-space: pre-line;
		    word-wrap: break-word;
		}
        .pk-result-hunger {font-size:15px; color:#ff4d4f; font-weight:bold; margin-bottom:15px;}
        .pk-result-btn {
            padding: 10px 25px; background: #3b82f6; color: #fff;
            border: none; border-radius: 10px; font-size: 16px; cursor: pointer;
        }
        .pk-win .pk-result-title {color: #10b981;}
        .pk-lose .pk-result-title {color: #ef4444;}
        @keyframes fadeIn {from{opacity:0;}to{opacity:1;}}

        /* 卡片选择弹窗（修复版） */
        .card-select-modal {
            display: none;
            position: fixed; top:0; left:0; width:100%; height:100%;
            background:rgba(0,0,0,0.85); z-index:99999;
            align-items: center; justify-content: center;
        }
        .card-box {
            background:#fff; border-radius:16px; width:90%; max-width:500px;
            padding:20px; max-height:80vh; overflow-y:auto;
            color: #333;
        }
        .card-grid {
            display: grid; grid-template-columns: repeat(3,1fr); gap:10px;
            margin:15px 0;
        }
        .card-item {
            border:2px solid #ddd; border-radius:8px; padding:8px; text-align:center;
            cursor:pointer; transition: all 0.2s;
        }
        .card-item:hover {
            border-color:#ec4899;
            transform: translateY(-2px);
        }
        .card-item.selected {
            border-color:#ec4899; background:#fdf2f8;
            box-shadow: 0 0 10px rgba(236,72,153,0.3);
        }
        .card-item img {
            width:70px; height:90px; object-fit:contain;
        }
        .card-desc {
            font-size: 12px; margin: 5px 0;
        }
        
        /* 宠物说明悬浮提示框 - 美观版 */
		.pet-tooltip {
		    position: absolute;
		    bottom: 105%;
		    left: 50%;
		    transform: translateX(-50%);
		    background: var(--card);
		    color: var(--text);
		    border: 1px solid var(--border);
		    padding: 8px 12px;
		    border-radius: 10px;
		    font-size: 12px;
		    white-space: pre-wrap;
		    width: 180px;
		    text-align: center;
		    line-height: 1.4;
		    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
		    z-index: 999;
		    opacity: 0;
		    pointer-events: none;
		    transition: opacity 0.2s ease;
		}
		.pet-card {
		    position: relative; /* 必须加，让提示框相对卡片定位 */
		}
		.pet-card:hover .pet-tooltip {
		    opacity: 1;
		}
    </style>
</head>
<body class="<?= 'style-'.$style ?>">
<div class="container">
    <form method="post" class="style-switch">
        <button type="submit" name="style" value="game" class="style-btn <?= $style=='game'?'active':'' ?>">🎮 科技风</button>
        <button type="submit" name="style" value="clean" class="style-btn <?= $style=='clean'?'active':'' ?>">☀️ 清新风</button>
        <button type="submit" name="style" value="cute" class="style-btn <?= $style=='cute'?'active':'' ?>">🌸 可爱风</button>
    </form>
    <div class="game-header"><h1 class="game-title">🐾 宠物基地</h1></div>
    <div class="user-bar"><span>学员：<?= $student['name'] ?>（<?= $student_id ?>）</span><span class="points" id="user-points">⭐ 积分：<?= $my_points ?></span></div>
    <div class="sign-bar"><form method="post"><button class="sign-btn" name="sign" <?= $signed_today?'disabled':'' ?>><?= $signed_today?'✅ 今日已签到':'📅 每日签到（随机3-8分）' ?></button></form></div>

    <div class="panel"><h2>🎒 我的宠物</h2><div class="pet-grid">
        <?php if ($my_pets->num_rows>0):while($pet=$my_pets->fetch_assoc()):$pid=$pet['id'];$stage=$pet['stage'];
            if($stage==1){$img=$pet['pet_image'];$t='幼年期';}elseif($stage==2){$img=$pet['pet_image_stage2'];$t='成熟期';}else{$img=$pet['pet_image_stage3'];$t='终极体';}
            $mh=100+min($pet['level']*5,100);$mm=100+min($pet['level']*5,100);$tip=$pet['rename_count']==0?'首次免费':'5积分';
        ?>
        <div class="pet-card" id="pet-<?=$pid?>">
            <img src="<?=$img?>" id="pet-img-<?=$pid?>">
            <div class="pet-name" id="pet-name-<?=$pid?>"><?=$pet['pet_nickname']?></div>
            <span class="stage-badge stage<?=$stage?>" id="pet-stage-<?=$pid?>"><?=$t?></span>
            <div class="pet-info" id="pet-level-<?=$pid?>">Lv.<?=$pet['level']?> 经验：<span id="pet-exp-<?=$pid?>"><?=$pet['exp']?></span>/<?=$pet['level']*20?></div>
            <div class="pet-info" id="pet-type-<?=$pid?>">🐾 类型：<?=$pet['pet_type']?></div>
            <div class="pet-info" id="pet-hunger-<?=$pid?>" style="<?=$pet['hunger']==0?'color:red':''?>">🍖 饥饿：<span id="hunger-val-<?=$pid?>"><?=$pet['hunger']?></span>/<?=$mh?></div>
            <div class="pet-info" id="pet-mood-<?=$pid?>">🎈 心情：<span id="mood-val-<?=$pid?>"><?=$pet['mood']?></span>/<?=$mm?></div>

            <input type="text" class="rename-input" id="rename-input-<?=$pid?>" placeholder="2-8字">
            <div class="rename-tip"><?=$tip?></div>
            <button class="btn btn-rename" onclick="renamePet(<?=$pid?>)">✏️ 改名</button>

            <div id="pet-sick-<?=$pid?>" style="color:red;margin:8px 0;<?=$pet['is_sick']?'':'display:none;'?>">🤒 生病</div>
            <div id="btn-cure-<?=$pid?>" style="<?=$pet['is_sick']?'':'display:none;'?>">
                <button class="btn btn-cure" onclick="curePet(<?=$pid?>)">治疗(-5)</button>
            </div>

            <div id="btn-normal-<?=$pid?>" style="<?=$pet['is_sick']?'display:none;':''?>">
                <div style="margin-top:8px;">
                    <button class="btn btn-feed" onclick="feedPet(<?=$pid?>)">喂食</button>
                    <button class="btn btn-play" onclick="playPet(<?=$pid?>)">玩耍</button>
                </div>
                <div style="margin-top:6px;">
                    <button class="btn btn-item" onclick="useFood(<?=$pid?>)">食物(-2)</button>
                    <button class="btn btn-item" onclick="useToy(<?=$pid?>)">玩具(-3)</button>
                </div>
                <?php if(($stage==1&&$pet['level']>=5)||($stage==2&&$pet['level']>=15)):?>
                <div style="margin-top:8px;">
                    <button class="btn btn-evolve" onclick="evolvePet(<?=$pid?>)">进化</button>
                </div>
                <?php endif;?>
            </div>
        </div>
        <?php endwhile;else:?><div class="empty">暂无宠物</div><?php endif;?>
    </div></div>

    <div class="panel" id="cards-panel"><h2>🎴 我的价值观卡片</h2>
    <div class="pet-grid" id="cards-container">
        <?php
        $my_cards = $conn->query("SELECT c.*, sc.obtained_at FROM student_cards sc JOIN core_values_cards c ON sc.card_id = c.id WHERE sc.student_id='$student_id' ORDER BY sc.id DESC LIMIT 12");
        $total = 12;
        $collected = $conn->query("SELECT COUNT(DISTINCT card_id) as cnt FROM student_cards WHERE student_id='$student_id'")->fetch_assoc()['cnt'];
        
        while ($card = $my_cards->fetch_assoc()) {
            echo "<div class='pet-card'>
                <img src='{$card['card_image']}'>
                <div class='pet-name'>{$card['card_name']}</div>
                <div class='pet-info'>获得：".date('m-d H:i',strtotime($card['obtained_at']))."</div>
            </div>";
        }
        ?>
        <div class="empty" id="card-collect-text" style="grid-column:1/-1;">已集齐种类：<?=$collected?>/12</div>
    </div></div>

    <div class="panel"><h2>🏆 班级排行榜</h2><div class="rank-list">
        <?php $r=1;while($row=$rank_list->fetch_assoc()):$st=$row['stage']??1;if($st==1)$pi=$row['pet_image'];elseif($st==2)$pi=$row['pet_image_stage2'];else$pi=$row['pet_image_stage3'];$per=($row['exp']/($row['level']*20))*100;?>
        <div class="rank-item"><div class="rank-no <?= $r<=3?"no-$r":"other" ?>"><?=$r?></div><img src="<?=$pi?>" class="rank-pet-img"><div class="rank-info"><div class="rank-name"><?=$row['name']?><span class="stage-badge stage<?=$st?>"><?=$st==1?'幼':($st==2?'成':'满')?></span></div><div class="rank-pet-name"><?=$row['pet_nickname']?>（<?=$row['pet_type']?>）</div><div class="rank-exp-bar"><div class="rank-exp-progress" style="width:<?=$per?>%"></div></div></div><div class="rank-level">Lv.<?=$row['level']?></div></div>
        <?php $r++;endwhile;?>
    </div></div>

    <div class="panel"><h2>👋 拜访同学</h2><div class="pet-grid">
        <?php if($all_student_pets->num_rows>0):while($p=$all_student_pets->fetch_assoc()):$id=$p['id'];$st=$p['stage'];if($st==1)$pi=$p['pet_image'];elseif($st==2)$pi=$p['pet_image_stage2'];else$pi=$p['pet_image_stage3'];?>
        <div class="pet-card"><img src="<?=$pi?>"><div class="pet-name"><?=$p['pet_nickname']?></div><div class="pet-info">主人：<?=$p['owner_real_name']?></div><div class="pet-info">🐾 <?=$p['pet_type']?></div><div class="pet-info">Lv.<?=$p['level']?></div>
            <?php if($my_pets->num_rows>0):?><button class="btn btn-pk" onclick="openPk(<?=$id?>)">⚔️ PK</button><?php endif;?>
            <div style="margin-top:5px;">
                <button class="btn btn-gift" onclick="sendGift(<?=$id?>, 'feed')">投喂</button>
                <button class="btn btn-gift" onclick="sendGift(<?=$id?>, 'food')">食物</button>
                <button class="btn btn-gift" onclick="sendGift(<?=$id?>, 'toy')">玩具</button>
            </div>
        </div>
        <?php endwhile;else:?><div class="empty">暂无同学宠物</div><?php endif;?>
    </div></div>

    <div class="panel"><h2>🏪 宠物商店</h2><div class="pet-grid">
	    <?php while($p=$all_pets->fetch_assoc()):
		    $is_rare = $p['is_rare'] ?? 0;
		    $need = $p['need_card_count'] ?? 0;
		    $pet_desc = htmlspecialchars($p['pet_desc'] ?? '这是一只可爱的宠物');
		    
		    // 限量库存逻辑
		    $total_limit = $p['total_limit'] ?? 0;
		    $stock = $p['stock'] ?? 0;
		?>
	    <div class="pet-card">
		    <div class="pet-tooltip"><?=$pet_desc?></div>
		    <img src="<?=$p['pet_image']?>">
		    <div class="pet-name"><?=$p['pet_name']?></div>
		    
		    <?php if($is_rare):?>
		        <div style="color:#ec4899; font-weight:bold; font-size:12px;">⭐ 稀有宠物</div>
		        <div class="pet-info">需卡片：<?=$need?>张</div>
		    <?php endif;?>
		    
		    <div class="pet-info">🐾 <?=$p['pet_type']?></div>
		    <div class="pet-info">⭐ <?=$p['unlock_points']?>分</div>
		    
		    <!-- 显示剩余库存（关键代码） -->
		    <?php if ($total_limit > 0): ?>
		        <div class="pet-info" style="color: #f59e0b; font-weight:bold;">
		            📦 剩余：<?=$stock?> 个
		        </div>
		        <?php if ($stock <= 0): ?>
		            <div class="pet-info" style="color:red; font-weight:bold;">❌ 已兑罄</div>
		        <?php endif; ?>
		    <?php else: ?>
		        <div class="pet-info" style="color:#10b981;">♾️ 无限兑换</div>
		    <?php endif; ?>
		    
		    <?php if(in_array($p['id'],$owned_ids)):?>
		        <button class="btn" disabled>✅ 已拥有</button>
		    <?php else: ?>
		        <?php if ($total_limit > 0 && $stock <= 0): ?>
		            <button class="btn" disabled>❌ 已兑罄</button>
		        <?php else: ?>
		            <button class="btn btn-unlock" onclick="unlockPet(<?=$p['id']?>)">🔓 解锁</button>
		        <?php endif; ?>
		    <?php endif; ?>
		</div>
	    <?php endwhile;?>
	</div></div>

    <a href="student_menu.php" class="back-btn">返回菜单</a>
</div>

<!-- ====================== 稀有宠物卡片选择弹窗（修复完整版） ====================== -->
<?php 
// 🔥 修复：只要 SESSION 存在稀有宠物数据，就显示弹窗
$rare_pet = $_SESSION['rare_unlock_pet'] ?? null;
$rare_cards = $_SESSION['rare_unlock_cards'] ?? [];
$show_rare_modal = ($rare_pet !== null && !empty($rare_cards));

if ($show_rare_modal): 
    $pet_id = $rare_pet['pet_id'];
    $need_card_count = $rare_pet['need_card_count'];
    $pet_name = $rare_pet['pet_name'];
?>
<div id="rareCardModal" class="card-select-modal" style="display:flex;">
    <div class="card-box">
        <h3 style="text-align:center; margin-bottom:15px;">🎴 选择 <?=$need_card_count?> 张卡片解锁稀有宠物【<?=$pet_name?>】</h3>
		<div style="text-align:center; margin-bottom:10px; font-size:14px; color:#666;">
		    已选择：<span id="selectedCount">0</span> / <?=$need_card_count?> 张
		</div>
<div id="cardTip" style="text-align:center; font-size:13px; color:red; margin-bottom:10px; display:none;"></div>
        <form id="rareForm" onsubmit="confirmRareUnlock(event)">
            <input type="hidden" name="pet_id" value="<?=$pet_id?>">
            
            <div class="card-grid">
                <?php foreach ($rare_cards as $card): ?>
                <div class="card-item" onclick="toggleCard(this, '<?=$card['sc_id']?>')">
                    <img src="<?=$card['card_image']?>">
                    <div style="font-size:12px; font-weight:bold; margin-top:5px;"><?=$card['card_name']?></div>
                    <input type="hidden" class="card-scid" value="<?=$card['sc_id']?>">
                </div>
                <?php endforeach; ?>
            </div>

            <div style="text-align:center; margin-top:15px;">
                <button type="submit" class="btn" style="padding:10px 20px; background:#ec4899; font-size:14px;">✅ 确认解锁</button>
                <button type="button" class="btn" style="padding:10px 20px; background:#666; font-size:14px; margin-left:10px;" onclick="closeRareModal()">❌ 取消</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- PK 选择宠物 -->
<div id="pkModal" class="pk-modal"><div class="modal-content"><h3 class="modal-title">选择出战宠物</h3><div class="pet-select-grid">
    <?php foreach($my_pets_list as $mp):$st=$mp['stage']??1;if($st==1)$im=$mp['pet_image'];elseif($st==2)$im=$mp['pet_image_stage2'];else$im=$mp['pet_image_stage3'];?>
    <div class="pet-select-card" onclick="startPk(<?=$mp['id']?>)"><img src="<?=$im?>"><div class="pet-select-name"><?=$mp['pet_nickname']?></div><div style="font-size:11px;">Lv.<?=$mp['level']?></div></div>
    <?php endforeach;?>
</div><button class="modal-close" onclick="closePk()">取消</button></div></div>

<!-- 答题弹窗 -->
<div id="quizModal" class="q-modal"><div class="modal-content"><h3 class="modal-title">📝 PK 答题（答对提升战力）</h3>
    <div id="quizBox"></div>
    <button class="modal-close" onclick="closeQuiz()">取消</button>
</div></div>

<!-- PK 结果弹窗 -->
<div id="pkResultModal" class="pk-result-modal">
    <div class="pk-result-box" id="resultBox">
        <div class="pk-result-icon" id="resultIcon"></div>
        <div class="pk-result-title" id="resultTitle"></div>
        <div class="pk-result-desc" id="resultDesc"></div>
        
        <div id="cardRewardArea" style="display:none; margin:15px 0; text-align:center;">
            <img id="cardRewardImg" src="" style="width:120px; height:160px; object-fit:contain; border-radius:8px; border:2px solid #fbbf24;">
            <div style="font-weight:bold; font-size:16px; margin:5px 0;" id="cardRewardName"></div>
            <div style="font-size:12px; color:#666;" id="cardRewardDesc"></div>
        </div>
        
        <div class="pk-result-hunger" id="resultHunger"></div>
        <button class="pk-result-btn" onclick="closePkResult()">确定</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let targetId = 0;
const REAL_ANSWER = <?= (int)$question['answer'] ?>;
const questionData = <?=json_encode($question)?>;

let selectedCards = [];
const NEED_CARD = <?=$rare_pet['need_card_count'] ?? 0?>;

// 更新已选数量
function updateSelectedCount() {
    document.getElementById('selectedCount').textContent = selectedCards.length;
}

// 卡片选择（带提示）
function toggleCard(el, scid) {
    const tipBox = document.getElementById('cardTip');
    
    if (el.classList.contains('selected')) {
        el.classList.remove('selected');
        selectedCards = selectedCards.filter(id => id != scid);
        tipBox.style.display = 'none'; // 取消选择时隐藏提示
    } else {
        if (selectedCards.length >= NEED_CARD) {
            tipBox.textContent = `❌ 最多只能选择 ${NEED_CARD} 张卡片！`;
            tipBox.style.display = 'block';
            return;
        }
        el.classList.add('selected');
        selectedCards.push(scid);
        tipBox.style.display = 'none';
    }
    updateSelectedCount();
}

// 弹出消息（成功/错误）
function showMsg(msg, isError = false) {
    Swal.fire({
        icon: isError ? 'error' : 'success',
        title: msg,
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 2200,
        width: '90%'
    });
}

// 页面加载自动弹出通知
window.onload = function(){
    // PK通知
    <?php
    $notices = $conn->query("SELECT * FROM pet_notifications WHERE to_student_id='$student_id' ORDER BY id DESC");
    $noticeMsg = [];
    while ($nt = $notices->fetch_assoc()) {
        $from_user = $conn->query("SELECT name FROM students WHERE student_id='{$nt['from_student_id']}'")->fetch_assoc();
        $res_str = $nt['result'] == 'win' ? '胜利了' : '失败了';
        $noticeMsg[] = "⚔️ 【{$from_user['name']}】的宠物 {$nt['pet_name']} 向你发起挑战并".$res_str."！";
    }
    $conn->query("DELETE FROM pet_notifications WHERE to_student_id='$student_id'");
    
    if(!empty($noticeMsg)){
        foreach($noticeMsg as $msg){
            showMsg(`$msg`);
        }
    }
    ?>

    // 系统消息（签到等）
    <?php if (isset($_SESSION['msg'])): 
        $msg = $_SESSION['msg'];
        $isError = str_contains($msg, '❌') || str_contains($msg, '⚠️');
        unset($_SESSION['msg']);
    ?>
    showMsg(`<?=$msg?>`, <?= $isError ? 'true' : 'false' ?>);
    <?php endif; ?>
}

// 关闭稀有弹窗（修复版）
function closeRareModal() {
    document.getElementById('rareCardModal').style.display = 'none';
    selectedCards = [];
    fetch('pet.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'clear_rare_session=1'
    }).then(() => {
        location.reload();
    });
}

// 确认解锁稀有宠物（修复版）
async function confirmRareUnlock(e) {
    e.preventDefault();
    const pet_id = document.querySelector('input[name="pet_id"]').value;
    const tipBox = document.getElementById('cardTip');

    if (selectedCards.length !== NEED_CARD) {
        tipBox.textContent = `⚠️ 必须选择 ${NEED_CARD} 张卡片！`;
        tipBox.style.display = 'block';
        return false;
    }

    tipBox.style.display = 'none';

    let formData = new FormData();
    formData.append('ajax_confirm_rare_unlock', 1);
    formData.append('pet_id', pet_id);
    selectedCards.forEach(id => {
        formData.append('selected_cards[]', id);
    });

    const res = await fetch('pet.php', {
        method: 'POST',
        body: formData
    });
    const data = await res.json();
    
    if (data.code === 1) {
        showMsg(data.msg);
        document.getElementById('user-points').textContent = `⭐ 积分：${data.points}`;
        closeRareModal();
        if (data.refresh) setTimeout(() => location.reload(), 1000);
    } else {
        tipBox.textContent = data.msg;
        tipBox.style.display = 'block';
    }
}

// 解锁宠物（无感）
async function unlockPet(pet_id) {
    const res = await fetch('pet.php', {
        method: 'POST',
        body: new URLSearchParams({ajax_unlock_pet: 1, pet_id: pet_id})
    });
    const data = await res.json();
    if (data.code === 1) {
        showMsg(data.msg);
        document.getElementById('user-points').textContent = `⭐ 积分：${data.points}`;
        if (data.refresh) setTimeout(() => location.reload(), 1000);
    } else if (data.code === 2) {
        location.reload();
    } else {
        showMsg(data.msg, true);
    }
}

// 送礼（无感）
async function sendGift(target_pet_id, gift_type) {
    const res = await fetch('pet.php', {
        method: 'POST',
        body: new URLSearchParams({
            ajax_send_gift: 1,
            target_pet_id: target_pet_id,
            gift_type: gift_type
        })
    });
    const data = await res.json();
    if (data.code === 1) {
        showMsg(data.msg);
        document.getElementById('user-points').textContent = `⭐ 积分：${data.points}`;
    } else {
        showMsg(data.msg, true);
    }
}

// 喂食
async function feedPet(pid) {
    const res = await fetch('pet.php', {
        method: 'POST',
        body: new URLSearchParams({feed:1, pet_id:pid})
    });
    const data = await res.json();
    if(data.code === 1) {
        document.getElementById(`hunger-val-${pid}`).textContent = data.hunger;
        document.getElementById(`pet-exp-${pid}`).textContent = data.exp;
        document.getElementById(`user-points`).textContent = `⭐ 积分：${data.points}`;
        showMsg(data.msg);
    } else {
        showMsg(data.msg, true);
    }
}

// 玩耍
async function playPet(pid) {
    const res = await fetch('pet.php', {
        method: 'POST',
        body: new URLSearchParams({play:1, pet_id:pid})
    });
    const data = await res.json();
    if(data.code === 1) {
        document.getElementById(`mood-val-${pid}`).textContent = data.mood;
        document.getElementById(`pet-exp-${pid}`).textContent = data.exp;
        document.getElementById(`user-points`).textContent = `⭐ 积分：${data.points}`;
        showMsg(data.msg);
    } else {
        showMsg(data.msg, true);
    }
}

// 使用食物
async function useFood(pid) {
    const res = await fetch('pet.php', {
        method: 'POST',
        body: new URLSearchParams({use_food:1, pet_id:pid})
    });
    const data = await res.json();
    if(data.code === 1) {
        document.getElementById(`hunger-val-${pid}`).textContent = data.hunger;
        document.getElementById(`pet-exp-${pid}`).textContent = data.exp;
        document.getElementById(`user-points`).textContent = `⭐ 积分：${data.points}`;
        showMsg(data.msg);
    } else {
        showMsg(data.msg, true);
    }
}

// 使用玩具
async function useToy(pid) {
    const res = await fetch('pet.php', {
        method: 'POST',
        body: new URLSearchParams({use_toy:1, pet_id:pid})
    });
    const data = await res.json();
    if(data.code === 1) {
        document.getElementById(`mood-val-${pid}`).textContent = data.mood;
        document.getElementById(`pet-exp-${pid}`).textContent = data.exp;
        document.getElementById(`user-points`).textContent = `⭐ 积分：${data.points}`;
        showMsg(data.msg);
    } else {
        showMsg(data.msg, true);
    }
}

// 治疗
async function curePet(pid) {
    const res = await fetch('pet.php', {
        method: 'POST',
        body: new URLSearchParams({cure:1, pet_id:pid})
    });
    const data = await res.json();
    if(data.code === 1) {
        document.getElementById(`pet-sick-${pid}`).style.display = 'none';
        document.getElementById(`btn-cure-${pid}`).style.display = 'none';
        document.getElementById(`btn-normal-${pid}`).style.display = '';
        document.getElementById(`hunger-val-${pid}`).textContent = data.hunger;
        document.getElementById(`mood-val-${pid}`).textContent = data.mood;
        document.getElementById(`user-points`).textContent = `⭐ 积分：${data.points}`;
        showMsg(data.msg);
    } else {
        showMsg(data.msg, true);
    }
}

// 进化
async function evolvePet(pid) {
    const res = await fetch('pet.php', {
        method: 'POST',
        body: new URLSearchParams({evolve:1, pet_id:pid})
    });
    const data = await res.json();
    if(data.code === 1) {
        showMsg(data.msg);
        if(data.refresh) location.reload();
    } else {
        showMsg(data.msg, true);
    }
}

// 改名
async function renamePet(pid) {
    const val = document.getElementById(`rename-input-${pid}`).value.trim();
    if(!val) return;
    const res = await fetch('pet.php', {
        method: 'POST',
        body: new URLSearchParams({rename_pet:1, pet_id:pid, new_name:val})
    });
    const data = await res.json();
    if(data.code === 1) {
        document.getElementById(`pet-name-${pid}`).textContent = data.name;
        document.getElementById(`user-points`).textContent = `⭐ 积分：${data.points}`;
        showMsg(data.msg);
    } else {
        showMsg(data.msg, true);
    }
}

// ====================== PK 相关 ======================
function openPk(tid){
    targetId = tid;
    document.getElementById('pkModal').classList.add('show');
}
function closePk(){
    document.getElementById('pkModal').classList.remove('show');
}
function closeQuiz(){
    document.getElementById('quizModal').classList.remove('show');
}

// 🔥 修复：每次PK重新获取题目
async function startPk(mid){
    closePk();
    
    // 每次PK都重新获取最新题目，永不失效
    try {
        const res = await fetch("pet.php?get_question=1");
        const q = await res.json();
        questionData = q;
        REAL_ANSWER = q.answer;
    } catch(e){}

    document.getElementById('quizBox').innerHTML = `
        <div style='font-weight:bold;margin-bottom:10px;'>${questionData.question}</div>
        <div class='quiz-option' onclick='submitAnswer(${mid},1)'>1. ${questionData.option1}</div>
        <div class='quiz-option' onclick='submitAnswer(${mid},2)'>2. ${questionData.option2}</div>
        <div class='quiz-option' onclick='submitAnswer(${mid},3)'>3. ${questionData.option3}</div>
        <div class='quiz-option' onclick='submitAnswer(${mid},4)'>4. ${questionData.option4}</div>
    `;
    document.getElementById('quizModal').classList.add('show');
}

// 🔥 修复：PK请求增加自动重试 + 超时
async function submitAnswer(myPetId, userAns) {
    closeQuiz();
    const isCorrect = userAns === REAL_ANSWER;

    const pkAnim = document.createElement('div');
    pkAnim.style.cssText = `
        position:fixed;top:0;left:0;width:100%;height:100%;
        background:rgba(0,0,0,0.95);z-index:99999;
        display:flex;align-items:center;justify-content:center;
        flex-direction:column;color:#fff;font-size:24px;font-weight:bold;
    `;
    pkAnim.innerHTML = `
        <div style='font-size:70px;margin-bottom:20px;'>⚔️</div>
        <div style='margin-bottom:10px;'>${isCorrect ? '✅ 回答正确！战力暴涨！' : '❌ 回答错误！战力下降！'}</div>
        <div style='margin-bottom:15px;'>宠物对战中...</div>
        <div style='font-size:40px;color:#fbbf24;'>VS</div>
    `;
    document.body.appendChild(pkAnim);

    // 自动重试 2 次，解决偶尔网络/超时失败
    let retry = 2;
    while (retry-- > 0) {
        try {
            const res = await fetch('pet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `pk_answer=1&my_pet_id=${myPetId}&target_pet_id=${targetId}&qid=${questionData.id}&answer=${userAns}`,
                signal: AbortSignal.timeout(8000) // 8秒超时
            });

            if (!res.ok) throw new Error("请求异常");
            const data = await res.json();
            
            pkAnim.remove();
            showPkResult(data);
            return;
        } catch (err) {
            console.warn("PK 重试中...", err);
            await new Promise(r => setTimeout(r, 600));
        }
    }

    // 最终失败
    pkAnim.remove();
    showMsg("网络不稳定，PK 失败，请稍后重试", true);
}

function showPkResult(data){
    const modal = document.getElementById('pkResultModal');
    const box = document.getElementById('resultBox');
    const icon = document.getElementById('resultIcon');
    const title = document.getElementById('resultTitle');
    const desc = document.getElementById('resultDesc');
    const hunger = document.getElementById('resultHunger');
    
    const cardArea = document.getElementById('cardRewardArea');
    const cardImg = document.getElementById('cardRewardImg');
    const cardName = document.getElementById('cardRewardName');
    const cardDesc = document.getElementById('cardRewardDesc');

    if(data.status === 'limit' || data.status === 'error'){
        icon.textContent = '⚠️';
        title.textContent = 'PK 失败';
        desc.textContent = data.msg;
        hunger.textContent = '';
        cardArea.style.display = 'none';
        box.className = 'pk-result-box';
    } else {
        if(data.result === 'win'){
            icon.textContent = '🎉';
            title.textContent = '挑战胜利！';
            box.className = 'pk-result-box pk-win';
            
            if(data.card){
                cardArea.style.display = 'block';
                cardImg.src = data.card.image;
                cardName.textContent = data.card.name;
                cardDesc.textContent = data.card.desc;
            }else{
                cardArea.style.display = 'none';
            }
            
        } else {
            icon.textContent = '💀';
            title.textContent = '挑战失败！';
            box.className = 'pk-result-box pk-lose';
            cardArea.style.display = 'none';
        }
        desc.textContent = data.msg;
        hunger.textContent = `💡 PK 消耗：饥饿值 -${data.hunger_cost}`;
    }
    modal.classList.add('show');
}

function closePkResult(){
    document.getElementById('pkResultModal').classList.remove('show');
    refreshPetDataAfterPK();
    // 强制刷新卡片面板（安全DOM渲染，不破坏布局）
    refreshMyCardsPanel();
}

// 🔥 终极修复：安全渲染卡片，不破坏原有布局结构
async function refreshMyCardsPanel() {
    try {
        const response = await fetch('pet.php?refresh_cards_data=1');
        const data = await response.json();
        
        const container = document.getElementById('cards-container');
        // 清空容器内所有卡片（保留结构）
        container.innerHTML = '';
        
        // 安全渲染卡片
        data.cards.forEach(card => {
            const cardDiv = document.createElement('div');
            cardDiv.className = 'pet-card';
            cardDiv.innerHTML = `
                <img src="${card.card_image}">
                <div class="pet-name">${card.card_name}</div>
                <div class="pet-info">获得：${new Date(card.obtained_at).toLocaleDateString('zh-CN', {month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'})}</div>
            `;
            container.appendChild(cardDiv);
        });
        
        // 添加集齐数量文字
        const textDiv = document.createElement('div');
        textDiv.className = 'empty';
        textDiv.style.gridColumn = '1/-1';
        textDiv.textContent = `已集齐种类：${data.collected}/${data.total}`;
        container.appendChild(textDiv);
        
    } catch (e) {
        console.log('卡片刷新失败', e);
    }
}

async function refreshPetDataAfterPK() {
    // 刷新用户积分
    const userRes = await fetch('pet.php', {
        method: 'POST',
        body: new URLSearchParams({ ajax_refresh_points: 1 })
    });
    const userData = await userRes.json();
    if (userData.points !== undefined) {
        document.getElementById('user-points').textContent = `⭐ 积分：${userData.points}`;
    }

    // 刷新所有宠物状态
    const petElements = document.querySelectorAll('[id^="pet-"]');
    for (const el of petElements) {
        const pid = el.id.replace('pet-', '');
        if (!pid || isNaN(pid)) continue;

        try {
            const res = await fetch('pet.php', {
                method: 'POST',
                body: new URLSearchParams({ ajax_refresh_pet: 1, pet_id: pid })
            });
            const data = await res.json();
            if (data.code === 1) {
                // 1. 更新等级/经验
                document.getElementById(`pet-level-${pid}`).innerHTML = 
                    `Lv.${data.level} 经验：<span id="pet-exp-${pid}">${data.exp}</span>/${data.level * 20}`;
                
                // 2. 更新饥饿/心情（强制限制在0-上限内，避免超范围）
                const max_hunger = 100 + Math.min(data.level * 5, 100);
                const max_mood = 100 + Math.min(data.level * 5, 100);
                const safe_hunger = Math.max(0, Math.min(data.hunger, max_hunger));
                const safe_mood = Math.max(0, Math.min(data.mood, max_mood));
                
                document.getElementById(`hunger-val-${pid}`).textContent = safe_hunger;
                document.getElementById(`mood-val-${pid}`).textContent = safe_mood;

                // 🔥 3. 核心修复：同步生病状态（数据库is_sick=0时，强制隐藏生病提示）
                const sickEl = document.getElementById(`pet-sick-${pid}`);
                const cureBtn = document.getElementById(`btn-cure-${pid}`);
                const normalBtn = document.getElementById(`btn-normal-${pid}`);
                
                if (data.is_sick == 1) {
                    sickEl.style.display = 'block';
                    cureBtn.style.display = 'block';
                    normalBtn.style.display = 'none';
                } else {
                    sickEl.style.display = 'none';
                    cureBtn.style.display = 'none';
                    normalBtn.style.display = 'block';
                }

                // 4. 更新形态
                let stageText = '';
                if (data.stage == 1) stageText = '幼年期';
                else if (data.stage == 2) stageText = '成熟期';
                else stageText = '终极体';
                document.getElementById(`pet-stage-${pid}`).textContent = stageText;
            }
        } catch (e) {
            console.error('宠物刷新失败:', e);
        }
    }
}
</script>
</body>
</html>