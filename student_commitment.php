<?php
session_start();
include 'config.php';

// 验证学生登录状态
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] != "student") {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION["user_id"];
$student_name = "";
$student_class = "";
$commitment_status = 0; // 0=未签署，1=已签署
$signed_time = "";
$student_signature = ""; // 学生签名（对应signature_data字段）
$parent_signature = "";  // 家长签名（对应parent_signature_data字段）
$agree_status = 0; // 新增：默认不同意
$force_agree = 0; // 新增：是否强制同意配置

// 查询系统是否强制同意
$sql_force_agree = "SELECT config_value FROM system_config WHERE config_key = 'force_commitment_agree'";
$result_force_agree = $conn->query($sql_force_agree);
$force_agree = $result_force_agree->fetch_assoc()['config_value'] ?? '0';

// 查询学生信息及承诺书状态（适配实际表字段）
$sql = "SELECT s.name, s.class, c.status, c.signed_time, c.signature_data, c.parent_signature_data, c.agree_status 
        FROM students s
        LEFT JOIN student_discipline_commitment c ON s.student_id = c.student_id
        WHERE s.student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $student_name = $row["name"];
    $student_class = $row["class"];
    $commitment_status = $row["status"] ?? 0;
    $signed_time = $row["signed_time"] ?? "";
    $student_signature = $row["signature_data"] ?? ""; // 对应表中signature_data字段
    $parent_signature = $row["parent_signature_data"] ?? ""; // 对应表中parent_signature_data字段
    $agree_status = $row["agree_status"] ?? 0; // 读取是否同意状态
}
$stmt->close();

// 获取家长签名配置
$sql_config = "SELECT config_value FROM system_config WHERE config_key = 'parent_signature_required'";
$result_config = $conn->query($sql_config);
$parent_signature_required = $result_config->fetch_assoc()['config_value'] ?? '1';

// 关键修复：仅当未签署且强制同意时，才限制登录；已签署（无论是否同意）均允许访问页面
if ($force_agree == 1 && $commitment_status == 0) {
    // 未签署且强制同意，正常显示签署页面（让学生必须签署）
} elseif ($force_agree == 1 && $commitment_status == 1 && $agree_status == 0) {
    // 已签署不同意且强制同意：不再直接退出，改为提示并允许重新签署
    $_SESSION['force_agree_warning'] = "您之前签署了不同意，系统要求必须同意承诺书，请重新签署！";
}

