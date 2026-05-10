<?php
// 只在POST接口请求时输出JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // ========== 按你给的官方参数填写 ==========
    $appkey = "uSfM8OkLYSpHCwl1mHgInziURjxTxgVu";
    $appid  = "2053005769488361280";
    $apiUrl = "https://yuanqi.tencent.com/openapi/v1/agent/chat/completions";
    $userId = "user001"; // 随便填字符串即可

    $question = trim($_POST['question'] ?? '');
    if (empty($question)) {
        echo json_encode(["code" => 400, "msg" => "请输入问题"]);
        exit;
    }

    // 官方标准请求头
    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer {$appkey}"
    ];

    // 官方标准请求体
    $postData = [
        "assistant_id" => $appid,
        "user_id"      => $userId,
        "stream"       => false,
        "messages"     => [
            [
                "role"    => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => $question
                    ]
                ]
            ]
        ]
    ];

    // CURL请求
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($postData, JSON_UNESCAPED_UNICODE),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 30
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if (!empty($err)) {
        echo json_encode(["code" => 500, "msg" => "请求出错：{$err}"]);
        exit;
    }

    $resArr = json_decode($resp, true);

    // 正常返回内容
    if (isset($resArr['choices'][0]['message']['content'])) {
        echo json_encode([
            "code" => 200,
            "data" => $resArr['choices'][0]['message']['content']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            "code" => 500,
            "msg"  => "接口返回异常",
            "raw"  => $resArr
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>智能对话助手</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f0f4ff, #e2eafd);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 720px;
            margin: 0 auto;
        }

        /* 返回菜单按钮 */
        .back-menu {
            display: block;
            width: 100%;
            padding: 12px;
            background: #10b981;
            color: #fff;
            text-align: center;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 20px;
            transition: 0.2s;
        }
        .back-menu:hover {
            background: #059669;
        }

        .title {
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            color: #1d4ed8;
            margin: 10px 0 30px;
        }

        .chat-box {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        textarea {
            width: 100%;
            min-height: 120px;
            border: 1px solid #e0e6f7;
            border-radius: 16px;
            padding: 16px;
            font-size: 15px;
            resize: none;
            outline: none;
            transition: 0.2s;
            margin-bottom: 16px;
        }

        textarea:focus {
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(29,78,216,0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #1d4ed8;
            color: #fff;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-submit:hover {
            background: #1e40af;
        }

        .loading {
            text-align: center;
            padding: 16px;
            color: #4b5563;
            display: none;
        }

        .answer {
            background: #e0e7ff;
            border-left: 4px solid #1d4ed8;
            padding: 18px;
            border-radius: 16px;
            line-height: 1.6;
            font-size: 15px;
            color: #1e3a8a;
            display: none;
            margin: 12px 0;
            white-space: pre-wrap;
        }

        .error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #dc2626;
            padding: 18px;
            border-radius: 16px;
            display: none;
            margin: 12px 0;
        }

        .history {
            margin-top: 30px;
        }

        .history-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 12px;
        }

        .his-item {
            background: #fff;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .his-item strong {
            color: #1d4ed8;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- 🔙 返回菜单按钮 -->
        <a href="http://192.168.103.108/score2026/student_menu.php" class="back-menu">🔙 返回菜单</a>

        <h2 class="title">💬 Python学习精灵</h2>

        <div class="chat-box">
            <textarea id="q" placeholder="请输入你想询问的问题..."></textarea>
            <button class="btn-submit" onclick="send()">发送问题</button>
        </div>

        <div class="loading" id="loading">
            ⏳ 正在思考中，请稍候...
        </div>

        <div class="answer" id="answer"></div>
        <div class="error" id="error"></div>

        <div class="history">
            <div class="history-title">📜 对话历史</div>
            <div id="history"></div>
        </div>
    </div>

<script>
async function send() {
    const q = document.getElementById('q').value.trim();
    if (!q) return alert('请输入内容');

    const loading = document.getElementById('loading');
    const answer = document.getElementById('answer');
    const error = document.getElementById('error');

    loading.style.display = 'block';
    answer.style.display = 'none';
    error.style.display = 'none';

    const res = await fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'question=' + encodeURIComponent(q)
    });

    const text = await res.text();
    loading.style.display = 'none';

    try {
        const data = JSON.parse(text);
        if (data.code === 200) {
            answer.innerText = data.data;
            answer.style.display = 'block';
            addHistory(q, data.data);
        } else {
            error.innerText = data.msg + "\n" + JSON.stringify(data.raw||'');
            error.style.display = 'block';
        }
    } catch(e) {
        error.innerText = '解析失败：' + text;
        error.style.display = 'block';
    }
}

function addHistory(q, a) {
    const h = document.getElementById('history');
    const div = document.createElement('div');
    div.className = 'his-item';
    div.innerHTML = `<strong>你：</strong>${q}<br><br><strong>AI：</strong>${a}`;
    h.prepend(div);
}
</script>
</body>
</html>