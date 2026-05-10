<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $encryptedDataBase64 = $_POST['encrypted'];
    $ivBase64 = $_POST['iv'];
    $key = $_POST['key'];

    $encryptedData = base64_decode($encryptedDataBase64);
    $iv = base64_decode($ivBase64);

    $decryptedData = openssl_decrypt($encryptedData, 'aes-256-cbc', $key, 0, $iv);

    echo $decryptedData;
}
?>