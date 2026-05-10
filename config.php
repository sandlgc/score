<?php
	
// 生成一个16字节（128位）的随机密钥
define('ENCRYPTION_KEY', bin2hex(random_bytes(16)));

// 数据库配置
$servername = "localhost";
$username = "root";
$password = "jys8873109";
$dbname = "student_points2026";


// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 设置字符编码为 utf8mb4
$conn->set_charset("utf8mb4");
?>