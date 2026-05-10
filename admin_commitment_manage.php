<?php
session_start();
include 'config.php';

// 验证管理员登录状态
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php");
    exit();
}

// ========== 新增：处理删除请求 ==========
// 修复：先判断是否存在POST['action']，避免未定义索引警告
if (isset($_POST['action']) && $_POST['action'] == 'delete_commitment' && isset($_POST['student_id'])) {
    $student_id = $_POST['student_id'];
    // 执行删除操作
    $delete_sql = "DELETE FROM student_discipline_commitment WHERE student_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("s", $student_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '承诺书记录删除成功！']);
    } else {
        echo json_encode(['success' => false, 'message' => '删除失败：' . $conn->error]);
    }
    $stmt->close();
    $conn->close(); // 新增：关闭数据库连接
    exit();
}

// ========== 获取筛选和搜索条件 ==========
$filter = $_GET['filter'] ?? 'all';
$search_key = $_GET['search'] ?? ''; // 搜索关键词

// 1. 查询系统配置（家长签名是否必填）
$sql_config = "SELECT config_value FROM system_config WHERE config_key = 'parent_signature_required'";
$result_config = $conn->query($sql_config);
$parent_signature_required = $result_config->fetch_assoc()['config_value'] ?? '1';

// 2. 构建查询条件
$where_conditions = [];
// 筛选条件
switch ($filter) {
    case 'signed': // 已签署
        $where_conditions[] = "c.status = 1";
        break;
    case 'unsigned': // 未签署
        $where_conditions[] = "c.status = 0 OR c.status IS NULL";
        break;
    case 'agreed': // 已同意
        $where_conditions[] = "c.agree_status = 1";
        break;
    case 'disagreed': // 不同意
        $where_conditions[] = "c.status = 1 AND c.agree_status = 0";
        break;
    case 'all': // 全部
    default:
        break;
}

// 新增：搜索条件（学号/姓名/班级模糊匹配）
if (!empty($search_key)) {
    $search_escaped = $conn->real_escape_string($search_key);
    $where_conditions[] = "(s.student_id LIKE '%{$search_escaped}%' OR s.name LIKE '%{$search_escaped}%' OR s.class LIKE '%{$search_escaped}%')";
}

// 组合WHERE子句
$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// 3. 查询所有学生承诺书信息
$sql = "SELECT 
            s.student_id, 
            s.name, 
            s.class, 
            c.status, 
            c.agree_status, 
            c.signed_time, 
            c.signature_data, 
            c.parent_signature_data 
        FROM students s
        LEFT JOIN student_discipline_commitment c ON s.student_id = c.student_id
        $where_clause
        ORDER BY s.class, s.name";
$result = $conn->query($sql);

// 4. 统计各类状态数量
// 总学生数
$total_sql = "SELECT COUNT(*) as total FROM students";
$total_result = $conn->query($total_sql);
$total_students = $total_result->fetch_assoc()['total'];

// 已签署数
$signed_sql = "SELECT COUNT(*) as count FROM student_discipline_commitment WHERE status = 1";
$signed_result = $conn->query($signed_sql);
$total_signed = $signed_result->fetch_assoc()['count'];

// 未签署数
$unsigned_sql = "SELECT COUNT(*) as count FROM students s LEFT JOIN student_discipline_commitment c ON s.student_id = c.student_id WHERE c.status = 0 OR c.status IS NULL";
$unsigned_result = $conn->query($unsigned_sql);
$total_unsigned = $unsigned_result->fetch_assoc()['count'];

// 已同意数
$agreed_sql = "SELECT COUNT(*) as count FROM student_discipline_commitment WHERE agree_status = 1";
$agreed_result = $conn->query($agreed_sql);
$total_agreed = $agreed_result->fetch_assoc()['count'];

