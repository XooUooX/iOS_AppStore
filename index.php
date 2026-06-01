<?php
/**
 * Ning.Si软件源管理系统 - 前台激活页面
 */

// 判断是否已安装
if (!is_file(__DIR__ . '/install.lock')) {
    header("location:./install.php");
    exit;
}

define('IN_SYSTEM', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class.database.php';
require_once __DIR__ . '/includes/class.cardkey.php';
require_once __DIR__ . '/includes/class.device.php';

$result = null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardKey = $_POST['card_key'] ?? '';
    $deviceId = $_POST['device_id'] ?? '';
    
    // 验证UDID格式 (8位十六进制-16位十六进制)
    $udidPattern = '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{16}$/';
    
    if (empty($cardKey) || empty($deviceId)) {
        $error = '卡密和UDID不能为空';
    } elseif (!preg_match($udidPattern, $deviceId)) {
        $error = 'UDID格式不正确，正确格式如：00007130-000318863C03C03D';
    } else {
        $cardKeyObj = new CardKey();
        $result = $cardKeyObj->activate($cardKey, $deviceId);
        
        if ($result['success']) {
            $success = "激活成功！到期时间: " . $result['data']['expire_time'];
        } else {
            $error = $result['message'];
        }
    }
}

// 获取网站设置
$db = Database::getInstance();
$siteSettings = [];
$settingNames = ['site_title', 'site_keywords', 'site_description', 'frontend_title', 'icp_number', 'copyright', 'announcement', 'announcement_enabled', 'show_shortcut_after_activate'];
$placeholders = implode(',', array_fill(0, count($settingNames), '?'));
$rows = $db->fetchAll("SELECT name, value FROM " . $db->getTable('config') . " WHERE name IN ($placeholders)", $settingNames);
foreach ($rows as $row) {
    $siteSettings[$row['name']] = $row['value'];
}
$siteTitle = $siteSettings['site_title'] ?? '';
$frontendTitle = $siteSettings['frontend_title'] ?? '';
$siteKeywords = $siteSettings['site_keywords'] ?? '';
$siteDescription = $siteSettings['site_description'] ?? '';
$icpNumber = $siteSettings['icp_number'] ?? '';
$copyright = $siteSettings['copyright'] ?? '';
$announcement = $siteSettings['announcement'] ?? '';
$announcementEnabled = ($siteSettings['announcement_enabled'] ?? '0') === '1';
$showShortcutAfterActivate = ($siteSettings['show_shortcut_after_activate'] ?? '0') === '1';

// 获取应用分类和应用列表
$appTypes = ['1' => '应用', '2' => '游戏', '3' => '影音', '4' => '工具', '5' => '插件', 'default' => '默认'];
$appsByType = [];

// 先获取所有不同的应用类型
$allTypes = $db->fetchAll("SELECT DISTINCT type FROM " . $db->getTable('category') . " WHERE status = 'normal' AND type != '' ORDER BY type");

