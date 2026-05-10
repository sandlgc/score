<?php
session_start();
include 'config.php';

// 检查是否有必要的参数
if (!isset($_POST['encrypted']) || !isset($_POST['iv']) || !isset($_POST['key'])) {
    http_response_code(400);
    echo "Missing required parameters";
    exit();
}

$encryptedDataBase64 = $_POST['encrypted'];
$ivBase64 = $_POST['iv'];
$key = $_POST['key'];

// 检查是否为有效的 Base64 编码
function isValidBase64($str) {
    return base64_encode(base64_decode($str, true)) === $str;
}

if (!isValidBase64($encryptedDataBase64) || !isValidBase64($ivBase64)) {
    http_response_code(400);
    echo "Invalid Base64 data";
    exit();
}

$encryptedData = base64_decode($encryptedDataBase64);
$iv = base64_decode($ivBase64);

// 解密函数
$decryptedData = openssl_decrypt($encryptedData, 'aes-256-cbc', $key, 0, $iv);

if ($decryptedData === false) {
    http_response_code(500);
    echo "Decryption failed";
    exit();
}

echo $decryptedData;    