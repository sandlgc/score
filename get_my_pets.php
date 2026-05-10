<?php
session_start();
include 'config.php';

if(!isset($_SESSION['user_id'])) exit;
$sid = $_SESSION['user_id'];

$pets = $conn->query("SELECT sp.*, pc.pet_image, pc.pet_image_stage2, pc.pet_image_stage3
                      FROM student_pets sp
                      JOIN pet_config pc ON sp.pet_id=pc.id
                      WHERE sp.student_id='$sid'");

while($pet = $pets->fetch_assoc()):
$stage = $pet['stage'] ?? 1;
if($stage==1) $img = $pet['pet_image'];
elseif($stage==2) $img = $pet['pet_image_stage2'];
else $img = $pet['pet_image_stage3'];
?>

<div class="pet-select-card" onclick="selectPet(<?= $pet['id'] ?>)">
    <img src="<?= $img ?>">
    <div class="pet-select-name"><?= $pet['pet_nickname'] ?></div>
    <div style="font-size:11px;">Lv.<?= $pet['level'] ?></div>
</div>

<?php endwhile; ?>