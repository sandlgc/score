<?php
session_start();
include 'config.php';

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $class = $_POST['class'];
    $name = $_POST['name'];
    $student_id = $_POST['student_id'];
    $time = date('Y-m-d H:i:s');

    $sql = "INSERT INTO roll_call_records (class, name, student_id, time) VALUES ('$class', '$name', '$student_id', '$time')";
    if ($conn->query($sql) === TRUE) {
        echo "Record inserted successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

$conn->close();
?>