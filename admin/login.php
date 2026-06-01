<?php
/**
 * 后台管理 - 登录页面
 */

// 判断是否已安装
if (!is_file(__DIR__ . '/../install.lock')) {
    header("location:../install.php");
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';

session_start();

// 生成CSRF令牌
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 已登录则跳转
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

$error = '';

// 获取站点标题
$siteTitle = '';
try {
    $db = Database::getInstance();
    $config = $db->fetch("SELECT value FROM " . $db->getTable('config') . " WHERE name = 'site_title'");
    $siteTitle = $config['value'] ?? '';
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = '安全验证失败，请刷新页面重试';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $loginSuccess = false;
        
        // 验证数据库密码
        try {
            $db = Database::getInstance();
            $admin = $db->fetch("SELECT * FROM " . $db->getTable('admins') . " WHERE username = ? AND status = 1", [$username]);
            
            if ($admin && password_verify($password, $admin['password'])) {
                $loginSuccess = true;
            }
        } catch (Exception $e) {
            $error = '数据库连接失败';
        }
        
        if ($loginSuccess) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_id'] = $admin['id'] ?? 1;
            
            // 更新登录信息
            try {
                $db = Database::getInstance();
                $db->update('admins', [
                    'last_login_time' => date('Y-m-d H:i:s'),
                    'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                ], "username = ?", [$username]);
            } catch (Exception $e) {}
            
            header('Location: index.php');
            exit;
        } else {
            $error = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - <?php echo htmlspecialchars($siteTitle); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            z-index: 1;
            filter: brightness(0.6) blur(2px);
        }
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.2);
            z-index: 2;
        }
        
        .login-wrapper {
            position: relative;
            z-index: 100;
            width: 100%;
            max-width: 420px;
        }
        
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            padding: 50px 40px;
            text-align: center;
        }
        
        .login-header {
            margin-bottom: 40px;
        }
        
        .login-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .login-header p {
            color: #9ca3af;
            font-size: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 18px;
            pointer-events: none;
            z-index: 1;
        }
        
        .form-group input {
            width: 100%;
            padding: 16px 16px 16px 50px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f9fafb;
        }
        
        .form-group input::placeholder {
            color: #d1d5db;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-options {
            margin: 24px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 14px;
            color: #6b7280;
            user-select: none;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }
        
        .forget-password {
            font-size: 14px;
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .forget-password:hover {
            color: #764ba2;
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #00d4ff 0%, #0099ff 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(0, 153, 255, 0.4);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 153, 255, 0.5);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .form-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #d1d5db;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 40px 30px;
            }
            
            .login-header h1 {
                font-size: 28px;
            }
            
            .form-options {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <script>
        // 直接使用 Bing 壁纸 API 作为背景
        const bgUrl = 'https://bing.img.run/rand.php';
        document.body.style.backgroundImage = `url('${bgUrl}')`;
        document.body.style.backgroundSize = 'cover';
        document.body.style.backgroundPosition = 'center';
        document.body.style.backgroundAttachment = 'fixed';
        document.body.style.backgroundRepeat = 'no-repeat';
        
        // 更新 ::before 伪元素的背景
        const style = document.createElement('style');
        style.textContent = `body::before { background-image: url('${bgUrl}'); }`;
        document.head.appendChild(style);
    </script>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <h1><?php echo htmlspecialchars($siteTitle); ?></h1>
                <p>账户登录</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span>⚠️</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <label for="username">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </label>
                    <input type="text" id="username" name="username" placeholder="输入用户名或邮箱" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </label>
                    <input type="password" id="password" name="password" placeholder="账户密码" required>
                </div>
                
                <button type="submit" class="btn-login">登 录</button>
            </form>
            
            <div class="form-footer">
                © 2026 Ning.Si 软件源管理系统
            </div>
        </div>
    </div>
</body>
</html>
<?php
// 文件结束
?>
