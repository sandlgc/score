<?php
include 'config.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// 设置返回JSON
header('Content-Type: application/json; charset=utf-8');

// ====================== 接口处理 ======================
$action = $_REQUEST['action'] ?? '';

// 1. 获取所有章节（筛选用）
if ($action === 'getChapters') {
    $res = $conn->query("SELECT DISTINCT chapter_name FROM questions ORDER BY chapter_name");
    $list = [];
    while ($row = $res->fetch_assoc()) $list[] = $row;
    echo json_encode($list);
    exit;
}

// 2. 获取题目列表（支持筛选）
if ($action === 'getQuestions') {
    $chapter = $_GET['chapter'] ?? '';
    $where = $chapter ? "WHERE chapter_name='$chapter'" : "";
    $res = $conn->query("SELECT * FROM questions $where ORDER BY id DESC");
    $list = [];
    while ($row = $res->fetch_assoc()) $list[] = $row;
    echo json_encode($list);
    exit;
}

// 3. 获取单条题目（编辑用）
if ($action === 'getOneQuestion') {
    $id = $_GET['id'];
    $res = $conn->query("SELECT * FROM questions WHERE id=$id");
    echo json_encode($res->fetch_assoc());
    exit;
}

// 4. 添加题目
if ($action === 'addQuestion') {
    $c = $_POST['chapter_name'];
    $q = $_POST['question'];
    $o1 = $_POST['option1'];
    $o2 = $_POST['option2'];
    $o3 = $_POST['option3'];
    $o4 = $_POST['option4'];
    $ans = $_POST['answer'];
    
    $stmt = $conn->prepare("INSERT INTO questions (chapter_name,question,option1,option2,option3,option4,answer) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss", $c,$q,$o1,$o2,$o3,$o4,$ans);
    echo $stmt->execute() ? "添加成功" : "添加失败：".$stmt->error;
    exit;
}

// 5. 编辑题目
if ($action === 'editQuestion') {
    $id = $_POST['id'];
    $c = $_POST['chapter_name'];
    $q = $_POST['question'];
    $o1 = $_POST['option1'];
    $o2 = $_POST['option2'];
    $o3 = $_POST['option3'];
    $o4 = $_POST['option4'];
    $ans = $_POST['answer'];
    
    $stmt = $conn->prepare("UPDATE questions SET chapter_name=?,question=?,option1=?,option2=?,option3=?,option4=?,answer=? WHERE id=?");
    $stmt->bind_param("sssssssi", $c,$q,$o1,$o2,$o3,$o4,$ans,$id);
    echo $stmt->execute() ? "修改成功" : "修改失败：".$stmt->error;
    exit;
}

// 6. 删除题目
if ($action === 'deleteQuestion') {
    $id = $_GET['id'];
    $res = $conn->query("DELETE FROM questions WHERE id=$id");
    echo $res ? "删除成功" : "删除失败";
    exit;
}

// ====================== Excel 导入 ======================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excel_file"])) {
    $file = $_FILES["excel_file"]["tmp_name"];
    try {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        array_shift($rows);

        $stmt = $conn->prepare("INSERT INTO questions (chapter_name, question, option1, option2, option3, option4, answer) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssss", $chapter_name, $question, $option1, $option2, $option3, $option4, $answer);

        foreach ($rows as $row) {
            $chapter_name = $row[0];
            $question = $row[1];
            $option1 = $row[2];
            $option2 = $row[3];
            $option3 = $row[4];
            $option4 = $row[5];
            $answer = $row[6];
            $stmt->execute();
        }
        echo "导入成功！";
    } catch (Exception $e) {
        echo "导入失败：".$e->getMessage();
    }
    exit;
}

$conn->close();
?>