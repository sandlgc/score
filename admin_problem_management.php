<?php
session_start(); // 启动会话
include 'config.php';

// ========== 身份验证 ==========
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "admin") {
    header("Location: login.php"); // 未登录/非管理员跳转到登录页
    exit();
}

// 设置默认用户名（防止未定义）
$adminUsername = isset($_SESSION['username']) ? $_SESSION['username'] : '管理员';

// ========== 筛选条件处理 ==========
$filterClass = isset($_GET['class']) ? $_GET['class'] : '';
$filterStudent = isset($_GET['student']) ? $_GET['student'] : '';
$filterDateStart = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$filterDateEnd = isset($_GET['date_end']) ? $_GET['date_end'] : '';
$filterAction = isset($_GET['action']) ? $_GET['action'] : '';

// ========== 分页处理 ==========
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = 15; // 每页显示15条
$offset = ($page - 1) * $pageSize;

// ========== 构建查询条件 ==========
$whereConditions = [];
$params = [];

// 基础条件：问题行为（扣分或特定行为）
$whereConditions[] = "pl.points_change < 0 OR pl.action LIKE '%睡觉%' OR pl.action LIKE '%不交%'";

// 班级筛选
if (!empty($filterClass)) {
    $whereConditions[] = "s.class = ?";
    $params[] = $filterClass;
}

// 学生筛选（学号/姓名）
if (!empty($filterStudent)) {
    $whereConditions[] = "(s.student_id LIKE ? OR s.name LIKE ?)";
    $params[] = "%{$filterStudent}%";
    $params[] = "%{$filterStudent}%";
}

// 日期范围筛选
if (!empty($filterDateStart)) {
    $whereConditions[] = "DATE(pl.created_at) >= ?";
    $params[] = $filterDateStart;
}
if (!empty($filterDateEnd)) {
    $whereConditions[] = "DATE(pl.created_at) <= ?";
    $params[] = $filterDateEnd;
}

// 行为类型筛选
if (!empty($filterAction)) {
    $whereConditions[] = "pl.action LIKE ?";
    $params[] = "%{$filterAction}%";
}

$whereSql = implode(" AND ", $whereConditions);

// ========== 获取总记录数 ==========
$countSql = "
    SELECT COUNT(*) as total 
    FROM students s
    JOIN point_logs pl ON s.student_id = pl.student_id
    WHERE {$whereSql}
";
$countStmt = $conn->prepare($countSql);
// 绑定参数
if (!empty($params)) {
    $types = str_repeat('s', count($params)); // 所有参数都按字符串处理
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $pageSize);

// ========== 获取当前页数据 ==========
$listSql = "
    SELECT 
        s.student_id,
        s.name,
        s.class,
        pl.action,
        pl.points_change,
        pl.created_at,
        pl.id as log_id
    FROM students s
    JOIN point_logs pl ON s.student_id = pl.student_id
    WHERE {$whereSql}
    ORDER BY pl.created_at DESC
    LIMIT ? OFFSET ?
";
// 补充分页参数
$params[] = $pageSize;
$params[] = $offset;
$listStmt = $conn->prepare($listSql);
if (!empty($params)) {
    $types = str_repeat('s', count($params) - 2) . 'ii'; // 最后两个是整数（pageSize/offset）
    $listStmt->bind_param($types, ...$params);
}
$listStmt->execute();
$listResult = $listStmt->get_result();
$problemList = [];
while ($row = $listResult->fetch_assoc()) {
    $problemList[] = $row;
}

// ========== 获取所有班级（用于筛选下拉框） ==========
$classSql = "SELECT DISTINCT class FROM students ORDER BY class";
$classResult = $conn->query($classSql);
$classList = [];
while ($row = $classResult->fetch_assoc()) {
    $classList[] = $row['class'];
}

// ========== 处理删除操作 ==========
if (isset($_POST['delete_log'])) {
    $logId = (int)$_POST['log_id'];
    $deleteSql = "DELETE FROM point_logs WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param('i', $logId);
    if ($deleteStmt->execute()) {
        $_SESSION['success_msg'] = "记录删除成功！";
    } else {
        $_SESSION['error_msg'] = "记录删除失败：" . $conn->error;
    }
    header("Location: problem_management.php" . (isset($_GET['class']) ? "?class={$_GET['class']}" : ""));
    exit();
}