// 不同意数
$disagreed_sql = "SELECT COUNT(*) as count FROM student_discipline_commitment WHERE status = 1 AND agree_status = 0";
$disagreed_result = $conn->query($disagreed_sql);
$total_disagreed = $disagreed_result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>承诺书管理 - 管理员后台</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #007BFF;
        }

        .header h1 {
            color: #007BFF;
            font-size: 2em;
        }

        .logout-btn {
            background-color: #dc3545;
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        .logout-btn:hover {
            background-color: #c82333;
        }

        /* 统计卡片样式 */
        .stats-container {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .stat-card {
            flex: 1;
            min-width: 200px;
            padding: 20px;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-card.total {
            border-top: 4px solid #007BFF;
        }

        .stat-card.signed {
            border-top: 4px solid #28a745;
        }

        .stat-card.unsigned {
            border-top: 4px solid #ffc107;
        }

        .stat-card.agreed {
            border-top: 4px solid #17a2b8;
        }

        .stat-card.disagreed {
            border-top: 4px solid #dc3545;
        }

        .stat-card h3 {
            font-size: 1.2em;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
        }

        /* 筛选和搜索栏样式 */
        .filter-search-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex: 1;
            min-width: 300px;
        }

        .filter-group label, .search-group label {
            font-weight: bold;
            margin-right: 5px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background-color: #007BFF;
            color: #fff;
            border-color: #007BFF;
        }

        .filter-btn:hover:not(.active) {
            background-color: #f1f1f1;
        }

        .search-input {
            flex: 1;
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }

        .search-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            background-color: #007BFF;
            color: #fff;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .search-btn:hover {
            background-color: #0056b3;
        }

        .reset-btn {
            padding: 8px 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .reset-btn:hover {
            background-color: #f1f1f1;
        }

        /* 表格样式 */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th, td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        /* 状态标签样式 */
        .status-tag {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }

        .status-signed {
            background-color: #28a745;
            color: #fff;
        }

        .status-unsigned {
            background-color: #ffc107;
            color: #212529;
        }

        .status-agreed {
            background-color: #17a2b8;
            color: #fff;
        }

        .status-disagreed {
            background-color: #dc3545;
            color: #fff;
        }

        /* 签名图片样式 */
        .signature-img {
            max-width: 100px;
            max-height: 80px;
            cursor: pointer;
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 5px;
            transition: transform 0.3s ease;
        }

        .signature-img:hover {
            transform: scale(1.05);
        }

        /* 操作按钮样式 */
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s ease;
            margin: 0 5px;
        }

        .delete-btn {
            background-color: #dc3545;
            color: #fff;
        }

        .delete-btn:hover {
            background-color: #c82333;
        }

        /* 签名预览弹窗 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            max-width: 80%;
            max-height: 80%;
            overflow: auto;
            text-align: center;
        }

        .modal-img {
            max-width: 100%;
            max-height: 70vh;
        }

        .close-modal {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #007BFF;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .close-modal:hover {
            background-color: #0056b3;
        }

        /* 删除确认弹窗 */
        .confirm-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            align-items: center;
            justify-content: center;
        }

        .confirm-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        .confirm-content h3 {
            color: #dc3545;
            margin-bottom: 20px;
        }

        .confirm-content p {
            margin-bottom: 30px;
            font-size: 1.1em;
        }

        .confirm-btns {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .confirm-btn {
            padding: 10px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }

        .confirm-yes {
            background-color: #dc3545;
            color: #fff;
        }

        .confirm-no {
            background-color: #6c757d;
            color: #fff;
        }

        /* 提示消息样式 */
        .message-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: #fff;
            font-weight: bold;
            z-index: 2000;
            display: none;
            animation: fadeInOut 3s ease;
        }

        .toast-success {
            background-color: #28a745;
        }

        .toast-error {
            background-color: #dc3545;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            80% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); }
        }

        /* 无数据提示 */
        .no-data {
            text-align: center;
            padding: 50px;
            color: #666;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>学生课堂纪律承诺书管理</h1>
            <a href="admin_menu.php" class="logout-btn">返回管理员菜单</a>
        </div>

        <!-- 统计卡片 -->
        <div class="stats-container">
            <div class="stat-card total">
                <h3>总学生数</h3>
                <div class="number"><?php echo $total_students; ?></div>
            </div>
            <div class="stat-card signed">
                <h3>已签署承诺书</h3>
                <div class="number"><?php echo $total_signed; ?></div>
            </div>
            <div class="stat-card unsigned">
                <h3>未签署承诺书</h3>
                <div class="number"><?php echo $total_unsigned; ?></div>
            </div>
            <div class="stat-card agreed">
                <h3>已同意承诺书</h3>
                <div class="number"><?php echo $total_agreed; ?></div>
            </div>
            <div class="stat-card disagreed">
                <h3>不同意承诺书</h3>
                <div class="number"><?php echo $total_disagreed; ?></div>
            </div>
        </div>

        <!-- 筛选和搜索栏（新增：搜索功能） -->
        <div class="filter-search-bar">
            <div class="filter-group">
                <label>筛选状态：</label>
                <a href="?filter=all&search=<?php echo urlencode($search_key); ?>" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">全部学生</a>
                <a href="?filter=signed&search=<?php echo urlencode($search_key); ?>" class="filter-btn <?php echo $filter == 'signed' ? 'active' : ''; ?>">已签署</a>
                <a href="?filter=unsigned&search=<?php echo urlencode($search_key); ?>" class="filter-btn <?php echo $filter == 'unsigned' ? 'active' : ''; ?>">未签署</a>
                <a href="?filter=agreed&search=<?php echo urlencode($search_key); ?>" class="filter-btn <?php echo $filter == 'agreed' ? 'active' : ''; ?>">已同意</a>
                <a href="?filter=disagreed&search=<?php echo urlencode($search_key); ?>" class="filter-btn <?php echo $filter == 'disagreed' ? 'active' : ''; ?>">不同意</a>
            </div>

            <div class="search-group">
                <label>搜索：</label>
                <input type="text" class="search-input" id="searchInput" placeholder="请输入学号/姓名/班级关键词" value="<?php echo htmlspecialchars($search_key); ?>">
                <button class="search-btn" id="searchBtn">搜索</button>
                <button class="reset-btn" id="resetBtn">重置</button>
            </div>
        </div>

        <!-- 学生列表表格 -->
        <div class="table-container">
            <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>序号</th>
                        <th>学号</th>
                        <th>姓名</th>
                        <th>班级</th>
                        <th>签署状态</th>
                        <th>同意状态</th>
                        <th>签署时间</th>
                        <th>学生签名</th>
                        <?php if ($parent_signature_required == '1'): ?>
                        <th>家长签名</th>
                        <?php endif; ?>
                        <th>操作</th> <!-- 新增：操作列 -->
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $index = 1;
                    while ($row = $result->fetch_assoc()): 
                        $status = $row['status'] ?? 0;
                        $agree_status = $row['agree_status'] ?? 0;
                        $signed_time = $row['signed_time'] ? date('Y-m-d H:i:s', strtotime($row['signed_time'])) : '-';
                        $student_signature = $row['signature_data'] ?? '';
                        $parent_signature = $row['parent_signature_data'] ?? '';
                        $student_id = $row['student_id'];
                        $student_name = $row['name'];
                    ?>
                    <tr id="row_<?php echo $student_id; ?>">
                        <td><?php echo $index++; ?></td>
                        <td><?php echo $student_id; ?></td>
                        <td><?php echo $student_name; ?></td>
                        <td><?php echo $row['class']; ?></td>
                        <td>
                            <span class="status-tag <?php echo $status ? 'status-signed' : 'status-unsigned'; ?>">
                                <?php echo $status ? '已签署' : '未签署'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($status): ?>
                            <span class="status-tag <?php echo $agree_status ? 'status-agreed' : 'status-disagreed'; ?>">
                                <?php echo $agree_status ? '同意' : '不同意'; ?>
                            </span>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td><?php echo $signed_time; ?></td>
                        <td>
                            <?php if ($student_signature): ?>
                            <img src="<?php echo $student_signature; ?>" class="signature-img" onclick="openModal('<?php echo $student_signature; ?>', '学生签名 - <?php echo $student_name; ?>')" alt="学生签名">
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <?php if ($parent_signature_required == '1'): ?>
                        <td>
                            <?php if ($parent_signature): ?>
                            <img src="<?php echo $parent_signature; ?>" class="signature-img" onclick="openModal('<?php echo $parent_signature; ?>', '家长签名 - <?php echo $student_name; ?>')" alt="家长签名">
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td> <!-- 新增：删除按钮 -->
                            <?php if ($status): ?>
                            <button class="action-btn delete-btn" onclick="showConfirmModal('<?php echo $student_id; ?>', '<?php echo $student_name; ?>')">删除记录</button>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                暂无符合条件的学生数据
            </div>
            <?php endif; ?>
        </div>

        <!-- 签名预览弹窗 -->
        <div id="signatureModal" class="modal">
            <div class="modal-content">
                <h3 id="modalTitle"></h3>
                <img id="modalImage" class="modal-img" alt="签名预览">
                <button class="close-modal" onclick="closeModal()">关闭</button>
            </div>
        </div>

        <!-- 新增：删除确认弹窗 -->
        <div id="confirmModal" class="confirm-modal">
            <div class="confirm-content">
                <h3>确认删除</h3>
                <p id="confirmText">您确定要删除 <strong>XXX</strong> 同学的承诺书签署记录吗？删除后该同学需重新签署！</p>
                <div class="confirm-btns">
                    <button class="confirm-btn confirm-yes" onclick="confirmDelete()">确认删除</button>
                    <button class="confirm-btn confirm-no" onclick="closeConfirmModal()">取消</button>
                </div>
            </div>
        </div>

        <!-- 提示消息弹窗 -->
        <div id="messageToast" class="message-toast"></div>
    </div>

    <script>
        // 全局变量：当前要删除的学生ID
        let currentDeleteStudentId = '';

        // 打开签名预览弹窗
        function openModal(imgSrc, title) {
            const modal = document.getElementById('signatureModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalImage = document.getElementById('modalImage');
            
            modalTitle.textContent = title;
            modalImage.src = imgSrc;
            modal.style.display = 'flex';
        }

        // 关闭签名弹窗
        function closeModal() {
            const modal = document.getElementById('signatureModal');
            modal.style.display = 'none';
        }

        // 点击弹窗外区域关闭
        window.onclick = function(event) {
            const modal = document.getElementById('signatureModal');
            const confirmModal = document.getElementById('confirmModal');
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == confirmModal) {
                closeConfirmModal();
            }
        }

        // 新增：显示删除确认弹窗
        function showConfirmModal(studentId, studentName) {
            currentDeleteStudentId = studentId;
            const confirmModal = document.getElementById('confirmModal');
            const confirmText = document.getElementById('confirmText');
            confirmText.innerHTML = `您确定要删除 <strong>${studentName}</strong> 同学的承诺书签署记录吗？删除后该同学需重新签署！`;
            confirmModal.style.display = 'flex';
        }

        // 新增：关闭删除确认弹窗
        function closeConfirmModal() {
            const confirmModal = document.getElementById('confirmModal');
            confirmModal.style.display = 'none';
            currentDeleteStudentId = '';
        }

        // 新增：确认删除操作
        function confirmDelete() {
            if (!currentDeleteStudentId) {
                showMessage('无效的学生信息！', 'error');
                closeConfirmModal();
                return;
            }

            // 发送AJAX请求删除
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                closeConfirmModal();
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showMessage(response.message, 'success');
                            // 删除表格行
                            const row = document.getElementById(`row_${currentDeleteStudentId}`);
                            if (row) {
                                row.remove();
                                // 检查是否还有数据行
                                const tableRows = document.querySelectorAll('table tbody tr');
                                if (tableRows.length === 0) {
                                    document.querySelector('.table-container').innerHTML = '<div class="no-data">暂无符合条件的学生数据</div>';
                                }
                            }
                        } else {
                            showMessage(response.message, 'error');
                        }
                    } catch (e) {
                        showMessage('处理失败：' + e.message, 'error');
                    }
                } else {
                    showMessage('服务器错误，请稍后重试！', 'error');
                }
            };
            xhr.send(`action=delete_commitment&student_id=${encodeURIComponent(currentDeleteStudentId)}`);
        }

        // 新增：显示提示消息
        function showMessage(message, type) {
            const toast = document.getElementById('messageToast');
            toast.textContent = message;
            toast.className = `message-toast toast-${type}`;
            toast.style.display = 'block';
            
            // 3秒后隐藏
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        // 新增：搜索功能
        document.getElementById('searchBtn').addEventListener('click', function() {
            const searchKey = document.getElementById('searchInput').value.trim();
            const currentFilter = '<?php echo $filter; ?>';
            // 构建跳转URL
            let url = `?filter=${currentFilter}`;
            if (searchKey) {
                url += `&search=${encodeURIComponent(searchKey)}`;
            }
            window.location.href = url;
        });

        // 新增：重置搜索
        document.getElementById('resetBtn').addEventListener('click', function() {
            document.getElementById('searchInput').value = '';
            const currentFilter = '<?php echo $filter; ?>';
            window.location.href = `?filter=${currentFilter}`;
        });

        // 新增：回车触发搜索
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchBtn').click();
            }
        });
    </script>
</body>
</html>