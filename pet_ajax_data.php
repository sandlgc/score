<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$student_id = $_SESSION['user_id'];

// 获取用户积分
$student = $conn->query("SELECT points FROM students WHERE student_id='$student_id'")->fetch_assoc();

// 获取宠物全部信息
$pets = $conn->query("
    SELECT id, level, exp, hunger, mood, is_sick
    FROM student_pets
    WHERE student_id='$student_id'
");

$petList = [];
while ($p = $pets->fetch_assoc()) {
    $petList[] = $p;
}

echo json_encode([
    'points' => (int)$student['points'],
    'pets' => $petList
]);