// ========== 处理标记已审核操作 ==========
if (isset($_POST['audit_log'])) {
    $logId = (int)$_POST['log_id'];
    $auditSql = "UPDATE point_logs SET is_audited = 1, audit_by = ?, audit_time = NOW() WHERE id = ?";
    $auditStmt = $conn->prepare($auditSql);
    $auditStmt->bind_param('si', $adminUsername, $logId);
    if ($auditStmt->execute()) {
        $_SESSION['success_msg'] = "记录已标记为审核通过！";
    } else {
        $_SESSION['error_msg'] = "审核操作失败：" . $conn->error;
    }
    header("Location: problem_management.php" . (isset($_GET['class']) ? "?class={$_GET['class']}" : ""));
    exit();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>问题行为管理 - 学习积分管理系统</title>
    <link rel="stylesheet" href="fontawesome-free-6.4.0-web/css/all.min.css">
    <style>
        :root {
            --primary: #1890ff;
            --primary-light: #e6f7ff;
            --success: #52c41a;
            --warning: #faad14;
            --danger: #ff4d4f;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --radius-lg: 12px;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Microsoft Yahei', sans-serif;
        }

        body {
            background-color: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.6;
        }

        .navbar {
            background-color: white;
            box-shadow: var(--shadow-md);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .nav-actions {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-600);
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: #096dd9;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #ff1f1f;
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #389e0d;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--gray-200);
            color: var(--gray-600);
        }

        .btn-outline:hover {
            background-color: var(--gray-50);
        }

        .container {
            max-width: 1400px;
            margin: 24px auto;
            padding: 0 24px;
        }

        .page-header {
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 0;
        }

        .page-desc {
            font-size: 14px;
            color: var(--gray-600);
            margin-top: 4px;
        }

        .card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 24px;
            margin-bottom: 24px;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-800);
        }

        .card-title i {
            color: var(--primary);
        }

        /* 筛选区域样式 */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            padding: 16px 20px;
            background-color: var(--gray-50);
            border-radius: 8px;
            margin-bottom: 20px;
            align-items: center;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 180px;
        }

        .filter-label {
            font-size: 12px;
            color: var(--gray-600);
            font-weight: 500;
        }

        .filter-input {
            padding: 8px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 4px;
            font-size: 14px;
            transition: var(--transition);
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-light);
        }

        .table-container {
            overflow-x: auto;
            margin-top: 16px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .data-table th, .data-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .data-table th {
            background-color: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
        }

        .data-table tr:hover {
            background-color: var(--gray-50);
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-danger {
            background-color: #fff2f0;
            color: var(--danger);
        }

        .badge-warning {
            background-color: #fffbe6;
            color: var(--warning);
        }

        .badge-success {
            background-color: #f6ffed;
            color: var(--success);
        }

        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }

        .page-link:hover {
            background-color: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        .page-link.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* 提示消息样式 */
        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background-color: #f6ffed;
            color: var(--success);
            border: 1px solid #b7eb8f;
        }

        .alert-error {
            background-color: #fff2f0;
            color: var(--danger);
            border: 1px solid #ffccc7;
        }

        /* 模态框样式 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            padding: 24px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-100);
        }

        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--gray-600);
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        @media (max-width: 768px) {
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-item {
                min-width: 100%;
            }

            .page-title {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .nav-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }

            .pagination {
                gap: 4px;
            }

            .page-link {
                padding: 6px 10px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <div class="navbar">
        <div class="logo">
            <i class="fas fa-school"></i>
            <span>学习积分管理系统</span>
        </div>
        <div class="nav-actions">
            <div class="user-info">
                <i class="fas fa-user-shield"></i>
                <span>管理员：<?= $adminUsername ?></span>
            </div>
            <a href="admin_board.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> 返回仪表盘
            </a>
            <a href="logout.php" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i> 退出
            </a>
        </div>
    </div>

    <!-- 主内容区 -->
    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">问题行为管理</h1>
                <p class="page-desc">共 <?= $totalRecords ?> 条问题行为记录 | 今日日期：<?= date('Y年m月d日') ?></p>
            </div>
            <div>
                <button class="btn btn-outline" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> 刷新数据
                </button>
                <button class="btn btn-primary" onclick="exportData()">
                    <i class="fas fa-download"></i> 导出记录
                </button>
            </div>
        </div>

        <!-- 提示消息 -->
        <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= $_SESSION['success_msg'] ?>
        </div>
        <?php unset($_SESSION['success_msg']); endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= $_SESSION['error_msg'] ?>
        </div>
        <?php unset($_SESSION['error_msg']); endif; ?>

        <!-- 筛选卡片 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-filter"></i> 筛选条件
            </div>
            <form method="GET" class="filter-bar">
                <div class="filter-item">
                    <label class="filter-label">班级</label>
                    <select name="class" class="filter-input">
                        <option value="">全部班级</option>
                        <?php foreach ($classList as $class): ?>
                        <option value="<?= $class ?>" <?= $filterClass == $class ? 'selected' : '' ?>>
                            <?= $class ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label class="filter-label">学生（学号/姓名）</label>
                    <input type="text" name="student" class="filter-input" placeholder="输入学号或姓名" value="<?= $filterStudent ?>">
                </div>
                <div class="filter-item">
                    <label class="filter-label">开始日期</label>
                    <input type="date" name="date_start" class="filter-input" value="<?= $filterDateStart ?>">
                </div>
                <div class="filter-item">
                    <label class="filter-label">结束日期</label>
                    <input type="date" name="date_end" class="filter-input" value="<?= $filterDateEnd ?>">
                </div>
                <div class="filter-item">
                    <label class="filter-label">行为类型</label>
                    <input type="text" name="action" class="filter-input" placeholder="例如：睡觉、不交作业" value="<?= $filterAction ?>">
                </div>
                <div style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> 筛选
                    </button>
                    <a href="problem_management.php" class="btn btn-outline">
                        <i class="fas fa-reset"></i> 重置
                    </a>
                </div>
            </form>
        </div>

        <!-- 问题行为列表 -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-exclamation-triangle"></i> 问题行为记录列表
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>序号</th>
                            <th>学生信息</th>
                            <th>班级</th>
                            <th>问题行为</th>
                            <th>扣分</th>
                            <th>发生时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($problemList)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--gray-600); padding: 24px;">
                                <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
                                暂无符合条件的问题行为记录
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($problemList as $index => $item): ?>
                        <tr>
                            <td><?= $offset + $index + 1 ?></td>
                            <td>
                                <?= $item['name'] ?> <br>
                                <small style="color: var(--gray-600);"><?= $item['student_id'] ?></small>
                            </td>
                            <td><?= $item['class'] ?></td>
                            <td>
                                <span class="badge badge-danger"><?= $item['action'] ?></span>
                            </td>
                            <td style="color: var(--danger); font-weight: 600;"><?= round($item['points_change'], 2) ?></td>
                            <td><?= date('Y-m-d H:i:s', strtotime($item['created_at'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button class="btn btn-success btn-sm" onclick="showAuditModal(<?= $item['log_id'] ?>)">
                                        <i class="fas fa-check"></i> 审核
                                    </button>
                                    <button class="btn btn-outline btn-sm" onclick="viewDetail(<?= $item['log_id'] ?>)">
                                        <i class="fas fa-eye"></i> 详情
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="showDeleteModal(<?= $item['log_id'] ?>)">
                                        <i class="fas fa-trash"></i> 删除
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 分页 -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a href="?page=1&class=<?= $filterClass ?>&student=<?= $filterStudent ?>&date_start=<?= $filterDateStart ?>&date_end=<?= $filterDateEnd ?>&action=<?= $filterAction ?>" class="page-link">
                        <i class="fas fa-angle-double-left"></i> 首页
                    </a>
                </li>
                <li class="page-item">
                    <a href="?page=<?= $page - 1 ?>&class=<?= $filterClass ?>&student=<?= $filterStudent ?>&date_start=<?= $filterDateStart ?>&date_end=<?= $filterDateEnd ?>&action=<?= $filterAction ?>" class="page-link">
                        <i class="fas fa-angle-left"></i> 上一页
                    </a>
                </li>
                <?php endif; ?>

                <?php
                // 显示当前页前后各2页
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <li class="page-item">
                    <a href="?page=<?= $i ?>&class=<?= $filterClass ?>&student=<?= $filterStudent ?>&date_start=<?= $filterDateStart ?>&date_end=<?= $filterDateEnd ?>&action=<?= $filterAction ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a href="?page=<?= $page + 1 ?>&class=<?= $filterClass ?>&student=<?= $filterStudent ?>&date_start=<?= $filterDateStart ?>&date_end=<?= $filterDateEnd ?>&action=<?= $filterAction ?>" class="page-link">
                        下一页 <i class="fas fa-angle-right"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a href="?page=<?= $totalPages ?>&class=<?= $filterClass ?>&student=<?= $filterStudent ?>&date_start=<?= $filterDateStart ?>&date_end=<?= $filterDateEnd ?>&action=<?= $filterAction ?>" class="page-link">
                        尾页 <i class="fas fa-angle-double-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 删除确认模态框 -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">删除确认</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>确定要删除这条问题行为记录吗？此操作不可恢复！</p>
                <input type="hidden" id="delete_log_id" value="">
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('deleteModal')">取消</button>
                <button class="btn btn-danger" onclick="confirmDelete()">确认删除</button>
            </div>
        </div>
    </div>

    <!-- 审核确认模态框 -->
    <div class="modal" id="auditModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">审核确认</h3>
                <button class="modal-close" onclick="closeModal('auditModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>确定要标记这条问题行为记录为已审核吗？</p>
                <input type="hidden" id="audit_log_id" value="">
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('auditModal')">取消</button>
                <button class="btn btn-success" onclick="confirmAudit()">确认审核</button>
            </div>
        </div>
    </div>

    <!-- 详情查看模态框 -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">问题行为详情</h3>
                <button class="modal-close" onclick="closeModal('detailModal')">&times;</button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- 详情内容将通过JS动态加载 -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="closeModal('detailModal')">关闭</button>
            </div>
        </div>
    </div>

    <script>
        // 打开删除模态框
        function showDeleteModal(logId) {
            document.getElementById('delete_log_id').value = logId;
            document.getElementById('deleteModal').classList.add('active');
        }

        // 关闭模态框
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // 确认删除
        function confirmDelete() {
            const logId = document.getElementById('delete_log_id').value;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'problem_management.php<?= !empty($_GET) ? '?' . http_build_query($_GET) : '' ?>';
            
            const logIdInput = document.createElement('input');
            logIdInput.type = 'hidden';
            logIdInput.name = 'log_id';
            logIdInput.value = logId;
            
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_log';
            deleteInput.value = '1';
            
            form.appendChild(logIdInput);
            form.appendChild(deleteInput);
            document.body.appendChild(form);
            form.submit();
        }

        // 打开审核模态框
        function showAuditModal(logId) {
            document.getElementById('audit_log_id').value = logId;
            document.getElementById('auditModal').classList.add('active');
        }

        // 确认审核
        function confirmAudit() {
            const logId = document.getElementById('audit_log_id').value;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'problem_management.php<?= !empty($_GET) ? '?' . http_build_query($_GET) : '' ?>';
            
            const logIdInput = document.createElement('input');
            logIdInput.type = 'hidden';
            logIdInput.name = 'log_id';
            logIdInput.value = logId;
            
            const auditInput = document.createElement('input');
            auditInput.type = 'hidden';
            auditInput.name = 'audit_log';
            auditInput.value = '1';
            
            form.appendChild(logIdInput);
            form.appendChild(auditInput);
            document.body.appendChild(form);
            form.submit();
        }

        // 查看详情
        function viewDetail(logId) {
            // 模拟加载详情（实际项目可通过AJAX获取）
            const detailContent = document.getElementById('detailContent');
            // 这里简化处理，实际可根据logId查询数据库获取更多详情
            detailContent.innerHTML = `
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 30%; padding: 8px 0; color: var(--gray-600);">记录ID：</td>
                        <td style="padding: 8px 0;">${logId}</td>
                    </tr>
                    <tr>
                        <td style="width: 30%; padding: 8px 0; color: var(--gray-600);">学生信息：</td>
                        <td style="padding: 8px 0;">${document.querySelector(`tr:has(input[value="${logId}"]) td:nth-child(2)`).innerText}</td>
                    </tr>
                    <tr>
                        <td style="width: 30%; padding: 8px 0; color: var(--gray-600);">班级：</td>
                        <td style="padding: 8px 0;">${document.querySelector(`tr:has(input[value="${logId}"]) td:nth-child(3)`).innerText}</td>
                    </tr>
                    <tr>
                        <td style="width: 30%; padding: 8px 0; color: var(--gray-600);">问题行为：</td>
                        <td style="padding: 8px 0;">${document.querySelector(`tr:has(input[value="${logId}"]) td:nth-child(4)`).innerText}</td>
                    </tr>
                    <tr>
                        <td style="width: 30%; padding: 8px 0; color: var(--gray-600);">扣分：</td>
                        <td style="padding: 8px 0; color: var(--danger); font-weight: 600;">${document.querySelector(`tr:has(input[value="${logId}"]) td:nth-child(5)`).innerText}</td>
                    </tr>
                    <tr>
                        <td style="width: 30%; padding: 8px 0; color: var(--gray-600);">发生时间：</td>
                        <td style="padding: 8px 0;">${document.querySelector(`tr:has(input[value="${logId}"]) td:nth-child(6)`).innerText}</td>
                    </tr>
                    <tr>
                        <td style="width: 30%; padding: 8px 0; color: var(--gray-600);">审核状态：</td>
                        <td style="padding: 8px 0;"><span class="badge badge-warning">未审核</span></td>
                    </tr>
                </table>
            `;
            document.getElementById('detailModal').classList.add('active');
        }

        // 导出数据
        function exportData() {
            // 构建导出URL
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = 'problem_management.php?' + params.toString();
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>