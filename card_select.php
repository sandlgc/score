<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$student_id = $_SESSION['user_id'];
$pet_id = (int)$_GET['pet_id'];
$pet = $conn->query("SELECT * FROM pet_config WHERE id=$pet_id")->fetch_assoc();
$need = $pet['need_card_count'];
$my_cards = $conn->query("SELECT sc.id as sc_id, c.* FROM student_cards sc JOIN core_values_cards c ON sc.card_id = c.id WHERE sc.student_id='$student_id'");
?>
<!DOCTYPE html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>选择兑换卡片</title>
<style>
body{background:#f7f9fc;font-family:Microsoft YaHei;padding:20px;}
.card-box{max-width:500px;margin:0 auto;background:#fff;border-radius:16px;padding:20px;box-shadow:0 5px 20px #00000010;}
.title{text-align:center;font-size:18px;font-weight:bold;margin-bottom:10px;color:#e84181;}
.tip{text-align:center;color:#666;margin-bottom:15px;}
.card-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
.card-item{border:2px solid #ddd;border-radius:8px;padding:8px;text-align:center;cursor:pointer;}
.card-item.selected{border-color:#ec4899;background:#fef2f8;}
.card-item img{width:70px;height:90px;object-fit:contain;}
.card-name{font-size:12px;margin-top:5px;font-weight:bold;}
.btn-area{text-align:center;margin-top:20px;}
.btn{padding:10px 25px;background:#ec4899;color:#fff;border:none;border-radius:10px;font-weight:bold;cursor:pointer;}
.btn:disabled{background:#ccc;}
</style>
<div class="card-box">
    <div class="title">⭐ 解锁稀有宠物</div>
    <div class="tip">请选择 <span style="color:red;font-weight:bold;"><?=$need?></span> 张卡片兑换</div>
    <form method="post" action="pet2.php" id="cardForm">
        <input type="hidden" name="confirm_rare_unlock" value="1">
        <input type="hidden" name="pet_id" value="<?=$pet_id?>">
        <div class="card-grid" id="cardGrid">
            <?php while($c=$my_cards->fetch_assoc()): ?>
            <div class="card-item" onclick="toggleCard(this,<?=$c['sc_id']?>)">
                <img src="<?=$c['card_image']?>">
                <div class="card-name"><?=$c['card_name']?></div>
            </div>
            <?php endwhile; ?>
        </div>
        <div class="btn-area">
            <button type="submit" class="btn" id="confirmBtn" disabled>确认兑换并解锁</button>
        </div>
    </form>
</div>

<script>
const need = <?=$need?>;
let selected = [];
function toggleCard(el, scid){
    if(el.classList.contains('selected')){
        el.classList.remove('selected');
        selected = selected.filter(id => id != scid);
    }else{
        if(selected.length >= need) return alert('最多选择'+need+'张');
        el.classList.add('selected');
        selected.push(scid);
    }
    updateBtn();
}
function updateBtn(){
    document.getElementById('confirmBtn').disabled = selected.length != need;
}
document.getElementById('cardForm').addEventListener('submit',function(){
    selected.forEach(id=>{
        let inp = document.createElement('input');
        inp.type='hidden';
        inp.name='selected_cards[]';
        inp.value=id;
        this.appendChild(inp);
    });
});
</script>