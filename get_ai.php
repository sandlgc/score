<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>智能体对话</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <style>
        /* 自定义思考提示动画 */
        .thinking {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 0.5;
            }
            50% {
                opacity: 1;
            }
            100% {
                opacity: 0.5;
            }
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-8">
        <h1 class="text-3xl font-bold mb-4 text-center text-gray-800">与智能体对话</h1>
        <form id="questionForm" action="" method="post" class="mb-4">
            <textarea name="question" id="question" rows="4"
                class="w-full p-3 border border-gray-300 rounded-md mb-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="请输入你的问题"></textarea>
            <button type="submit"
                class="bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:ring-2 focus:ring-blue-300 focus:outline-none">发送</button>
        </form>
        <div id="responseContainer" class="relative">
            <div id="thinking" class="hidden text-center text-gray-600 thinking">
                <i class="fa-solid fa-spinner fa-spin"></i> 智能体正在思考...
            </div>
            <div id="response" class="hidden bg-white border border-gray-300 p-4 rounded-md shadow-md">
                <p class="text-gray-700">智能体回复：</p>
                <p id="answer" class="text-gray-800"></p>
            </div>
            <div id="error" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-4">
                <p id="errorMessage"></p>
            </div>
        </div>
        <div id="history" class="mt-4">
            <h2 class="text-xl font-bold mb-2">历史对话</h2>
            <div id="historyList"></div>
        </div>
    </div>
    <script>
        document.getElementById('questionForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const question = document.getElementById('question').value;
            const thinkingDiv = document.getElementById('thinking');
            const responseDiv = document.getElementById('response');
            const answerDiv = document.getElementById('answer');
            const errorDiv = document.getElementById('error');
            const errorMessageDiv = document.getElementById('errorMessage');
            const historyList = document.getElementById('historyList');

            // 清空之前的回答和错误信息
            answerDiv.textContent = '';
            errorMessageDiv.textContent = '';
            // 显示思考提示
            thinkingDiv.classList.remove('hidden');
            responseDiv.classList.add('hidden');
            errorDiv.classList.add('hidden');

            const formData = new FormData();
            formData.append('question', question);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
              .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
              .then(data => {
                    console.log('API 响应数据:', data); // 输出响应数据用于调试
                    // 隐藏思考提示
                    thinkingDiv.classList.add('hidden');
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    const errorElement = doc.querySelector('#errorMessage');
                    let answerText = '';

                    if (errorElement.textContent) {
                        errorDiv.classList.remove('hidden');
                        errorMessageDiv.textContent = errorElement.textContent;
                    } else {
                        // 使用正则表达式提取动态设置的回复内容
                        const regex = /answerDiv\.textContent = "([^"]+)"/;
                        const match = data.match(regex);
                        if (match) {
                            answerText = match[1];
                        }

                        if (answerText) {
                            responseDiv.classList.remove('hidden');
                            let index = 0;
                            const typingInterval = setInterval(() => {
                                if (index < answerText.length) {
                                    answerDiv.textContent += answerText[index];
                                    index++;
                                } else {
                                    clearInterval(typingInterval);
                                    // 添加到历史对话
                                    const questionItem = document.createElement('p');
                                    questionItem.classList.add('text-gray-600', 'mb-1');
                                    questionItem.textContent = `你: ${question}`;
                                    const answerItem = document.createElement('p');
                                    answerItem.classList.add('text-gray-800', 'mb-3');
                                    answerItem.textContent = `智能体: ${answerText}`;
                                    historyList.appendChild(questionItem);
                                    historyList.appendChild(answerItem);
                                }
                            }, 50);
                        } else {
                            errorDiv.classList.remove('hidden');
                            errorMessageDiv.textContent = '未获取到有效的回复内容';
                        }
                    }
                })
              .catch(error => {
                    console.error('请求出错:', error); // 输出错误信息用于调试
                    // 隐藏思考提示
                    thinkingDiv.classList.add('hidden');
                    errorDiv.classList.remove('hidden');
                    errorMessageDiv.textContent = `请求出错: ${error.message}`;
                });
        });
    </script>
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // 引入数据库配置文件
        require_once 'config.php';

        // 创建数据库连接
        $conn = new mysqli($servername, $username, $password, $dbname);

        // 检查连接是否成功
        if ($conn->connect_error) {
            die("数据库连接失败: " . $conn->connect_error);
        }

        // 查询 API Token
        $sql = "SELECT api_token FROM token WHERE id = 1"; // 使用新的 token 表
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $api_token = $row["api_token"];
        } else {
            echo '<script>';
            echo 'const errorMessageDiv = document.getElementById("errorMessage");';
            echo 'errorMessageDiv.textContent = "未从数据库中获取到 API Token，请检查数据库记录";';
            echo '</script>';
            $conn->close();
            return;
        }

        // 关闭数据库连接
        $conn->close();

        // 配置参数
        $assistant_id = "BFexzUrimarr"; // 替换为你的智能体ID
        $api_url = "https://open.hunyuan.tencent.com/openapi/v1/agent/chat/completions";

        // 构建请求头
        $headers = [
            'X-Source: openapi',
            'Content-Type: application/json',
            'Authorization: Bearer '.$api_token // 添加 Bearer 前缀
        ];

        // 构建请求体
        $data = [
            "assistant_id" => $assistant_id,
            "user_id" => "user_123", // 用户唯一标识（业务侧自定义）
            "stream" => false, // 是否启用流式响应
            "messages" => [
                [
                    "role" => "user",
                    "content" => [
                        [
                            "type" => "text",
                            "text" => $_POST['question'] // 获取用户输入
                        ]
                    ]
                ]
            ]
        ];

        // 发送POST请求（cURL方式）
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // 错误处理
        if ($http_code != 200) {
            $error = json_decode($response, true);
            $errorMessage = isset($error['error']['message']) ? $error['error']['message'] : '未知错误';
            echo '<script>';
            echo 'console.error("API 调用失败:", "'.$errorMessage.'", "HTTP状态码:", '.$http_code.', "响应内容:", "'.htmlspecialchars($response).'");';
            echo 'const errorMessageDiv = document.getElementById("errorMessage");';
            echo 'errorMessageDiv.textContent = "API调用失败：'.$errorMessage.'<br>HTTP状态码: '.$http_code.'<br>响应内容: '.htmlspecialchars($response).'";';
            echo '</script>';
        } else {
            // 解析响应
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                $answer = addslashes($result['choices'][0]['message']['content']);
                echo '<script>';
                echo 'const answerDiv = document.getElementById("answer");';
                echo 'answerDiv.textContent = "'.$answer.'";';
                echo '</script>';
            } else {
                echo '<script>';
                echo 'console.error("未获取到有效的回复内容:", "'.htmlspecialchars($response).'");';
                echo 'const errorMessageDiv = document.getElementById("errorMessage");';
                echo 'errorMessageDiv.textContent = "未获取到有效的回复内容: '.htmlspecialchars($response).'";';
                echo '</script>';
            }
        }

        curl_close($ch);
    }
    ?>
</body>

</html>    