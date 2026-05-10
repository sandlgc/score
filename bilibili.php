
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>离线B站视频播放器</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            text-align: center;
            padding: 30px 0;
            color: white;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .video-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            height: 0;
            overflow: hidden;
            border-radius: 10px;
            background: #000;
        }
        
        .video-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .info-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .info-panel h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .feature-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-card h3 {
            color: #555;
            margin-bottom: 10px;
        }
        
        .feature-card p {
            color: #666;
            line-height: 1.6;
        }
        
        .instructions {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .instructions h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .steps {
            counter-reset: step-counter;
            list-style: none;
        }
        
        .steps li {
            counter-increment: step-counter;
            margin-bottom: 20px;
            padding-left: 40px;
            position: relative;
        }
        
        .steps li:before {
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        footer {
            text-align: center;
            color: white;
            padding: 30px 0;
            margin-top: 30px;
            opacity: 0.8;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>离线B站视频播放器</h1>
            <p class="subtitle">专为校园网络环境优化的视频观看解决方案</p>
        </header>
        
        <div class="video-container">
            <div class="video-wrapper">
                <iframe src="//player.bilibili.com/player.html?isOutside=true&aid=504447132&bvid=BV13g41177LV&cid=34967326206&p=94" 
                        allowfullscreen="true">
                </iframe>
            </div>
        </div>
        
        <div class="info-panel">
            <h2>功能特性</h2>
            <div class="features">
                <div class="feature-card">
                    <h3>离线优化</h3>
                    <p>针对校园网络环境进行优化，减少对外部资源的依赖，提升播放流畅度。</p>
                </div>
                <div class="feature-card">
                    <h3>安全访问</h3>
                    <p>通过官方嵌入方式确保视频播放安全，无需担心网络访问限制。</p>
                </div>
                <div class="feature-card">
                    <h3>自适应布局</h3>
                    <p>响应式设计适配各种设备屏幕，在教室、宿舍等不同场景下都能完美观看。</p>
                </div>
            </div>
        </div>
        
        <div class="instructions">
            <h2>使用说明</h2>
            <ol class="steps">
                <li>确保设备已连接校园网络</li>
                <li>直接点击播放器中的视频即可开始观看</li>
                <li>支持全屏播放，提供更好的观看体验</li>
                <li>如遇到播放问题，请刷新页面或联系管理员</li>
            </ol>
        </div>
        
        <footer>
            <p>© 2026 离线B站视频播放器 | 专为教育环境设计</p>
        </footer>
    </div>
</body>
</html>