foreach ($allTypes as $typeRow) {
    $typeId = $typeRow['type'];
    $typeName = $appTypes[$typeId] ?? $typeId;
    
    $apps = $db->fetchAll("SELECT id, name, nickname, image, keywords, bt1a, bt1b, bt2a, bt2b, flag, updatetime FROM " . $db->getTable('category') . " WHERE type = ? AND status = 'normal' ORDER BY weigh DESC LIMIT 12", [$typeId]);
    if (!empty($apps)) {
        $appsByType[$typeId] = [
            'name' => $typeName,
            'apps' => $apps
        ];
    }
}
$activeType = !empty($appsByType) ? key($appsByType) : null;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <?php if ($siteKeywords): ?><meta name="keywords" content="<?php echo htmlspecialchars($siteKeywords); ?>"><?php endif; ?>
    <?php if ($siteDescription): ?><meta name="description" content="<?php echo htmlspecialchars($siteDescription); ?>"><?php endif; ?>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="el-header">
        <div class="el-header__content">
            <a href="/" class="el-header__logo">
                <i class="fa fa-mobile-alt"></i>
                <?php echo htmlspecialchars($frontendTitle); ?>
            </a>
            <nav class="el-header__nav">
                <a href="app.php"><i class="fa fa-th-large"></i> 应用中心</a>
                <a href="kami.php"><i class="fa fa-gift"></i> 领取卡密</a>
                <a href="docs.php"><i class="fa fa-book"></i> 文档</a>
            </nav>
        </div>
    </header>

    <div class="el-container">

        <?php if ($announcementEnabled && !empty($announcement)): ?>
        <div id="announcementModal" class="el-dialog" onclick="if(event.target===this)closeAnnouncement()">
            <div class="el-dialog__body">
                <div class="el-dialog__header">
                    <span class="el-dialog__title"><i class="fa fa-bullhorn" style="color: var(--el-color-primary); margin-right: 8px;"></i>公告</span>
                    <button class="el-dialog__close" onclick="closeAnnouncement()">&times;</button>
                </div>
                <div class="el-dialog__content"><?php echo $announcement; ?></div>
            </div>
        </div>
        <script src="assets/js/index.js"></script>
        <?php endif; ?>

        <?php
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $domain = $protocol . '://' . $_SERVER['HTTP_HOST'];
        $appstoreUrl = $domain . '/appstore';
        ?>

        <?php if ($showShortcutAfterActivate): ?>
        <div class="el-row">
            <div class="el-col-8">
                <a href="nsk-sign://addsource/?url=<?php echo $appstoreUrl; ?>" class="shortcut-card">
                    <div class="shortcut-icon"><img src="uploads/quanneng.png" alt="全能签" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;"></div>
                    <div>
                        <div class="shortcut-title">添加至全能签</div>
                        <div class="shortcut-desc">一键添加软件源</div>
                    </div>
                </a>
            </div>
            <div class="el-col-8">
                <a href="esign://addsource?url=<?php echo $appstoreUrl; ?>" class="shortcut-card">
                    <div class="shortcut-icon"><img src="uploads/qingsong.png" alt="轻松签" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;"></div>
                    <div>
                        <div class="shortcut-title">添加至轻松签</div>
                        <div class="shortcut-desc">一键添加软件源</div>
                    </div>
                </a>
            </div>
            <div class="el-col-8">
                <a href="wnq-signtool://addsource?url=<?php echo $appstoreUrl; ?>" class="shortcut-card">
                    <div class="shortcut-icon"><img src="uploads/wanneng.png" alt="万能签" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;"></div>
                    <div>
                        <div class="shortcut-title">添加至万能签</div>
                        <div class="shortcut-desc">一键添加软件源</div>
                    </div>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="el-alert el-alert--error">
            <i class="fa fa-exclamation-circle el-alert__icon"></i>
            <div class="el-alert__content"><?php echo $error; ?></div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="el-alert el-alert--success">
            <i class="fa fa-check-circle el-alert__icon"></i>
            <div class="el-alert__content"><?php echo $success; ?></div>
        </div>

        <div class="el-card">
            <div class="el-card__header">
                <span class="el-card__title">激活信息</span>
            </div>
            <div class="el-card__body">
                <div class="el-descriptions">
                    <div class="el-descriptions__item">
                        <span class="el-descriptions__label">设备ID</span>
                        <span class="el-descriptions__content"><?php echo htmlspecialchars(substr($result['data']['device_id'], 0, 20) . '...'); ?></span>
                    </div>
                    <div class="el-descriptions__item">
                        <span class="el-descriptions__label">有效期</span>
                        <span class="el-descriptions__content"><?php echo $result['data']['expire_days']; ?>天</span>
                    </div>
                    <div class="el-descriptions__item">
                        <span class="el-descriptions__label">到期时间</span>
                        <span class="el-descriptions__content success"><?php echo $result['data']['expire_time']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="el-card">
            <div class="el-card__header">
                <span class="el-card__title">卡密激活</span>
            </div>
            <div class="el-card__body">
                <form method="POST" action="">
                    <div class="el-form-item">
                        <label class="el-form-item__label is-required">卡密</label>
                        <div class="el-input">
                            <i class="fa fa-key el-input__icon"></i>
                            <input type="text" name="card_key" class="el-input__inner" placeholder="请输入卡密，如: IOSXXXXXXXXXXXXXXXX" required>
                        </div>
                    </div>
                    <div class="el-form-item">
                        <label class="el-form-item__label is-required">UDID</label>
                        <div class="el-input">
                            <i class="fa fa-mobile el-input__icon"></i>
                            <input type="text" name="device_id" class="el-input__inner" placeholder="请输入设备UDID" required>
                        </div>
                    </div>
                    <button type="submit" class="el-button el-button--primary is-round" style="width: 100%;">
                        <i class="fa fa-bolt"></i> 立即激活
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer class="el-footer">
        <?php if ($icpNumber || $copyright): ?>
        <div style="margin-top: 15px; line-height: 1.8;">
            <?php if ($icpNumber): ?><div><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none; transition: color 0.3s ease;" onmouseover="this.style.color='var(--el-color-primary)'" onmouseout="this.style.color='inherit'"><?php echo htmlspecialchars($icpNumber); ?></a></div><?php endif; ?>
            <?php if ($copyright): ?><div><?php echo htmlspecialchars($copyright); ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
    </footer>
    <script>console.log("\n %c Ning.Si软件源管理系统 %c by Ning.Si | https://github.com/XooUooX/iOS_AppStore ", "color:#fff;background:#409EFF;padding:5px 0;", "color:#eee;background:#444;padding:5px 10px;");</script>
</body>
</html>