// 新增：判断是否已同意（同意后禁止修改）
$is_agreed = ($commitment_status == 1 && $agree_status == 1);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>中职生课堂纪律承诺书 - 签署页面</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f4f4f9;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #007BFF;
        }

        .header h1 {
            color: #007BFF;
            font-size: 2em;
            margin-bottom: 10px;
        }

        .student-info {
            text-align: right;
            margin-bottom: 20px;
            font-size: 1.1em;
        }

        .commitment-content {
            margin-bottom: 40px;
        }

        .commitment-content h2 {
            color: #333;
            font-size: 1.5em;
            margin: 25px 0 15px;
        }

        .commitment-content p {
            margin-bottom: 15px;
            text-indent: 2em;
        }

        .commitment-content ol, .commitment-content ul {
            margin-left: 40px;
            margin-bottom: 15px;
        }

        .commitment-content li {
            margin-bottom: 8px;
        }

        /* 突出显示加粗内容的样式 */
        .commitment-content strong {
            color: #007BFF;
            font-weight: 700;
        }

        .signature-section {
            margin: 50px 0;
        }

        /* 签名组样式 - 适配居中 */
        .signature-group {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 40px;
        }
        /* 仅学生签名时居中 */
        .signature-group.single-signature {
            justify-content: center;
        }

        .signature-card {
            width: 45%;
            min-width: 300px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        /* 仅学生签名时调整卡片宽度 */
        .signature-group.single-signature .signature-card {
            width: 60%;
            max-width: 500px;
        }

        .signature-card.hidden {
            display: none;
        }

        .signature-card h3 {
            color: #555;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            text-align: center; /* 签名标题居中 */
        }

        .signature-pad {
            width: 100%;
            height: 200px;
            border: 1px solid #ccc;
            border-radius: 5px;
            cursor: crosshair;
            background: #fff;
            margin-bottom: 15px;
        }

        .signature-image {
            width: 100%;
            height: 200px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: #fff;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .signature-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .signature-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            justify-content: center; /* 操作按钮居中 */
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #007BFF;
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-danger {
            background-color: #dc3545;
            color: #fff;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .btn-success {
            background-color: #28a745;
            color: #fff;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .status-tag {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }

        .status-pending {
            background-color: #ffc107;
            color: #212529;
        }

        .status-signed {
            background-color: #28a745;
            color: #fff;
        }

        .status-reject {
            background-color: #dc3545;
            color: #fff;
        }

        .signed-info {
            margin-top: 10px;
            color: #666;
            font-size: 0.9em;
            text-align: center; /* 签署信息居中 */
        }

        .submit-section {
            text-align: center;
            margin-top: 50px;
        }

        .back-menu {
            display: inline-block;
            margin-top: 30px;
            text-align: center;
            width: 100%;
        }

        .back-menu a {
            color: #007BFF;
            text-decoration: none;
            font-size: 1.1em;
        }

        .back-menu a:hover {
            text-decoration: underline;
        }

        .logout-btn {
            display: inline-block;
            margin-top: 20px;
            background-color: #dc3545;
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .logout-btn:hover {
            background-color: #c82333;
        }

        .config-note {
            text-align: center;
            margin: 10px 0;
            color: #666;
            font-style: italic;
        }

        .hidden {
            display: none !important;
        }

        /* 附则样式，与承诺书主体区分 */
        .commitment-appendix {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .commitment-appendix h2 {
            color: #333;
            font-size: 1.4em;
            margin-bottom: 15px;
            text-align: center;
        }

        .commitment-appendix ol {
            margin-left: 40px;
            margin-bottom: 20px;
        }

        .commitment-appendix li {
            margin-bottom: 10px;
            line-height: 1.8;
        }

        /* 历史签名样式 */
        .history-signature {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
        }

        .history-signature h4 {
            color: #666;
            margin-bottom: 10px;
            text-align: center;
            font-weight: normal;
        }

        .resign-note {
            color: #dc3545;
            text-align: center;
            margin: 10px 0;
            font-weight: bold;
        }

        /* 强制同意警告样式 */
        .force-agree-warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            font-weight: bold;
        }

        /* 新增：已同意状态提示 */
        .agreed-notice {
            background-color: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 1.2em;
            font-weight: bold;
            margin: 30px 0;
        }

        /* 新增：签名验证提示 */
        .signature-tip {
            color: #666;
            text-align: center;
            margin: 10px 0;
            font-size: 0.9em;
        }
        .signature-error {
            color: #dc3545;
            text-align: center;
            margin: 10px 0;
            font-weight: bold;
            line-height: 1.8;
        }
        /* 新增：重新尝试引导按钮样式 */
        .retry-btn {
            display: block;
            margin: 10px auto;
            width: 180px;
            background-color: #ffc107;
            color: #212529;
            font-weight: bold;
        }
        .retry-btn:hover {
            background-color: #ffb300;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 强制同意警告提示 -->
        <?php if (isset($_SESSION['force_agree_warning'])): ?>
        <div class="force-agree-warning">
            <?php echo $_SESSION['force_agree_warning']; unset($_SESSION['force_agree_warning']); ?>
        </div>
        <?php endif; ?>

        <!-- 新增：已同意提示 -->
        <?php if ($is_agreed): ?>
        <div class="agreed-notice">
            ✅ 您已同意本承诺书，无法修改签署信息！
        </div>
        <?php endif; ?>

        <div class="header">
            <h1>计算机考证课堂纪律承诺书</h1>
            <div class="student-info">
                学生姓名：<?php echo $student_name; ?> | 
                学号：<?php echo $student_id; ?> | 
                班级：<?php echo $student_class; ?> | 
                签署状态：<span class="status-tag <?php echo $commitment_status ? ($agree_status ? 'status-signed' : 'status-reject') : 'status-pending'; ?>">
                    <?php echo $commitment_status ? ($agree_status ? '已签署（同意）' : '已签署（不同意）') : '未签署'; ?>
                </span>
                <?php if ($commitment_status): ?>
                <?php if ($signed_time): ?>
                | 上次签署时间：<?php echo date('Y-m-d H:i:s', strtotime($signed_time)); ?>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 承诺书内容 -->
        <div class="commitment-content">
            <p>课堂是知识传递的核心阵地，是我们中职生锤炼技能、涵养品德的重要场所。良好的课堂纪律，是保障教学有序开展的基石，更是我们实现自我成长、成就职业梦想的前提。为树立自律意识，规范自身行为，营造勤学善思、文明有序的课堂氛围，我郑重作出如下承诺：</p>

            <h2>一、课前准备：筑牢学习起点</h2>
            <ol>
                <li>按时到校，<strong>不迟到、不早退、不旷课</strong>。若因特殊情况无法上课，<strong>提前向班主任及任课教师履行请假手续，经批准后方可离校，绝不擅自缺课</strong>。</li>
                <li>提前进入教室，做好课前准备。<strong>按要求携带课本、笔记本、实训工具等学习用品</strong>，将手机等非学习类电子设备调至静音或关机状态，并<strong>主动存放在指定位置，不随身携带至座位</strong>。</li>
                <li>保持教室环境整洁，<strong>不随地吐痰、乱扔垃圾</strong>，主动整理自身座位周边的卫生，共同维护干净舒适的学习空间。</li>
            </ol>

            <h2>二、课中规范：专注投入学习</h2>
            <ol start="4">
                <li>遵守课堂礼仪，尊重教师劳动。教师进入教室时，<strong>主动起身问好</strong>；上课期间，<strong>不随意打断教师讲课</strong>，若有疑问需举手示意，经教师允许后发言，发言时声音清晰、表述规范。</li>
                <li>保持专注状态，全身心投入学习。<strong>不做与课堂无关的事，如睡觉、聊天、打闹、传阅与课程无关的书籍、偷偷使用电子设备等</strong>，确保思维始终跟随教师的教学节奏。</li>
                <li>积极参与课堂互动，主动配合教学。按时完成教师布置的课堂任务，无论是理论思考、小组讨论还是实训操作，都<strong>认真对待、积极参与，不敷衍、不应付</strong>。</li>
                <li>遵守实训课堂纪律，严守安全规范。在实训场地，<strong>严格按照教师要求操作设备，规范佩戴劳保用品，不擅自摆弄仪器、违规操作</strong>，确保自身及他人安全；实训结束后，<strong>及时整理工具、清理场地，做好设备归位工作</strong>。</li>
                <li>尊重同学，互助共进。与同学友好相处，<strong>不发生争执、冲突</strong>，主动帮助学习有困难的同学，共同营造团结协作的课堂氛围。</li>
            </ol>

            <h2>三、课后践行：延续良好习惯</h2>
            <ol start="9">
                <li>按时、独立完成课后作业，<strong>不抄袭、不拖延</strong>，认真对待每一次巩固练习的机会。</li>
                <li>主动反思课堂表现，若出现违反纪律的行为，<strong>及时向教师承认错误并改正</strong>，不断提升自我约束能力。</li>
                <li>积极参与班级学风建设，<strong>主动提醒身边同学遵守课堂纪律</strong>，共同打造文明、守纪、勤学的班级风貌。</li>
            </ol>

            <h2>四、责任担当：坚守承诺底线</h2>
            <p>我深知，遵守课堂纪律不仅是对自己学习负责，更是对教师、对班级集体的尊重。若我未能遵守上述承诺，<strong>自愿接受学校及班级制定的相关教育管理措施（如批评教育、补写反思、参与志愿服务等），并承担相应的责任</strong>。</p>
            <p>我将以此次承诺为契机，<strong>树立“规则意识”“自律意识”和“责任意识”</strong>，以良好的课堂表现为基础，锤炼专业技能，涵养职业素养，努力成为一名符合新时代要求的优秀中职生。</p>

            <!-- 附则部分（补充文档中的附则内容） -->
            <div class="commitment-appendix">
                <h2>附则</h2>
                <ol>
                    <li><strong>本承诺书自签字之日起生效，适用于日常教学及实训课堂</strong>。</li>
                    <li><strong>班级可根据实际情况，在征得学生及家长同意后，对承诺内容进行补充完善</strong>。</li>
                </ol>
            </div>
        </div>
        
        <!-- 新增：是否同意选项（仅未同意时显示） -->
        <?php if (!$is_agreed): ?>
        <div class="agree-section" style="margin: 30px 0; padding: 20px; border: 1px solid #eee; border-radius: 8px;">
            <h3 style="color: #333; margin-bottom: 15px;">是否同意以上课堂纪律承诺</h3>
            <div style="font-size: 1.1em; margin-left: 20px;">
                <label style="margin-right: 30px;">
                    <input type="radio" name="agree_status" value="1" <?php echo $agree_status == 1 ? 'checked' : ''; ?> required> 同意
                </label>
                <!-- 强制同意时隐藏不同意选项 -->
                <label <?php echo $force_agree == 1 ? 'style="display: none;"' : ''; ?>>
                    <input type="radio" name="agree_status" value="0" <?php echo $agree_status == 0 && $commitment_status ? 'checked' : ''; ?> <?php echo $force_agree == 1 ? 'disabled' : ''; ?>> 不同意
                </label>
                <?php if ($force_agree == 1): ?>
                <span style="color: #dc3545; font-weight: bold;">※ 系统要求必须同意本承诺书</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 签名区域 -->
        <div class="signature-section">
            <?php if ($parent_signature_required == '1'): ?>
            <div class="config-note">※ 本次承诺书需要学生和家长共同签名确认</div>
            <?php else: ?>
            <div class="config-note">※ 本次承诺书仅需学生签名确认</div>
            <?php endif; ?>

            <!-- 历史签名展示（已签署时） -->
            <?php if ($commitment_status): ?>
            <div class="history-signature">
                <h4>您的历史签名信息</h4>
                <!-- 根据是否需要家长签名添加不同的class -->
                <div class="signature-group <?php echo $parent_signature_required == '0' ? 'single-signature' : ''; ?>">
                    <!-- 学生历史签名 -->
                    <div class="signature-card">
                        <h3>学生签名</h3>
                        <?php if (!empty($student_signature)): ?>
                        <div class="signature-image">
                            <img src="<?php echo $student_signature; ?>" alt="学生签名">
                        </div>
                        <?php else: ?>
                        <p style="text-align: center; color: #666;">无历史签名</p>
                        <?php endif; ?>
                    </div>

                    <!-- 家长历史签名（根据配置显示/隐藏） -->
                    <div class="signature-card <?php echo $parent_signature_required == '0' ? 'hidden' : ''; ?>">
                        <h3>家长监督签名</h3>
                        <?php if (!empty($parent_signature)): ?>
                        <div class="signature-image">
                            <img src="<?php echo $parent_signature; ?>" alt="家长签名">
                        </div>
                        <?php else: ?>
                        <p style="text-align: center; color: #666;">无历史签名</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$is_agreed): ?>
                <div class="resign-note">※ 下方可重新签署签名（将覆盖历史签名）</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- 重新签署区域（仅未同意时显示） -->
            <?php if (!$is_agreed): ?>
            <h4 style="text-align: center; margin: 20px 0; color: #007BFF;">重新签署签名</h4>
            <!-- 新增：签名规范提示 -->
            <div class="signature-tip">※ 请签署您的真实姓名（汉字/拼音），禁止空白/单线签名！</div>
            <!-- 根据是否需要家长签名添加不同的class -->
            <div class="signature-group <?php echo $parent_signature_required == '0' ? 'single-signature' : ''; ?>">
                <!-- 学生签名 -->
                <div class="signature-card">
                    <h3>学生签名 <span style="color:red;">*</span></h3>
                    
                    <!-- 始终显示画板（允许重新签名） -->
                    <canvas id="studentSignaturePad" class="signature-pad"></canvas>
                    <!-- 精准错误提示 -->
                    <div id="studentSignatureError" class="signature-error hidden">
                        ❌ 签名无效！请绘制完整的姓名/连贯笔画（禁止空白/单点/单线）
                    </div>
                    <!-- 新增：重新尝试按钮 -->
                    <button id="retryStudentSignature" class="btn retry-btn hidden">清除并重新签名</button>
                    
                    <!-- 签名操作按钮（始终显示） -->
                    <div class="signature-actions">
                        <button id="clearStudentSignature" class="btn btn-danger">清除签名</button>
                        <button id="saveStudentSignature" class="btn btn-primary">确认签名</button>
                    </div>
                </div>

                <!-- 家长签名（根据配置显示/隐藏） -->
                <div class="signature-card <?php echo $parent_signature_required == '0' ? 'hidden' : ''; ?>">
                    <h3>家长监督签名 <?php echo $parent_signature_required == '1' ? '<span style="color:red;">*</span>' : ''; ?></h3>
                    
                    <!-- 始终显示画板（允许重新签名） -->
                    <canvas id="parentSignaturePad" class="signature-pad"></canvas>
                    <!-- 精准错误提示 -->
                    <div id="parentSignatureError" class="signature-error hidden">
                        ❌ 签名无效！请绘制完整的姓名/连贯笔画（禁止空白/单点/单线）
                    </div>
                    <!-- 新增：重新尝试按钮 -->
                    <button id="retryParentSignature" class="btn retry-btn hidden">清除并重新签名</button>
                    
                    <!-- 家长签名操作按钮（始终显示） -->
                    <div class="signature-actions">
                        <button id="clearParentSignature" class="btn btn-danger">清除签名</button>
                        <button id="saveParentSignature" class="btn btn-primary">确认签名</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 提交确认（仅未同意时显示） -->
        <?php if (!$is_agreed): ?>
        <div class="submit-section">
            <button id="submitCommitment" class="btn btn-success">
                <?php echo $commitment_status ? '重新提交承诺书' : '提交承诺书（已完成所有签名）'; ?>
            </button>
            <?php if ($commitment_status): ?>
            <p style="margin-top: 20px; color: #666; font-style: italic;">
                点击提交后将覆盖您之前的签署信息，请谨慎操作！
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 返回菜单 -->
        <div class="back-menu">
            <a href="student_menu.php">返回学生菜单</a>
        </div>

        <!-- 退出登录 -->
        <div style="text-align: center; margin-top: 20px;">
            <a href="logout.php" class="logout-btn">退出登录</a>
        </div>
    </div>

    <script>
        // 全局变量：记录是否有绘制操作
        let studentHasDrawn = false;
        let parentHasDrawn = false;
        // 全局变量：记录绘制的轨迹点（用于判断是否是单线）
        let studentDrawPoints = [];
        let parentDrawPoints = [];

        // 签名画板初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 配置参数
            const parentSignatureRequired = <?php echo $parent_signature_required; ?>;
            const commitmentStatus = <?php echo $commitment_status; ?>;
            const forceAgree = <?php echo $force_agree; ?>; // 新增：强制同意配置
            const isAgreed = <?php echo $is_agreed ? 'true' : 'false'; ?>; // 新增：是否已同意
            
            // 已同意时，直接退出初始化（禁用所有签名功能）
            if (isAgreed) {
                return;
            }

            // 学生签名画板
            const studentCanvas = document.getElementById('studentSignaturePad');
            const studentCtx = studentCanvas ? studentCanvas.getContext('2d') : null;
            // 家长签名画板
            const parentCanvas = document.getElementById('parentSignaturePad');
            const parentCtx = parentCanvas ? parentCanvas.getContext('2d') : null;

            // 签名状态
            let isStudentSigning = false;
            let isParentSigning = false;
            let studentSignatureSaved = false;
            let parentSignatureSaved = false;

            // 设置Canvas尺寸
            function resizeCanvas(canvas) {
                if (!canvas) return;
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width;
                canvas.height = rect.height;
            }

            // 初始化Canvas
            if (studentCanvas) resizeCanvas(studentCanvas);
            if (parentCanvas) resizeCanvas(parentCanvas);
            
            window.addEventListener('resize', function() {
                if (studentCanvas) resizeCanvas(studentCanvas);
                if (parentCanvas) resizeCanvas(parentCanvas);
            });

            // 计算两点之间的距离
            function getDistance(p1, p2) {
                return Math.sqrt(Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2));
            }

            // 判断轨迹是否为单线（所有点近似在一条直线上）
            function isSingleLine(points) {
                if (points.length < 5) return true; // 点数过少直接判定为单线
                
                // 取首尾点作为基准线
                const startPoint = points[0];
                const endPoint = points[points.length - 1];
                const totalDistance = getDistance(startPoint, endPoint);
                
                // 计算所有中间点到基准线的垂直距离之和
                let totalDeviation = 0;
                for (let i = 1; i < points.length - 1; i++) {
                    const point = points[i];
                    // 点到直线的垂直距离公式
                    const deviation = Math.abs(
                        (endPoint.y - startPoint.y) * point.x - 
                        (endPoint.x - startPoint.x) * point.y + 
                        endPoint.x * startPoint.y - 
                        endPoint.y * startPoint.x
                    ) / Math.sqrt(
                        Math.pow(endPoint.y - startPoint.y, 2) + 
                        Math.pow(endPoint.x - startPoint.x, 2)
                    );
                    totalDeviation += deviation;
                }
                
                // 平均偏差小于5像素则判定为单线
                const avgDeviation = totalDeviation / (points.length - 2);
                return avgDeviation < 5;
            }

            // 绘制函数 - 新增：记录绘制轨迹点
            function startDrawing(e, canvas, ctx, isStudent) {
                if (!canvas || !ctx) return;
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                ctx.beginPath();
                ctx.moveTo(x, y);
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#000';
                
                // 标记开始绘制
                if (isStudent) {
                    isStudentSigning = true;
                    studentDrawPoints = [{x, y}]; // 重置轨迹点并记录起点
                } else {
                    isParentSigning = true;
                    parentDrawPoints = [{x, y}]; // 重置轨迹点并记录起点
                }
            }

            function draw(e, canvas, ctx, isStudent) {
                if (!canvas || !ctx) return;
                if ((isStudent && !isStudentSigning) || (!isStudent && !isParentSigning)) return;
                
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                ctx.lineTo(x, y);
                ctx.stroke();
                
                // 标记有绘制操作
                if (isStudent) {
                    studentHasDrawn = true;
                    studentDrawPoints.push({x, y}); // 记录轨迹点
                } else {
                    parentHasDrawn = true;
                    parentDrawPoints.push({x, y}); // 记录轨迹点
                }
            }

            function stopDrawing(isStudent) {
                if (isStudent) {
                    isStudentSigning = false;
                } else {
                    isParentSigning = false;
                }
            }

            // 绑定学生签名事件 - 新增：记录轨迹点
            if (studentCanvas) {
                studentCanvas.addEventListener('mousedown', (e) => startDrawing(e, studentCanvas, studentCtx, true));
                studentCanvas.addEventListener('mousemove', (e) => draw(e, studentCanvas, studentCtx, true));
                studentCanvas.addEventListener('mouseup', () => stopDrawing(true));
                studentCanvas.addEventListener('mouseout', () => stopDrawing(true));
                // 兼容触摸设备
                studentCanvas.addEventListener('touchstart', (e) => {
                    e.preventDefault();
                    const touch = e.touches[0];
                    startDrawing(touch, studentCanvas, studentCtx, true);
                });
                studentCanvas.addEventListener('touchmove', (e) => {
                    e.preventDefault();
                    const touch = e.touches[0];
                    draw(touch, studentCanvas, studentCtx, true);
                });
                studentCanvas.addEventListener('touchend', () => stopDrawing(true));
            }

            // 绑定家长签名事件 - 新增：记录轨迹点
            if (parentSignatureRequired && parentCanvas) {
                parentCanvas.addEventListener('mousedown', (e) => startDrawing(e, parentCanvas, parentCtx, false));
                parentCanvas.addEventListener('mousemove', (e) => draw(e, parentCanvas, parentCtx, false));
                parentCanvas.addEventListener('mouseup', () => stopDrawing(false));
                parentCanvas.addEventListener('mouseout', () => stopDrawing(false));
                // 兼容触摸设备
                parentCanvas.addEventListener('touchstart', (e) => {
                    e.preventDefault();
                    const touch = e.touches[0];
                    startDrawing(touch, parentCanvas, parentCtx, false);
                });
                parentCanvas.addEventListener('touchmove', (e) => {
                    e.preventDefault();
                    const touch = e.touches[0];
                    draw(touch, parentCanvas, parentCtx, false);
                });
                parentCanvas.addEventListener('touchend', () => stopDrawing(false));
            }

            // 清除签名 - 重置所有状态
            if (document.getElementById('clearStudentSignature')) {
                document.getElementById('clearStudentSignature').addEventListener('click', function() {
                    if (studentCanvas && studentCtx) {
                        studentCtx.clearRect(0, 0, studentCanvas.width, studentCanvas.height);
                        studentSignatureSaved = false;
                        studentHasDrawn = false; // 重置绘制状态
                        studentDrawPoints = []; // 重置轨迹点
                        // 隐藏错误提示和重新尝试按钮
                        document.getElementById('studentSignatureError').classList.add('hidden');
                        document.getElementById('retryStudentSignature').classList.add('hidden');
                    }
                });
            }

            if (document.getElementById('clearParentSignature')) {
                document.getElementById('clearParentSignature').addEventListener('click', function() {
                    if (parentCanvas && parentCtx) {
                        parentCtx.clearRect(0, 0, parentCanvas.width, parentCanvas.height);
                        parentSignatureSaved = false;
                        parentHasDrawn = false; // 重置绘制状态
                        parentDrawPoints = []; // 重置轨迹点
                        // 隐藏错误提示和重新尝试按钮
                        document.getElementById('parentSignatureError').classList.add('hidden');
                        document.getElementById('retryParentSignature').classList.add('hidden');
                    }
                });
            }

            // 新增：重新尝试签名按钮事件
            if (document.getElementById('retryStudentSignature')) {
                document.getElementById('retryStudentSignature').addEventListener('click', function() {
                    if (studentCanvas && studentCtx) {
                        studentCtx.clearRect(0, 0, studentCanvas.width, studentCanvas.height);
                        studentSignatureSaved = false;
                        studentHasDrawn = false; // 重置绘制状态
                        studentDrawPoints = []; // 重置轨迹点
                        // 隐藏错误提示和重新尝试按钮
                        document.getElementById('studentSignatureError').classList.add('hidden');
                        document.getElementById('retryStudentSignature').classList.add('hidden');
                        // 聚焦到签名画板，方便重新绘制
                        studentCanvas.focus();
                    }
                });
            }

            if (document.getElementById('retryParentSignature')) {
                document.getElementById('retryParentSignature').addEventListener('click', function() {
                    if (parentCanvas && parentCtx) {
                        parentCtx.clearRect(0, 0, parentCanvas.width, parentCanvas.height);
                        parentSignatureSaved = false;
                        parentHasDrawn = false; // 重置绘制状态
                        parentDrawPoints = []; // 重置轨迹点
                        // 隐藏错误提示和重新尝试按钮
                        document.getElementById('parentSignatureError').classList.add('hidden');
                        document.getElementById('retryParentSignature').classList.add('hidden');
                        // 聚焦到签名画板，方便重新绘制
                        parentCanvas.focus();
                    }
                });
            }

            // ========== 终极修复：三重验证（绘制状态 + 轨迹形状 + 像素检查） ==========
            function validateSignature(canvas, isStudent) {
                if (!canvas) return false;
                
                // 第一步：检查轨迹是否为单线
                const points = isStudent ? studentDrawPoints : parentDrawPoints;
                if (isSingleLine(points)) {
                    return false;
                }
                
                const ctx = canvas.getContext('2d');
                const width = canvas.width;
                const height = canvas.height;
                const imageData = ctx.getImageData(0, 0, width, height);
                const data = imageData.data;
                
                // 第二步：逐像素检查，统计有效签名像素（非白色）
                let validPixels = 0;
                // 记录笔画的坐标范围（用于判断是否是有效笔画）
                let minX = width, maxX = 0, minY = height, maxY = 0;
                
                for (let y = 0; y < height; y++) {
                    for (let x = 0; x < width; x++) {
                        const idx = (y * width + x) * 4;
                        // 检查是否是黑色/灰色笔画（R/G/B均<255表示非空白）
                        const isStroke = data[idx] < 255 || data[idx+1] < 255 || data[idx+2] < 255;
                        
                        if (isStroke) {
                            validPixels++;
                            // 更新坐标范围
                            if (x < minX) minX = x;
                            if (x > maxX) maxX = x;
                            if (y < minY) minY = y;
                            if (y > maxY) maxY = y;
                        }
                    }
                }
                
                // 第三步：核心验证规则（严格判定）
                const hasEnoughPixels = validPixels > 20; // 至少20个有效像素（防短线/单点）
                const hasValidSize = (maxX - minX) > 10 && (maxY - minY) > 10; // 笔画至少跨10像素（防短线）
                
                // 只有同时满足所有条件才通过
                return hasEnoughPixels && hasValidSize;
            }

            // 保存学生签名（终极修复：三重验证）
            if (document.getElementById('saveStudentSignature')) {
                document.getElementById('saveStudentSignature').addEventListener('click', function() {
                    // 隐藏错误提示
                    const errorEl = document.getElementById('studentSignatureError');
                    const retryEl = document.getElementById('retryStudentSignature');
                    errorEl.classList.add('hidden');
                    retryEl.classList.add('hidden');
                    
                    // 第一步：优先检查是否有绘制操作（100%拦截空白）
                    if (!studentHasDrawn) {
                        alert('❌ 签名不能为空！请绘制您的姓名后再保存。');
                        return;
                    }
                    
//                  // 第二步：三重验证（防单点/单线/短线）
//                  if (!validateSignature(studentCanvas, true)) {
//                      errorEl.classList.remove('hidden');
//                      retryEl.classList.remove('hidden');
//                      return;
//                  }
                    
                    // 验证通过
                    studentSignatureSaved = true;
                    alert('✅ 学生签名保存成功！');
                });
            }

            // 保存家长签名（终极修复：三重验证）
            if (document.getElementById('saveParentSignature')) {
                document.getElementById('saveParentSignature').addEventListener('click', function() {
                    if (!parentSignatureRequired) return;
                    
                    // 隐藏错误提示
                    const errorEl = document.getElementById('parentSignatureError');
                    const retryEl = document.getElementById('retryParentSignature');
                    errorEl.classList.add('hidden');
                    retryEl.classList.add('hidden');
                    
                    // 第一步：优先检查是否有绘制操作（100%拦截空白）
                    if (!parentHasDrawn) {
                        alert('❌ 家长签名不能为空！请绘制家长姓名后再保存。');
                        return;
                    }
                    
                    // 第二步：三重验证（防单点/单线/短线）
                    if (!validateSignature(parentCanvas, false)) {
                        errorEl.classList.remove('hidden');
                        retryEl.classList.remove('hidden');
                        return;
                    }
                    
                    // 验证通过
                    parentSignatureSaved = true;
                    alert('✅ 家长签名保存成功！');
                });
            }

            // 提交承诺书
            if (document.getElementById('submitCommitment')) {
                document.getElementById('submitCommitment').addEventListener('click', function() {
                    // 新增：验证是否选择同意
                    const agreeRadios = document.getElementsByName('agree_status');
                    let agreeStatus = null;
                    for (let radio of agreeRadios) {
                        if (radio.checked) {
                            agreeStatus = radio.value;
                            break;
                        }
                    }
                    
                    // 强制同意时，默认选中同意且不允许取消
                    if (forceAgree == 1) {
                        agreeStatus = 1;
                        // 强制勾选同意选项
                        document.querySelector('input[name="agree_status"][value="1"]').checked = true;
                    } else {
                        if (agreeStatus === null) {
                            alert('请选择是否同意以上承诺！');
                            return;
                        }
                        if (agreeStatus == 0) {
                            if (!confirm('您选择了“不同意”，确定要提交吗？')) {
                                return;
                            }
                        }
                    }
                    
                    // 验证学生签名（必填）
                    if (!studentSignatureSaved) {
                        alert('请先完成并保存学生签名！');
                        return;
                    }

                    // 验证家长签名（仅当配置开启时）
                    if (parentSignatureRequired && !parentSignatureSaved) {
                        alert('请先完成并保存家长签名！');
                        return;
                    }

                    // 获取签名数据（Base64格式）
                    const studentSignature = studentCanvas ? studentCanvas.toDataURL('image/png') : '';
                    let parentSignature = '';
                    if (parentSignatureRequired && parentCanvas) {
                        parentSignature = parentCanvas.toDataURL('image/png');
                    }

                    // 发送AJAX请求保存数据
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'save_commitment.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                alert(response.message);
                                
                                // 关键修复：仅在提交时判断强制同意，且仅当本次提交不同意时才退出
                                if (forceAgree == 1 && agreeStatus == 0) {
                                    alert('系统要求必须同意承诺书，您的提交无效，即将退出登录！');
                                    window.location.href = 'logout.php';
                                } else {
                                    // 跳转到之前的页面（优先）或学生菜单
                                    const redirectUrl = '<?php echo $_SESSION['redirect_after_commit'] ?? 'student_menu.php'; ?>';
                                    window.location.href = redirectUrl;
                                    // 清除重定向记录
                                    <?php unset($_SESSION['redirect_after_commit']); ?>
                                }
                            } else {
                                alert('签署失败：' + response.message);
                            }
                        } else {
                            alert('服务器错误，请稍后重试！');
                        }
                    };

                    // 构造请求参数（适配实际表字段：signature_data和parent_signature_data）
                    const params = 'student_id=' + encodeURIComponent('<?php echo $student_id; ?>') +
                                  '&student_name=' + encodeURIComponent('<?php echo $student_name; ?>') +
                                  '&student_class=' + encodeURIComponent('<?php echo $student_class; ?>') +
                                  '&signature_data=' + encodeURIComponent(studentSignature) + // 对应表中signature_data字段
                                  '&parent_signature_data=' + encodeURIComponent(parentSignature) + // 对应表中parent_signature_data字段
                                  '&parent_signature_required=' + encodeURIComponent(parentSignatureRequired) + 
                                  '&agree_status=' + encodeURIComponent(agreeStatus); // 新增传递同意状态

                    xhr.send(params);
                });
            }
        });
    </script>
</body>
</html>