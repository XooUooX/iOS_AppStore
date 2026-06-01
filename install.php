<?php
/**
 * Ning.Si软件源管理系统 - 安装向导
 */

error_reporting(0);
session_start();
header('Content-Type: text/html; charset=UTF-8');

$do = isset($_GET['do']) ? intval($_GET['do']) : 0;

// 检查是否已安装
if (file_exists(__DIR__ . '/install.lock') && $do != 5) {
    exit('<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统已安装 - Ning.Si软件源管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --el-color-primary: #409eff;
            --el-color-success: #67c23a;
            --el-color-warning: #e6a23c;
            --el-color-danger: #f56c6c;
            --el-color-info: #909399;
            --el-border-color: #dcdfe6;
            --el-bg-color: #f5f7fa;
            --el-text-color: #606266;
            --el-text-color-primary: #303133;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Helvetica Neue", Helvetica, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", Arial, sans-serif;
            background: #f5f7fa;
            color: var(--el-text-color);
            line-height: 1.5;
            font-size: 14px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .el-card {
            background: #fff;
            border-radius: 4px;
            border: 1px solid var(--el-border-color);
            box-shadow: 0 2px 12px 0 rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        .el-card__body { padding: 40px; }
        .el-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 20px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid var(--el-border-color);
            background: #fff;
            color: var(--el-text-color);
            transition: all 0.2s;
            text-decoration: none;
            gap: 6px;
        }
        .el-button--primary {
            background: var(--el-color-primary);
            border-color: var(--el-color-primary);
            color: #fff;
        }
        .el-button--primary:hover {
            background: #66b1ff;
            border-color: #66b1ff;
        }
        .el-button.is-round { border-radius: 20px; padding: 12px 23px; }
        h2 { color: var(--el-text-color-primary); margin-bottom: 15px; font-size: 20px; }
        p { color: var(--el-text-color); line-height: 1.6; margin-bottom: 10px; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; color: var(--el-color-danger); font-family: monospace; }
    </style>
</head>
<body>
    <div class="el-card">
        <div class="el-card__body">
            <i class="fa fa-ban" style="font-size: 48px; color: var(--el-color-danger); margin-bottom: 20px;"></i>
            <h2>系统已安装</h2>
            <p>检测到 <code>install.lock</code> 文件，系统已经安装过了。</p>
            <p>如需重新安装，请先删除该文件。</p>
            <a href="admin/login.php" class="el-button el-button--primary is-round" style="margin-top: 20px;">
                <i class="fa fa-sign-in-alt"></i> 进入后台
            </a>
        </div>
    </div>
</body>
</html>');
}

// 辅助函数
function checkFunc($func) {
    return function_exists($func) ? '<span class="success"><i class="fa fa-check-circle"></i> 支持</span>' : '<span class="error"><i class="fa fa-times-circle"></i> 不支持</span>';
}

function checkClass($class) {
    return class_exists($class) ? '<span class="success"><i class="fa fa-check-circle"></i> 支持</span>' : '<span class="error"><i class="fa fa-times-circle"></i> 不支持</span>';
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>系统安装向导 - Ning.Si软件源管理系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        success: '#10b981',
                        danger: '#ef4444',
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --el-color-primary: #409eff;
            --el-color-success: #67c23a;
            --el-color-warning: #e6a23c;
            --el-color-danger: #f56c6c;
            --el-color-info: #909399;
            --el-border-color: #dcdfe6;
            --el-bg-color: #f5f7fa;
            --el-text-color: #606266;
            --el-text-color-primary: #303133;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Helvetica Neue", Helvetica, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", Arial, sans-serif;
            background: #f5f7fa;
            color: var(--el-text-color);
            line-height: 1.5;
            font-size: 14px;
        }
        .el-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        
        /* 进度条 */
        .progress-bar { 
            background: #e4e7ed; 
            height: 6px; 
            border-radius: 3px; 
            margin-bottom: 30px; 
            overflow: hidden; 
        }
        .progress-fill { 
            background: var(--el-color-primary); 
            height: 100%; 
            border-radius: 3px; 
            transition: width 0.3s; 
        }
        
        /* 卡片 */
        .el-card {
            background: #fff;
            border-radius: 4px;
            border: 1px solid var(--el-border-color);
            box-shadow: 0 2px 12px 0 rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .el-card__header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--el-border-color);
            background: #fff;
        }
        .el-card__title {
            font-size: 16px;
            font-weight: 500;
            color: var(--el-text-color-primary);
        }
        .el-card__body { padding: 20px; }
        
        /* 按钮 */
        .el-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 20px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid var(--el-border-color);
            background: #fff;
            color: var(--el-text-color);
            transition: all 0.2s;
            text-decoration: none;
            gap: 6px;
        }
        .el-button:hover {
            color: var(--el-color-primary);
            border-color: #c6e2ff;
            background: #ecf5ff;
        }
        .el-button--primary {
            background: var(--el-color-primary);
            border-color: var(--el-color-primary);
            color: #fff;
        }
        .el-button--primary:hover {
            background: #66b1ff;
            border-color: #66b1ff;
            color: #fff;
        }
        .el-button--success {
            background: var(--el-color-success);
            border-color: var(--el-color-success);
            color: #fff;
        }
        .el-button--success:hover {
            background: #85ce61;
            border-color: #85ce61;
            color: #fff;
        }
        .el-button.is-round { border-radius: 20px; padding: 12px 23px; }
        
        /* 输入框 */
        .el-input {
            position: relative;
            display: inline-flex;
            width: 100%;
        }
        .el-input__inner {
            width: 100%;
            height: 40px;
            padding: 0 15px;
            border: 1px solid var(--el-border-color);
            border-radius: 4px;
            font-size: 14px;
            color: var(--el-text-color);
            transition: border-color 0.2s;
            background: #fff;
        }
        .el-input__inner:focus {
            outline: none;
            border-color: var(--el-color-primary);
        }
        .el-input__inner::placeholder { color: #a8abb2; }
        
        /* 表单 */
        .el-form-item {
            margin-bottom: 22px;
        }
        .el-form-item__label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--el-text-color);
        }
        
        /* 提示 */
        .el-alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .el-alert--success {
            background: #f0f9eb;
            border: 1px solid #e1f3d8;
            color: #67c23a;
        }
        .el-alert--error {
            background: #fef0f0;
            border: 1px solid #fde2e2;
            color: #f56c6c;
        }
        .el-alert--warning {
            background: #fdf6ec;
            border: 1px solid #faecd8;
            color: #e6a23c;
        }
        .el-alert__icon { font-size: 16px; }
        .el-alert__content { flex: 1; font-size: 14px; }
        
        /* 页脚 */
        .el-footer {
            text-align: center;
            padding: 40px 20px;
            color: var(--el-color-info);
            font-size: 13px;
        }
        
        /* 协议 */
        .agreement { 
            background: #f5f7fa; 
            padding: 20px; 
            border-radius: 4px; 
            line-height: 1.8; 
            color: #606266; 
            margin-bottom: 25px; 
            max-height: 300px; 
            overflow-y: auto; 
            border: 1px solid var(--el-border-color);
        }
        
        /* 表格 */
        .el-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .el-table th, .el-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--el-border-color);
        }
        .el-table th {
            background: #f5f7fa;
            font-weight: 600;
            color: var(--el-text-color-primary);
        }
        .success { color: #67c23a; }
        .error { color: #f56c6c; }
        
        /* 步骤按钮 */
        .step-buttons { display: flex; justify-content: space-between; margin-top: 25px; }
        .step-buttons-center { display: flex; justify-content: center; margin-top: 25px; }
        
        /* SQL日志 */
        .sql-log { 
            background: #f5f7fa; 
            padding: 15px; 
            border-radius: 4px; 
            font-family: monospace; 
            font-size: 12px; 
            max-height: 200px; 
            overflow-y: auto; 
            margin-top: 15px; 
            border: 1px solid var(--el-border-color);
        }
        code { 
            background: #f0f0f0; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-family: monospace; 
            color: var(--el-color-danger);
        }
    </style>
</head>
<body>
    <div class="el-container">
        <div style="text-align: center; padding: 30px 0;">
            <div style="font-size: 24px; font-weight: 600; color: var(--el-text-color-primary); margin-bottom: 10px;">
                <i class="fa fa-mobile-alt" style="color: var(--el-color-primary); margin-right: 10px;"></i>
                Ning.Si软件源管理系统
            </div>
            <div style="color: var(--el-color-info); font-size: 14px;">软件源安装向导</div>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo ($do + 1) * 20; ?>%"></div>
        </div>
        <div class="el-card">
            <div class="el-card__body">

<?php
if ($do == 0) {
    $_SESSION['install_check'] = 1;
?>
    <div class="el-card__header" style="margin: -20px -20px 20px -20px;">
        <span class="el-card__title"><i class="fa fa-file-contract" style="margin-right: 8px;"></i>用户使用协议</span>
    </div>
    <div class="agreement">
        <p><strong>安装前请仔细阅读以下协议：</strong></p>
        <p>1. 本程序仅供学习交流使用。</p>
        <p>2. 请勿使用本程序上传或分发任何违反国家法律法规的内容。</p>
        <p>3. 使用本程序产生的一切法律后果由使用者自行承担。</p>
        <p>4. 对于因使用本软件而导致的任何直接或间接损失，开发者不承担责任。</p>
        <p>5. 请确保您已获得适当的授权来使用本系统。</p>
    </div>
    <div class="step-buttons-center">
        <a href="?do=1" class="el-button el-button--primary is-round">
            <i class="fa fa-check"></i> 同意协议并开始安装
        </a>
    </div>

<?php
} elseif ($do == 1) {
?>
    <div class="el-card__header" style="margin: -20px -20px 20px -20px;">
        <span class="el-card__title"><i class="fa fa-search" style="margin-right: 8px;"></i>环境检测</span>
    </div>
    <table class="el-table">
        <tr><th>检测项目</th><th>要求</th><th>当前状态</th></tr>
        <tr><td>PHP 版本</td><td>7.2+</td><td><?php echo version_compare(PHP_VERSION, '7.2.0', '>=') ? '<span class="success"><i class="fa fa-check-circle"></i> ' . PHP_VERSION . '</span>' : '<span class="error"><i class="fa fa-times-circle"></i> ' . PHP_VERSION . '</span>'; ?></td></tr>
        <tr><td>PDO 扩展</td><td>必须</td><td><?php echo checkClass('PDO'); ?></td></tr>
        <tr><td>PDO_MySQL</td><td>必须</td><td><?php echo extension_loaded('pdo_mysql') ? '<span class="success"><i class="fa fa-check-circle"></i> 支持</span>' : '<span class="error"><i class="fa fa-times-circle"></i> 不支持</span>'; ?></td></tr>
        <tr><td>curl_exec()</td><td>推荐</td><td><?php echo checkFunc('curl_exec'); ?></td></tr>
        <tr><td>file_get_contents()</td><td>必须</td><td><?php echo checkFunc('file_get_contents'); ?></td></tr>
        <tr><td>Session 支持</td><td>必须</td><td><?php echo isset($_SESSION['install_check']) ? '<span class="success"><i class="fa fa-check-circle"></i> 支持</span>' : '<span class="error"><i class="fa fa-times-circle"></i> 不支持</span>'; ?></td></tr>
        <tr><td>文件写入权限</td><td>必须</td><td><?php echo is_writable(__DIR__) ? '<span class="success"><i class="fa fa-check-circle"></i> 可写入</span>' : '<span class="error"><i class="fa fa-times-circle"></i> 不可写入</span>'; ?></td></tr>
    </table>
    <div class="step-buttons">
        <a href="?do=0" class="el-button"><i class="fa fa-arrow-left"></i> 上一步</a>
        <a href="?do=2" class="el-button el-button--primary">下一步 <i class="fa fa-arrow-right"></i></a>
    </div>

<?php
} elseif ($do == 2) {
?>
    <div class="el-card__header" style="margin: -20px -20px 20px -20px;">
        <span class="el-card__title"><i class="fa fa-database" style="margin-right: 8px;"></i>数据库配置</span>
    </div>
    <form action="?do=3" method="POST">
        <div class="el-form-item">
            <label class="el-form-item__label">数据库地址</label>
            <div class="el-input">
                <input type="text" name="db_host" class="el-input__inner" value="localhost" required>
            </div>
        </div>
        <div class="el-form-item">
            <label class="el-form-item__label">数据库端口</label>
            <div class="el-input">
                <input type="text" name="db_port" class="el-input__inner" value="3306" required>
            </div>
        </div>
        <div class="el-form-item">
            <label class="el-form-item__label">数据库用户名</label>
            <div class="el-input">
                <input type="text" name="db_user" class="el-input__inner" placeholder="root" required>
            </div>
        </div>
        <div class="el-form-item">
            <label class="el-form-item__label">数据库密码</label>
            <div class="el-input">
                <input type="password" name="db_pass" class="el-input__inner" placeholder="数据库密码">
            </div>
        </div>
        <div class="el-form-item">
            <label class="el-form-item__label">数据库名称</label>
            <div class="el-input">
                <input type="text" name="db_name" class="el-input__inner" placeholder="数据库名称" required>
            </div>
        </div>
        <div style="margin: 25px 0; border-top: 1px solid var(--el-border-color);"></div>
        <div style="font-size: 16px; font-weight: 500; color: var(--el-text-color-primary); margin-bottom: 15px;">
            <i class="fa fa-cog" style="margin-right: 8px;"></i>系统设置
        </div>
        <div class="el-form-item">
            <label class="el-form-item__label">系统名称</label>
            <div class="el-input">
                <input type="text" name="site_title" class="el-input__inner" placeholder="Ning.Si软件源" required>
            </div>
        </div>
        <div class="el-form-item">
            <label class="el-form-item__label">管理员账号</label>
            <div class="el-input">
                <input type="text" name="admin_username" class="el-input__inner" placeholder="admin">
            </div>
        </div>
        <div class="el-form-item">
            <label class="el-form-item__label">管理员密码</label>
            <div class="el-input">
                <input type="text" name="admin_password" class="el-input__inner" placeholder="admin123">
            </div>
        </div>
        <div class="step-buttons">
            <a href="?do=1" class="el-button"><i class="fa fa-arrow-left"></i> 上一步</a>
            <button type="submit" class="el-button el-button--primary">
                <i class="fa fa-plug"></i> 测试并保存配置
            </button>
        </div>
    </form>

<?php
} elseif ($do == 3) {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_port = intval($_POST['db_port'] ?? 3306);
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $db_name = $_POST['db_name'] ?? '';
    
    $site_title = $_POST['site_title'] ?? 'Ning.Si软件源';
    $admin_username = $_POST['admin_username'] ?? 'admin';
    $admin_password = $_POST['admin_password'] ?? 'admin123';
    
    $_SESSION['site_title'] = $site_title;
    $_SESSION['admin_username'] = $admin_username;
    $_SESSION['admin_password'] = $admin_password;

    if (empty($db_user) || empty($db_name)) {
        echo '<div class="el-alert el-alert--error">
            <i class="fa fa-exclamation-circle el-alert__icon"></i>
            <div class="el-alert__content">请填写完整的数据库信息</div>
        </div>';
        echo '<div class="step-buttons-center">
            <a href="?do=2" class="el-button el-button--primary is-round"><i class="fa fa-arrow-left"></i> 返回上一步</a>
        </div>';
    } else {
        try {
            $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $config_content = "<?php\n";
            $config_content .= "/** 配置文件 - 生成于 " . date('Y-m-d H:i:s') . " */\n\n";
            $config_content .= "if (!defined('IN_SYSTEM')) { define('IN_SYSTEM', true); }\n\n";
            $config_content .= "define('DB_HOST', '{$db_host}');\n";
            $config_content .= "define('DB_USER', '{$db_user}');\n";
            $config_content .= "define('DB_PASS', '{$db_pass}');\n";
            $config_content .= "define('DB_NAME', '{$db_name}');\n";
            $config_content .= "define('DB_CHARSET', 'utf8mb4');\n";
            $config_content .= "define('DB_PREFIX', 'ios_');\n\n";
            $config_content .= "define('SYSTEM_URL', 'http://' . \$_SERVER['HTTP_HOST']);\n\n";
            $config_content .= "define('CARD_KEY_LENGTH', 16);\ndefine('DEFAULT_EXPIRE_DAYS', 30);\n\n";
            $config_content .= "define('API_SECRET', '" . (function_exists('random_bytes') ? bin2hex(random_bytes(16)) : uniqid('', true)) . "');\n";
            $config_content .= "define('API_RATE_LIMIT', 100);\n\n";
            $config_content .= "date_default_timezone_set('Asia/Shanghai');\n\n";
            $config_content .= "if (session_status() == PHP_SESSION_NONE) { session_start(); }\n";

            if (file_put_contents(__DIR__ . '/config.php', $config_content)) {
                $_SESSION['db_host'] = $db_host;
                $_SESSION['db_port'] = $db_port;
                $_SESSION['db_user'] = $db_user;
                $_SESSION['db_pass'] = $db_pass;
                $_SESSION['db_name'] = $db_name;
                $_SESSION['site_title'] = $site_title;
                $_SESSION['admin_username'] = $admin_username;
                $_SESSION['admin_password'] = $admin_password;
                echo '<div class="el-alert el-alert--success">
                    <i class="fa fa-check-circle el-alert__icon"></i>
                    <div class="el-alert__content">数据库连接成功！配置文件已保存。</div>
                </div>';
                echo '<div class="step-buttons-center">
                    <a href="?do=4" class="el-button el-button--success is-round">
                        <i class="fa fa-database"></i> 导入数据表
                    </a>
                </div>';
            } else {
                echo '<div class="el-alert el-alert--error">
                    <i class="fa fa-exclamation-circle el-alert__icon"></i>
                    <div class="el-alert__content">配置文件保存失败，请检查目录写入权限</div>
                </div>';
                echo '<div class="step-buttons-center">
                    <a href="?do=2" class="el-button el-button--primary is-round"><i class="fa fa-arrow-left"></i> 返回上一步</a>
                </div>';
            }
        } catch (PDOException $e) {
            $error_msg = '数据库连接失败：';
            if (strpos($e->getMessage(), '1045') !== false) $error_msg .= '用户名或密码错误';
            elseif (strpos($e->getMessage(), '2002') !== false) $error_msg .= '数据库服务器连接失败';
            elseif (strpos($e->getMessage(), '1044') !== false) $error_msg .= '没有权限访问该数据库';
            elseif (strpos($e->getMessage(), '1049') !== false) $error_msg .= '数据库不存在，请先在面板中创建数据库';
            else $error_msg .= $e->getMessage();
            echo '<div class="el-alert el-alert--error">
                <i class="fa fa-exclamation-circle el-alert__icon"></i>
                <div class="el-alert__content">' . $error_msg . '</div>
            </div>';
            echo '<div class="step-buttons-center">
                <a href="?do=2" class="el-button el-button--primary is-round"><i class="fa fa-arrow-left"></i> 返回上一步</a>
            </div>';
        }
    }

} elseif ($do == 4) {
    if (!isset($_SESSION['db_host'])) {
        echo '<div class="el-alert el-alert--error">
            <i class="fa fa-exclamation-circle el-alert__icon"></i>
            <div class="el-alert__content">配置信息丢失，请重新配置数据库</div>
        </div>';
        echo '<div class="step-buttons-center">
            <a href="?do=2" class="el-button el-button--primary is-round"><i class="fa fa-arrow-left"></i> 返回上一步</a>
        </div>';
    } else {
        try {
            $pdo = new PDO("mysql:host={$_SESSION['db_host']};port={$_SESSION['db_port']};dbname={$_SESSION['db_name']};charset=utf8mb4", $_SESSION['db_user'], $_SESSION['db_pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql_file = __DIR__ . '/install.sql';
            if (!file_exists($sql_file)) {
                echo '<div class="el-alert el-alert--error">
                    <i class="fa fa-exclamation-circle el-alert__icon"></i>
                    <div class="el-alert__content">install.sql 文件不存在</div>
                </div>';
                echo '<div class="step-buttons-center">
                    <a href="?do=3" class="el-button el-button--primary is-round"><i class="fa fa-arrow-left"></i> 返回上一步</a>
                </div>';
            } else {
                $sql_content = file_get_contents($sql_file);
                
                $sql_content = preg_replace('/CREATE\s+DATABASE\s+.*?;/is', '', $sql_content);
                $sql_content = preg_replace('/USE\s+\w+\s*;/i', '', $sql_content);
                $sql_content = preg_replace('/--.*?$/m', '', $sql_content);
                $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
                
                $statements = array_filter(array_map('trim', explode(';', $sql_content)));
                $success = 0; $failed = 0; $errors = [];
                $critical_errors = [];
                
                foreach ($statements as $sql) {
                    $sql = trim($sql);
                    if (empty($sql)) continue;
                    if (preg_match('/^(--|\/\/|#)/', $sql)) continue;
                    if (preg_match('/^(CREATE|INSERT|UPDATE|DELETE|DROP|ALTER|SELECT)/i', $sql)) {
                        try {
                            $pdo->exec($sql);
                            $success++;
                        } catch (PDOException $e) {
                            $failed++;
                            $error_msg = $e->getMessage();
                            if (!in_array($error_msg, $errors)) {
                                $errors[] = $error_msg;
                            }
                            if (strpos($error_msg, '1044') !== false) {
                                $critical_errors[] = '数据库用户权限不足，无法创建表。请联系管理员授予权限或使用已有数据库。';
                            }
                        }
                    }
                }
                
                echo '<div class="el-card__header" style="margin: -20px -20px 20px -20px;">
                    <span class="el-card__title"><i class="fa fa-chart-bar" style="margin-right: 8px;"></i>安装结果</span>
                </div>';
                
                if (!empty($critical_errors)) {
                    echo '<div class="el-alert el-alert--error">
                        <i class="fa fa-exclamation-circle el-alert__icon"></i>
                        <div class="el-alert__content">' . implode('<br>', array_unique($critical_errors)) . '</div>
                    </div>';
                    echo '<div style="background:#fdf6ec;padding:15px;border-radius:4px;margin:15px 0;border:1px solid #faecd8;">';
                    echo '<p style="font-weight:600;color:#e6a23c;margin-bottom:10px;"><i class="fa fa-lightbulb" style="margin-right:6px;"></i>解决方案：</p>';
                    echo '<p style="margin-bottom:5px;">1. 在数据库管理面板手动创建数据库 <code>' . htmlspecialchars($_SESSION['db_name']) . '</code></p>';
                    echo '<p style="margin-bottom:5px;">2. 给用户授予 CREATE、DROP、INSERT、UPDATE、DELETE、SELECT 权限</p>';
                    echo '<p>3. 或使用已有数据库重新安装</p>';
                    echo '</div>';
                    echo '<div class="step-buttons">
                        <a href="?do=2" class="el-button"><i class="fa fa-redo"></i> 重新配置</a>
                        <a href="?do=5" class="el-button el-button--primary">强制完成 <i class="fa fa-arrow-right"></i></a>
                    </div>';
                } elseif ($failed == 0 && $success > 0) {
                    $admin_username = $_SESSION['admin_username'] ?? 'admin';
                    $admin_password_plain = $_SESSION['admin_password'] ?? 'admin123';
                    $admin_password_hash = password_hash($admin_password_plain, PASSWORD_DEFAULT);
                    
                    try {
                        $pdo->exec("DELETE FROM ios_admins WHERE username = 'admin'");
                        $stmt = $pdo->prepare("INSERT INTO ios_admins (username, password, nickname, status) VALUES (?, ?, ?, 1)");
                        $stmt->execute([$admin_username, $admin_password_hash, '超级管理员']);
                        
                        // 更新 site_title 到 config 表
                        $site_title = $_SESSION['site_title'] ?? 'Ning.Si软件源';
                        $stmt = $pdo->prepare("UPDATE ios_config SET value = ? WHERE name = 'site_title'");
                        $stmt->execute([$site_title]);
                        $stmt = $pdo->prepare("UPDATE ios_config SET value = ? WHERE name = 'frontend_title'");
                        $stmt->execute([$site_title]);
                    } catch (PDOException $e) {
                    }
                    
                    echo '<div class="el-alert el-alert--success">
                        <i class="fa fa-check-circle el-alert__icon"></i>
                        <div class="el-alert__content">数据表创建成功！共执行 ' . $success . ' 条 SQL。</div>
                    </div>';
                    echo '<div class="step-buttons-center">
                        <a href="?do=5" class="el-button el-button--success is-round">
                            <i class="fa fa-flag-checkered"></i> 完成安装
                        </a>
                    </div>';
                } elseif ($success == 0) {
                    echo '<div class="el-alert el-alert--warning">
                        <i class="fa fa-exclamation-triangle el-alert__icon"></i>
                        <div class="el-alert__content">未执行任何 SQL 语句</div>
                    </div>';
                    echo '<p style="color:var(--el-color-info);font-size:13px;margin:10px 0;">可能原因：SQL 文件为空或所有表已存在。</p>';
                    echo '<div class="step-buttons-center">
                        <a href="?do=5" class="el-button el-button--primary is-round">
                            <i class="fa fa-arrow-right"></i> 继续完成安装
                        </a>
                    </div>';
                } else {
                    echo '<div class="el-alert el-alert--warning">
                        <i class="fa fa-exclamation-triangle el-alert__icon"></i>
                        <div class="el-alert__content">安装完成但有错误。成功：' . $success . ' 条，失败：' . $failed . ' 条</div>
                    </div>';
                    if (!empty($errors)) {
                        echo '<div class="sql-log">' . implode("<br>", array_slice($errors, 0, 3)) . '</div>';
                    }
                    echo '<p style="color:var(--el-color-info);font-size:13px;margin:10px 0;">部分错误可能是表已存在导致，不影响正常使用。</p>';
                    echo '<div class="step-buttons-center">
                        <a href="?do=5" class="el-button el-button--primary is-round">
                            <i class="fa fa-arrow-right"></i> 继续完成安装
                        </a>
                    </div>';
                }
            }
        } catch (PDOException $e) {
            echo '<div class="el-alert el-alert--error">
                <i class="fa fa-exclamation-circle el-alert__icon"></i>
                <div class="el-alert__content">数据库连接失败：' . $e->getMessage() . '</div>
            </div>';
            echo '<div class="step-buttons-center">
                <a href="?do=2" class="el-button el-button--primary is-round"><i class="fa fa-arrow-left"></i> 返回上一步</a>
            </div>';
        }
    }

} elseif ($do == 5) {
    $admin_username = $_SESSION['admin_username'] ?? 'admin';
    $admin_password = $_SESSION['admin_password'] ?? 'admin123';
    $lock_content = "安装时间：" . date('Y-m-d H:i:s') . "\n安装IP：" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
    if (@file_put_contents(__DIR__ . '/install.lock', $lock_content)) {
        echo '<div class="el-card__header" style="margin: -20px -20px 20px -20px;">
            <span class="el-card__title"><i class="fa fa-check-circle" style="margin-right: 8px; color: var(--el-color-success);"></i>安装完成！</span>
        </div>';
        echo '<div class="el-alert el-alert--success">
            <i class="fa fa-check-circle el-alert__icon"></i>
            <div class="el-alert__content">
                <strong>系统安装成功！</strong><br>
                管理员账号：<code>' . htmlspecialchars($admin_username) . '</code><br>
                管理员密码：<code>' . htmlspecialchars($admin_password) . '</code>（请及时修改）
            </div>
        </div>';
        echo '<div style="background:#f5f7fa;padding:15px;border-radius:4px;margin:20px 0;border:1px solid var(--el-border-color);">';
        echo '<p style="font-weight:600;color:var(--el-text-color-primary);margin-bottom:10px;"><i class="fa fa-info-circle" style="margin-right:6px;"></i>后续操作：</p>';
        echo '<p style="margin-bottom:5px;">1. 删除 install.php 文件（已自动锁定）</p>';
        echo '<p style="margin-bottom:5px;">2. 登录后台修改默认密码</p>';
        echo '<p>3. 配置软件源和应用</p></div>';
        echo '<div class="step-buttons-center">
            <a href="admin/login.php" class="el-button el-button--success is-round">
                <i class="fa fa-sign-in-alt"></i> 进入后台管理
            </a>
        </div>';
    } else {
        echo '<div class="el-alert el-alert--warning">
            <i class="fa fa-exclamation-triangle el-alert__icon"></i>
            <div class="el-alert__content">安装完成，但无法创建锁定文件。</div>
        </div>';
        echo '<p style="margin:15px 0;">请手动在网站根目录创建 <code>install.lock</code> 文件。</p>';
        echo '<div class="step-buttons-center">
            <a href="admin/login.php" class="el-button el-button--success is-round">
                <i class="fa fa-sign-in-alt"></i> 进入后台管理
            </a>
        </div>';
    }
    unset($_SESSION['db_host']);
    unset($_SESSION['site_title']);
    unset($_SESSION['install_check']);
}
?>

            </div>
        </div>
    </div>
</body>
</html>
