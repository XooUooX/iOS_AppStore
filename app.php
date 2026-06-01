<?php
/**
 * Ning.Si软件源 - 应用列表页面
 */

define('IN_SYSTEM', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class.database.php';

$db = Database::getInstance();

// 获取网站设置
$siteSettings = [];
$settingNames = ['site_title', 'site_keywords', 'site_description', 'frontend_title', 'icp_number', 'copyright', 'sourceicon'];
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
$sourceIcon = $siteSettings['sourceicon'] ?? '';

// 获取统计数据
$totalApps = $db->fetch("SELECT COUNT(*) as total FROM " . $db->getTable('category') . " WHERE status = 'normal'")['total'] ?? 0;
$freeApps = $db->fetch("SELECT COUNT(*) as total FROM " . $db->getTable('category') . " WHERE status = 'normal' AND (bt2b IS NULL OR bt2b != '1')")['total'] ?? 0;
$paidApps = $db->fetch("SELECT COUNT(*) as total FROM " . $db->getTable('category') . " WHERE status = 'normal' AND bt2b = '1'")['total'] ?? 0;

// 获取搜索关键词和分页参数
$searchKeyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// 获取应用分类和应用列表
$appTypes = ['1' => '应用', '2' => '游戏', '3' => '影音', '4' => '工具', '5' => '插件', 'default' => '默认'];
$appsByType = [];

// 先获取所有不同的应用类型
if (empty($searchKeyword)) {
    $allTypes = $db->fetchAll("SELECT DISTINCT type FROM " . $db->getTable('category') . " WHERE status = 'normal' AND type != '' ORDER BY type");
} else {
    $allTypes = $db->fetchAll("SELECT DISTINCT type FROM " . $db->getTable('category') . " WHERE status = 'normal' AND type != '' AND name LIKE ? ORDER BY type", ['%' . $searchKeyword . '%']);
}

foreach ($allTypes as $typeRow) {
    $typeId = $typeRow['type'];
    $typeName = $appTypes[$typeId] ?? $typeId;
    
    $whereClause = "type = ? AND status = 'normal'";
    $params = [$typeId];
    
    if (!empty($searchKeyword)) {
        $whereClause .= " AND name LIKE ?";
        $params[] = '%' . $searchKeyword . '%';
    }
    
    $countSql = "SELECT COUNT(*) as total FROM " . $db->getTable('category') . " WHERE $whereClause";
    $totalResult = $db->fetch($countSql, $params);
    $totalAppsInType = $totalResult['total'] ?? 0;
    
    $offset = ($page - 1) * $perPage;
    $sql = "SELECT id, name, nickname, image, keywords, bt1a, bt1b, bt2a, bt2b, flag, updatetime FROM " . $db->getTable('category') . " WHERE $whereClause ORDER BY weigh DESC LIMIT $perPage OFFSET $offset";
    $apps = $db->fetchAll($sql, $params);
    
    if (!empty($apps) || !empty($searchKeyword)) {
        $appsByType[$typeId] = [
            'name' => $typeName,
            'apps' => $apps,
            'total' => $totalAppsInType,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($totalAppsInType / $perPage)
        ];
    }
}
$activeType = !empty($appsByType) ? key($appsByType) : null;

// 获取协议和域名
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$domain = $protocol . '://' . $_SERVER['HTTP_HOST'];
$appstoreUrl = $domain . '/appstore';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>应用列表 - <?php echo htmlspecialchars($siteTitle); ?></title>
    <?php if ($siteKeywords): ?><meta name="keywords" content="<?php echo htmlspecialchars($siteKeywords); ?>"><?php endif; ?>
    <?php if ($siteDescription): ?><meta name="description" content="<?php echo htmlspecialchars($siteDescription); ?>"><?php endif; ?>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/app.css">
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
        <div class="el-page-header">
            <h2 class="el-page-header__title">应用中心</h2>
            <p class="el-page-header__subtitle">发现优质iOS应用，一键安装体验</p>
        </div>

        <!-- 统计信息 -->
        <section class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><img src="uploads/APP.png" alt="应用总数" class="stat-icon-img"></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $totalApps; ?></div>
                        <div class="stat-label">应用总数</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><img src="uploads/APPS.png" alt="免费应用" class="stat-icon-img"></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $freeApps; ?></div>
                        <div class="stat-label">免费应用</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><img src="uploads/Down.png" alt="付费应用" class="stat-icon-img"></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $paidApps; ?></div>
                        <div class="stat-label">付费应用</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><img src="uploads/Star.png" alt="应用分类" class="stat-icon-img"></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($appsByType); ?></div>
                        <div class="stat-label">应用分类</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 快捷操作按钮 -->
        <section class="quick-actions">
            <div class="actions-grid">
                <a href="esign://addsource?url=<?php echo $appstoreUrl; ?>" class="action-button">
                    <div class="action-icon-wrapper"><img src="uploads/qingsong.png" alt="轻松签" class="action-icon"></div>
                    <span class="action-label">轻松签</span>
                </a>
                <a href="nsk-sign://addsource?url=<?php echo $appstoreUrl; ?>" class="action-button">
                    <div class="action-icon-wrapper"><img src="uploads/quanneng.png" alt="全能签" class="action-icon"></div>
                    <span class="action-label">全能签</span>
                </a>
                <a href="wnq-signtool://addsource?url=<?php echo $appstoreUrl; ?>" class="action-button">
                    <div class="action-icon-wrapper"><img src="uploads/wanneng.png" alt="万能签" class="action-icon"></div>
                    <span class="action-label">万能签</span>
                </a>
                <a href="index.php" class="action-button">
                    <div class="action-icon-wrapper"><img src="uploads/Key.png" alt="卡密激活" class="action-icon"></div>
                    <span class="action-label">卡密激活</span>
                </a>
            </div>
        </section>

        <!-- Search -->
        <form method="GET" action="" class="search-form">
            <div class="el-input-group">
                <span class="el-input-group__prepend"><i class="fa fa-search"></i></span>
                <input type="text" name="search" class="el-input__inner" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="搜索应用名称...">
                <button type="submit" class="el-input-group__append">搜索</button>
            </div>
            <?php if (!empty($searchKeyword)): ?>
            <div style="text-align: center; margin-top: 12px; color: #606266;">
                搜索结果："<span style="color: var(--el-color-primary); font-weight: 500;"><?php echo htmlspecialchars($searchKeyword); ?></span>"
                <a href="app.php" style="margin-left: 10px; color: #909399; text-decoration: none;"><i class="fa fa-times"></i> 清除</a>
            </div>
            <?php endif; ?>
        </form>

        <?php if (!empty($appsByType)): ?>
        <!-- Tabs -->
        <div class="el-tabs__header">
            <?php foreach ($appsByType as $typeId => $typeData): ?>
            <button class="el-tabs__item <?php echo $typeId === $activeType ? 'is-active' : ''; ?>" 
                    data-type="<?php echo $typeId; ?>" 
                    onclick="switchTab('<?php echo $typeId; ?>')">
                <i class="fa fa-folder"></i>
                <?php echo $typeData['name']; ?>
                <span class="tab-count"><?php echo $typeData['total']; ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- App Grid -->
        <div class="tab-content" id="appContent">
            <?php foreach ($appsByType as $typeId => $typeData): ?>
            <div class="app-panel <?php echo $typeId === $activeType ? '' : 'hidden'; ?>" id="panel-<?php echo $typeId; ?>">
                <div class="el-row el-row--grid">
                    <?php foreach ($typeData['apps'] as $app): 
                        $appIcon = !empty($app['image']) ? $app['image'] : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAABYlAAAWJQFJUFtwAAAAGXRFWHRTb2Z0d2FyZQB3d3cuaW5rc2NhcGUub3Jnm+48GgAAABlJREFUaIHjwTEAADgEwcCnL9/9AAAAAABJRU5ErkJggg==';
                        $appSize = !empty($app['bt2a']) ? round($app['bt2a'] / 1024 / 1024, 1) . ' MB' : '';
                        $isPaid = !empty($app['bt2b']) && $app['bt2b'] == '1';
                        $appData = json_encode([
                            'id' => $app['id'],
                            'name' => $app['name'],
                            'nickname' => $app['nickname'],
                            'image' => $appIcon,
                            'size' => $appSize,
                            'keywords' => $app['keywords'],
                            'isPaid' => $isPaid
                        ], JSON_UNESCAPED_UNICODE);
                    ?>
                    <div class="el-card app-card" onclick='showAppModal(<?php echo htmlspecialchars($appData, ENT_QUOTES); ?>)'>
                        <?php if ($isPaid): ?>
                        <div class="el-card__lock"><i class="fa fa-lock"></i> 付费</div>
                        <?php endif; ?>
                        <div class="el-card__body">
                            <div class="el-avatar">
                                <img src="<?php echo htmlspecialchars($appIcon); ?>" 
                                     alt="<?php echo htmlspecialchars($app['name']); ?>"
                                     onerror="this.src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAABYlAAAWJQFJUFtwAAAAGXRFWHRTb2Z0d2FyZQB3d3cuaW5rc2NhcGUub3Jnm+48GgAAABlJREFUaIHjwTEAADgEwcCnL9/9AAAAAABJRU5ErkJggg=='">
                            </div>
                            <div class="el-card__name"><?php echo htmlspecialchars($app['name']); ?></div>
                            <div class="el-card__version"><?php echo htmlspecialchars($app['nickname']); ?></div>
                            <div class="el-card__footer">
                                <span class="el-card__size"><i class="fa fa-database"></i> <?php echo $appSize; ?></span>
                                <?php if ($isPaid): ?>
                                <span class="el-tag el-tag--primary"><i class="fa fa-lock"></i> 付费</span>
                                <?php else: ?>
                                <span class="el-tag el-tag--success"><i class="fa fa-check-circle"></i> 免费</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($typeData['totalPages'] > 1): ?>
                <div class="el-pagination-wrapper">
                    <div class="el-pagination">
                        <?php if ($typeData['page'] > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="el-pagination__btn" title="首页">
                            <i class="fa fa-step-backward"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $typeData['page'] - 1])); ?>" class="el-pagination__btn" title="上一页">
                            <i class="fa fa-chevron-left"></i>
                        </a>
                        <?php else: ?>
                        <span class="el-pagination__btn is-disabled"><i class="fa fa-step-backward"></i></span>
                        <span class="el-pagination__btn is-disabled"><i class="fa fa-chevron-left"></i></span>
                        <?php endif; ?>
                        
                        <div class="el-pagination__pages">
                            <?php 
                            $startPage = max(1, $typeData['page'] - 2);
                            $endPage = min($typeData['totalPages'], $typeData['page'] + 2);
                            if ($startPage > 1): 
                            ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="el-pagination__btn">1</a>
                            <?php if ($startPage > 2): ?>
                            <span class="el-pagination__ellipsis">...</span>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <?php if ($i == $typeData['page']): ?>
                                <span class="el-pagination__btn is-active"><?php echo $i; ?></span>
                                <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="el-pagination__btn"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $typeData['totalPages']): ?>
                            <?php if ($endPage < $typeData['totalPages'] - 1): ?>
                            <span class="el-pagination__ellipsis">...</span>
                            <?php endif; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $typeData['totalPages']])); ?>" class="el-pagination__btn"><?php echo $typeData['totalPages']; ?></a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($typeData['page'] < $typeData['totalPages']): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $typeData['page'] + 1])); ?>" class="el-pagination__btn" title="下一页">
                            <i class="fa fa-chevron-right"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $typeData['totalPages']])); ?>" class="el-pagination__btn" title="末页">
                            <i class="fa fa-step-forward"></i>
                        </a>
                        <?php else: ?>
                        <span class="el-pagination__btn is-disabled"><i class="fa fa-chevron-right"></i></span>
                        <span class="el-pagination__btn is-disabled"><i class="fa fa-step-forward"></i></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="el-pagination__info">
                        共 <strong><?php echo $typeData['total']; ?></strong> 个应用，第 <strong><?php echo $typeData['page']; ?></strong>/<strong><?php echo $typeData['totalPages']; ?></strong> 页
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="el-empty">
            <div class="el-empty__image"><i class="fa fa-box-open"></i></div>
            <p class="el-empty__description">暂无应用</p>
            <p class="el-empty__action"><a href="app.php" style="color: var(--el-color-primary);">返回首页</a></p>
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
    
    <!-- 返回顶部按钮 -->
    <button id="backToTop" class="back-to-top" onclick="scrollToTop()" title="返回顶部">
        <i class="fa fa-arrow-up"></i>
    </button>

    <!-- 应用详情底部弹窗 -->
    <div id="app-sheet-modal" class="sheet-modal" onclick="if(event.target===this)hideAppModal()">
        <div class="sheet-modal-overlay"></div>
        <div class="sheet-modal-container">
            <div class="sheet-modal-handle-wrapper"><div class="sheet-modal-handle"></div></div>
            <div class="sheet-modal-content">
                <div class="sheet-app-header">
                    <img id="sheet-app-icon" src="" class="sheet-app-icon" alt="">
                    <div class="sheet-app-title-area">
                        <div id="sheet-app-name" class="sheet-app-name"></div>
                        <div class="sheet-app-meta">
                            <span id="sheet-app-version"></span>
                            <span id="sheet-app-size"></span>
                            <span id="sheet-app-tag"></span>
                        </div>
                    </div>
                </div>
                <div class="sheet-app-body">
                    <div id="sheet-app-description" class="sheet-app-description"></div>
                </div>
                <button class="sheet-download-button" onclick="showSignToolsModal()">
                    <i class="fa fa-download" style="margin-right: 8px;"></i>下载安装
                </button>
                <button class="sheet-close-button" onclick="hideAppModal()">关闭</button>
            </div>
        </div>
    </div>

    <!-- 签名工具选择弹窗 -->
    <div id="sign-tools-modal" class="sheet-modal" onclick="if(event.target===this)hideSignToolsModal()">
        <div class="sheet-modal-overlay"></div>
        <div class="sheet-modal-container">
            <div class="sheet-modal-handle-wrapper"><div class="sheet-modal-handle"></div></div>
            <div class="sheet-modal-content">
                <div class="sheet-app-header" style="border-bottom: none; padding-bottom: 8px;">
                    <div class="sheet-app-title-area">
                        <div class="sheet-app-name">选择签名工具</div>
                        <div class="sheet-app-meta">点击下方工具快速添加软件源</div>
                    </div>
                </div>
                <div class="sign-tools-grid">
                    <a href="esign://addsource?url=<?php echo $appstoreUrl; ?>" class="sign-tool-item">
                        <div class="sign-tool-icon"><img src="uploads/qingsong.png" alt="轻松签"></div>
                        <span class="sign-tool-name">轻松签</span>
                    </a>
                    <a href="nsk-sign://addsource?url=<?php echo $appstoreUrl; ?>" class="sign-tool-item">
                        <div class="sign-tool-icon"><img src="uploads/quanneng.png" alt="全能签"></div>
                        <span class="sign-tool-name">全能签</span>
                    </a>
                    <a href="wnq-signtool://addsource?url=<?php echo $appstoreUrl; ?>" class="sign-tool-item">
                        <div class="sign-tool-icon"><img src="uploads/wanneng.png" alt="万能签"></div>
                        <span class="sign-tool-name">万能签</span>
                    </a>
                    <a href="index.php" class="sign-tool-item">
                        <div class="sign-tool-icon"><img src="uploads/Key.png" alt="卡密激活"></div>
                        <span class="sign-tool-name">卡密激活</span>
                    </a>
                </div>
                <button class="sheet-close-button" onclick="hideSignToolsModal()">关闭</button>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
