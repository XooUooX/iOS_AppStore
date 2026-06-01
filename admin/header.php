<?php
function renderHeader($title, $activeMenu = '') {
    $menuItems = [
        ['id' => 'index', 'url' => 'index.php', 'icon' => 'chart', 'title' => '概览'],
        ['section' => '卡密相关'],
        ['id' => 'card_keys', 'url' => 'card_keys.php', 'icon' => 'credit-card', 'title' => '卡密管理'],
        ['id' => 'card_claim', 'url' => 'card_claim.php', 'icon' => 'gift', 'title' => '卡密领取'],
        ['section' => '设备管理'],
        ['id' => 'devices', 'url' => 'devices.php', 'icon' => 'smartphone', 'title' => '设备管理'],
        ['id' => 'black_list', 'url' => 'black_list.php', 'icon' => 'ban', 'title' => '设备黑名单'],
        ['section' => '软件源管理'],
        ['id' => 'sources', 'url' => 'sources.php', 'icon' => 'settings', 'title' => '软件源配置'],
        ['id' => 'copy_source', 'url' => 'copy_source.php', 'icon' => 'download', 'title' => '软件源复制'],
        ['section' => '资源管理'],
        ['id' => 'apps', 'url' => 'apps.php', 'icon' => 'package', 'title' => '应用管理'],
        ['id' => 'docs', 'url' => 'docs.php', 'icon' => 'book', 'title' => '文档管理'],
        ['section' => '监控与接口'],
        ['id' => 'api_request', 'url' => 'api_request.php', 'icon' => 'link', 'title' => 'API请求'],
        ['id' => 'monitor', 'url' => 'monitor.php', 'icon' => 'eye', 'title' => '监控记录'],
        ['section' => '网站管理'],
        ['id' => 'site_settings', 'url' => 'site_settings.php', 'icon' => 'sliders', 'title' => '网站设置'],
        ['id' => 'site_security', 'url' => 'site_security.php', 'icon' => 'lock', 'title' => '网站安全'],
    ];
    
    $username = $_SESSION['admin_username'] ?? 'Admin';
    $userInitial = strtoupper(substr($username, 0, 1));
    
    $siteTitle = '';
    $copyright = '';
    try {
        $db = Database::getInstance();
        $siteTitle = $db->fetch("SELECT value FROM " . $db->getTable('config') . " WHERE name = 'site_title'")['value'] ?? '';
        $copyright = $db->fetch("SELECT value FROM " . $db->getTable('config') . " WHERE name = 'copyright'")['value'] ?? '';
    } catch (Exception $e) {
        $siteTitle = '';
        $copyright = '';
    }
    
    if (empty($copyright)) {
        $copyright = '© 2026 Ning.Si软件源管理系统';
    }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <link rel="stylesheet" href="assets/css/apps.css">
    <link rel="stylesheet" href="assets/css/card_keys.css">
    <link rel="stylesheet" href="assets/css/devices.css">
    <link rel="stylesheet" href="assets/css/blacklist.css">
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="logo-icon">
                        <img src="assets/images/logo.png" alt="Logo" class="logo-image">
                    </div>
                    <div class="logo-text">
                        <div class="logo-title"><?php echo htmlspecialchars($siteTitle); ?></div>
                        <div class="logo-subtitle">管理系统</div>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <?php foreach ($menuItems as $item): ?>
                    <?php if (isset($item['section'])): ?>
                        <div class="nav-section">
                            <div class="nav-section-title"><?php echo $item['section']; ?></div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo $item['url']; ?>" class="nav-link <?php echo $activeMenu === $item['id'] ? 'active' : ''; ?>">
                            <?php if ($item['icon'] === 'chart'): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"></rect><rect width="7" height="5" x="14" y="3" rx="1"></rect><rect width="7" height="9" x="14" y="12" rx="1"></rect><rect width="7" height="5" x="3" y="16" rx="1"></rect></svg>
                            <?php elseif ($item['icon'] === 'credit-card'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            <?php elseif ($item['icon'] === 'gift'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 2 4 2 4 12"/><rect x="2" y="7" width="20" height="15" rx="2" ry="2"/><path d="M12 7v10M7 12h10"/></svg>
                            <?php elseif ($item['icon'] === 'smartphone'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            <?php elseif ($item['icon'] === 'ban'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                            <?php elseif ($item['icon'] === 'settings'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6M4.22 4.22l4.24 4.24m5.08 5.08l4.24 4.24M1 12h6m6 0h6M4.22 19.78l4.24-4.24m5.08-5.08l4.24-4.24"/></svg>
                            <?php elseif ($item['icon'] === 'download'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <?php elseif ($item['icon'] === 'package'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                            <?php elseif ($item['icon'] === 'book'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            <?php elseif ($item['icon'] === 'link'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            <?php elseif ($item['icon'] === 'eye'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <?php elseif ($item['icon'] === 'sliders'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
                            <?php elseif ($item['icon'] === 'lock'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <?php endif; ?>
                            <span><?php echo $item['title']; ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    <span>退出登录</span>
                </a>
            </div>
        </aside>
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-title">
                    <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
                    <h2><?php echo $title; ?></h2>
                </div>
            </header>
            <div class="admin-content">
    <?php
}

// 在页面底部添加脚本引入的函数
function renderFooter() {
    global $copyright;
    ?>
            </div>
        </main>
    </div>
    <script src="assets/js/admin.js"></script>
    <script src="assets/js/apps.js"></script>
    <script src="assets/js/card_keys.js"></script>
    <script src="assets/js/devices.js"></script>
    <script src="assets/js/blacklist.js"></script>
    <!-- 页脚 -->
    <footer class="admin-footer">
        <div class="footer-content">
            <div class="footer-left">
                <p class="footer-copyright"><?php echo htmlspecialchars($copyright); ?></p>
            </div>
            <div class="footer-right">
                <p class="footer-info">
                    <a href="https://github.com/XooUooX/iOS_AppStore" target="_blank" rel="noopener noreferrer" class="footer-link">
                        <span>Ning.Si软件源管理系统</span>
                    </a>
                </p>
            </div>
        </div>
    </footer>

    <style>
    .admin-footer {
        background: var(--white);
        border-top: 1px solid var(--gray-200);
        padding: 20px 32px;
        margin-top: 40px;
        text-align: center;
        font-size: 12px;
        color: var(--gray-500);
    }

    .footer-content {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }

    .footer-left,
    .footer-right {
        flex: none;
    }

    .footer-left {
        text-align: center;
    }

    .footer-right {
        text-align: center;
    }

    .footer-copyright {
        margin: 0;
        color: var(--gray-600);
        font-weight: 500;
    }

    .footer-info {
        margin: 0;
        color: var(--gray-500);
    }

    .footer-link {
        color: var(--gray-500);
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .footer-link:hover {
        color: var(--primary);
        text-decoration: underline;
    }

    @media screen and (max-width: 768px) {
        .admin-footer {
            padding: 16px 20px;
        }

        .footer-content {
            flex-direction: column;
            gap: 12px;
        }

        .footer-left,
        .footer-right {
            text-align: center;
        }
    }
    </style>
</body>
</html>
    <?php
}
