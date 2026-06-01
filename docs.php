<?php
/**
 * 前台 - 文档/帮助页面
 */
define('IN_SYSTEM', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class.database.php';
require_once __DIR__ . '/includes/class.docs.php';
require_once __DIR__ . '/includes/class.markdown.php';

header('Content-Type: text/html; charset=utf-8');

$db = Database::getInstance();

// 获取网站设置
$siteSettings = [];
$settingNames = ['site_title', 'site_keywords', 'site_description', 'frontend_title', 'icp_number', 'copyright'];
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

$docs = new Docs();

// 获取请求的文档
$slug = null;

// 从URL获取slug - 支持多种格式
// 1. /docs.php?slug=xxx
// 2. /docs.php?id=xxx
// 3. /docs/xxx.html (需要 .htaccess 支持)
// 4. /docs/xxx (需要 .htaccess 支持)

if (isset($_GET['slug'])) {
    $slug = $_GET['slug'];
} elseif (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $doc = $docs->getDocById($id);
    if ($doc) {
        $slug = $doc['slug'];
    }
} else {
    // 从 REQUEST_URI 解析 slug
    $requestUri = $_SERVER['REQUEST_URI'];
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);
    
    // 移除脚本目录前缀
    if (strpos($requestUri, $scriptName) === 0) {
        $requestUri = substr($requestUri, strlen($scriptName));
    }
    
    // 移除查询字符串
    if (strpos($requestUri, '?') !== false) {
        $requestUri = substr($requestUri, 0, strpos($requestUri, '?'));
    }
    
    // 解析 /docs/slug.html 或 /docs/slug 格式
    if (preg_match('#^/docs/([a-zA-Z0-9_-]+)(?:\.html)?/?$#', $requestUri, $matches)) {
        $slug = $matches[1];
    }
}

// 如果有slug，显示单个文档
if ($slug) {
    $doc = $docs->getDocBySlug($slug);
    if (!$doc) {
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>文档不存在</title>
            <link rel="stylesheet" href="/assets/css/tailwind.css">
            <link rel="stylesheet" href="/assets/css/font-awesome.min.css">
            <link rel="stylesheet" href="/assets/css/style.css">
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
                <div style="text-align: center; padding: 60px 20px;">
                    <h1 style="font-size: 48px; color: #999; margin-bottom: 20px;">404</h1>
                    <p style="font-size: 16px; color: #666; margin-bottom: 30px;">抱歉，您访问的文档不存在</p>
                    <a href="/docs.php" class="el-button el-button--primary">返回文档列表</a>
                </div>
            </div>
            <footer class="el-footer">
                <div class="el-footer__content">
                    <?php if ($copyright): ?><p><?php echo $copyright; ?></p><?php endif; ?>
                    <?php if ($icpNumber): ?><p><a href="https://beian.miit.gov.cn/" target="_blank"><?php echo htmlspecialchars($icpNumber); ?></a></p><?php endif; ?>
                </div>
            </footer>
        </body>
        </html>
        <?php
        exit;
    }
    
    // 显示单个文档
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title><?php echo htmlspecialchars($doc['title']); ?> - 文档</title>
        <?php if ($siteKeywords): ?><meta name="keywords" content="<?php echo htmlspecialchars($siteKeywords); ?>"><?php endif; ?>
        <?php if ($siteDescription): ?><meta name="description" content="<?php echo htmlspecialchars($siteDescription); ?>"><?php endif; ?>
        <link rel="stylesheet" href="/assets/css/tailwind.css">
        <link rel="stylesheet" href="/assets/css/font-awesome.min.css">
        <link rel="stylesheet" href="/assets/css/style.css">
        <style>
            .doc-wrapper { display: flex; gap: 24px; }
            .doc-sidebar { flex: 0 0 260px; order: 1; }
            .doc-main { flex: 1; min-width: 0; order: 2; }
            
            .sidebar-card { 
                background: white; 
                border-radius: 12px; 
                box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
                overflow: hidden; 
                margin-bottom: 24px;
                border: 1px solid #f0f0f0;
            }
            .sidebar-card-header { 
                background: linear-gradient(135deg, var(--el-color-primary) 0%, #5a67d8 100%);
                color: white; 
                padding: 16px 18px; 
                font-weight: 600;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .sidebar-card-body { padding: 0; }
            .sidebar-item { 
                padding: 12px 16px; 
                border-bottom: 1px solid #f5f5f5; 
                transition: all 0.3s ease;
                position: relative;
            }
            .sidebar-item:last-child { border-bottom: none; }
            .sidebar-item a { 
                color: #555; 
                text-decoration: none; 
                display: block; 
                font-size: 13px; 
                line-height: 1.6;
                transition: all 0.3s ease;
            }
            .sidebar-item a:hover { 
                color: var(--el-color-primary);
                padding-left: 8px;
            }
            .sidebar-item.active { background: rgba(102, 126, 234, 0.05); }
            .sidebar-item.active a { 
                color: var(--el-color-primary); 
                font-weight: 600;
            }
            .sidebar-item.active::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 3px;
                background: var(--el-color-primary);
            }
            
            .doc-content { max-width: 100%; }
            .doc-content h2 { 
                margin-top: 32px; 
                margin-bottom: 16px; 
                font-size: 22px; 
                font-weight: 700;
                color: #222;
                padding-bottom: 12px;
                border-bottom: 2px solid #f0f0f0;
            }
            .doc-content h3 { 
                margin-top: 24px; 
                margin-bottom: 12px; 
                font-size: 18px; 
                font-weight: 600;
                color: #333;
            }
            .doc-content h4 {
                margin-top: 16px;
                margin-bottom: 10px;
                font-size: 15px;
                font-weight: 600;
                color: #444;
            }
            .doc-content p { 
                margin-bottom: 14px; 
                line-height: 1.8;
                color: #555;
            }
            .doc-content code { 
                background: #f5f5f5; 
                padding: 3px 8px; 
                border-radius: 4px; 
                font-family: 'Courier New', monospace;
                font-size: 13px;
                color: #d63384;
            }
            .doc-content pre { 
                background: #2d2d2d; 
                color: #f8f8f2;
                padding: 16px; 
                border-radius: 8px; 
                overflow-x: auto; 
                margin-bottom: 16px;
                border: 1px solid #3e3d32;
            }
            .doc-content pre code { 
                background: none; 
                padding: 0;
                color: #f8f8f2;
            }
            .doc-content ul, .doc-content ol { 
                margin-left: 24px; 
                margin-bottom: 16px;
            }
            .doc-content li { 
                margin-bottom: 10px;
                line-height: 1.7;
                color: #555;
            }
            .doc-content blockquote { 
                border-left: 4px solid var(--el-color-primary); 
                padding: 12px 16px;
                margin-left: 0; 
                margin-bottom: 16px; 
                color: #666;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .doc-content table { 
                border-collapse: collapse; 
                width: 100%; 
                margin: 16px 0;
                border: 1px solid #ddd;
                border-radius: 6px;
                overflow: hidden;
            }
            .doc-content table th, .doc-content table td { 
                border: 1px solid #ddd; 
                padding: 12px 14px; 
                text-align: left;
            }
            .doc-content table th { 
                background: #f5f5f5;
                font-weight: 600;
                color: #333;
            }
            .doc-content table tr:hover { background: #fafafa; }
            .doc-content img { 
                max-width: 100%; 
                height: auto; 
                margin: 16px 0;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
            }
            .doc-meta { 
                display: flex; 
                gap: 24px; 
                font-size: 13px; 
                color: rgba(255,255,255,0.85);
                margin-bottom: 0;
            }
            .doc-meta span {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            
            .breadcrumb {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                margin-bottom: 20px;
                color: #999;
            }
            .breadcrumb a {
                color: var(--el-color-primary);
                text-decoration: none;
                transition: all 0.3s ease;
            }
            .breadcrumb a:hover {
                text-decoration: underline;
            }
            
            @media (max-width: 768px) {
                .doc-wrapper { 
                    flex-direction: column; 
                    gap: 16px;
                }
                .doc-sidebar { 
                    flex: 1; 
                    order: 3;
                }
                .doc-main { 
                    order: 1;
                }
            }
        </style>
    </head>
    <body>
        <header class="el-header">
            <div class="el-header__content">
                <a href="index.php" class="el-header__logo">
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
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 48px 24px; border-radius: 12px; margin-bottom: 32px; text-align: center; box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);">
                <h1 style="font-size: 32px; margin-bottom: 12px; font-weight: 700;"><?php echo htmlspecialchars($doc['title']); ?></h1>
                <div class="doc-meta">
                    <span><i class="fa fa-calendar"></i> <?php echo date('Y-m-d', strtotime($doc['create_time'])); ?></span>
                    <span><i class="fa fa-eye"></i> <?php echo $doc['views']; ?> 次浏览</span>
                </div>
            </div>
            
            <div class="breadcrumb">
                <a href="/docs.php"><i class="fa fa-home"></i> 文档首页</a>
                <span>/</span>
                <span><?php echo htmlspecialchars($doc['title']); ?></span>
            </div>
            
            <div class="doc-wrapper">
                <!-- 左侧边栏 -->
                <div class="doc-sidebar">
                    <?php
                    // 获取同分类的其他文章
                    $relatedDocs = $docs->getAllDocs($doc['category'], 1);
                    if (!empty($relatedDocs)):
                    ?>
                    <div class="sidebar-card">
                        <div class="sidebar-card-header">
                            <i class="fa fa-list"></i> 本分类文章
                        </div>
                        <div class="sidebar-card-body">
                            <?php foreach ($relatedDocs as $relatedDoc): ?>
                            <div class="sidebar-item <?php echo $relatedDoc['id'] == $doc['id'] ? 'active' : ''; ?>">
                                <a href="/docs/<?php echo urlencode($relatedDoc['slug']); ?>.html" title="<?php echo htmlspecialchars($relatedDoc['title']); ?>">
                                    <?php echo htmlspecialchars(strlen($relatedDoc['title']) > 20 ? substr($relatedDoc['title'], 0, 20) . '...' : $relatedDoc['title']); ?>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- 主内容区 -->
                <div class="doc-main">
                    <div class="doc-content" style="background: white; padding: 32px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #f0f0f0; margin-bottom: 32px;">
                        <?php 
                        $content = Markdown::parse($doc['content']);
                        // 检查是否包含未解析的占位符
                        if (preg_match('/(PLACEHOLDER\d+_?|INLINECODE\d+|CODEBLOCK\d+)/', $content)) {
                            echo '<div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #856404;">';
                            echo '<strong>⚠️ 文档内容异常</strong><br>';
                            echo '文档内容包含未解析的占位符。这通常是因为：<br>';
                            echo '• 文档编辑器保存时出现问题<br>';
                            echo '• 文档从其他系统导入时格式不兼容<br>';
                            echo '• 数据库中的内容被损坏<br><br>';
                            echo '建议：请联系管理员重新编辑并保存此文档。';
                            echo '</div>';
                        }
                        echo $content;
                        ?>
                    </div>
                    
                    <div style="text-align: center; margin-bottom: 32px;">
                        <a href="/docs.php" class="el-button el-button--primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;">
                            <i class="fa fa-arrow-left"></i> 返回文档列表
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <footer class="el-footer">
            <div class="el-footer__content">
                <?php if ($copyright): ?><p><?php echo $copyright; ?></p><?php endif; ?>
                <?php if ($icpNumber): ?><p><a href="https://beian.miit.gov.cn/" target="_blank"><?php echo htmlspecialchars($icpNumber); ?></a></p><?php endif; ?>
            </div>
        </footer>
    </body>
    </html>
    <?php
} else {
    // 显示文档列表
    $allDocs = $docs->getAllDocs(null, 1);
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>文档中心 - <?php echo htmlspecialchars($siteTitle); ?></title>
        <?php if ($siteKeywords): ?><meta name="keywords" content="<?php echo htmlspecialchars($siteKeywords); ?>"><?php endif; ?>
        <?php if ($siteDescription): ?><meta name="description" content="<?php echo htmlspecialchars($siteDescription); ?>"><?php endif; ?>
        <link rel="stylesheet" href="/assets/css/tailwind.css">
        <link rel="stylesheet" href="/assets/css/font-awesome.min.css">
        <link rel="stylesheet" href="/assets/css/style.css">
        <style>
            .docs-header {
                background: linear-gradient(135deg, rgba(59, 130, 246, 0.8) 0%, rgba(99, 102, 241, 0.8) 100%), url('/uploads/footer-bg.jpg') center/cover;
                color: white;
                padding: 40px 24px;
                border-radius: 12px;
                margin-bottom: 32px;
                text-align: center;
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
                position: relative;
                overflow: hidden;
            }
            .docs-header::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-image: 
                    radial-gradient(circle at 20% 50%, rgba(255,255,255,0.08) 0%, transparent 50%),
                    radial-gradient(circle at 80% 80%, rgba(255,255,255,0.03) 0%, transparent 50%);
                pointer-events: none;
            }
            .docs-header::after {
                content: '';
                position: absolute;
                top: -60%;
                right: -15%;
                width: 300px;
                height: 300px;
                background: rgba(255,255,255,0.03);
                border-radius: 50%;
                pointer-events: none;
            }
            .docs-header-content {
                position: relative;
                z-index: 1;
            }
            .docs-header-icon {
                width: 50px;
                height: 50px;
                background: rgba(255,255,255,0.15);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 12px;
                font-size: 28px;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.2);
            }
            .docs-header h1 {
                font-size: 28px;
                margin-bottom: 8px;
                font-weight: 700;
                letter-spacing: -0.3px;
            }
            .docs-header p {
                font-size: 14px;
                opacity: 0.9;
                margin: 0;
                font-weight: 300;
                letter-spacing: 0.2px;
            }
            .docs-header-stats {
                display: flex;
                justify-content: center;
                gap: 30px;
                margin-top: 16px;
                padding-top: 16px;
                border-top: 1px solid rgba(255,255,255,0.15);
            }
            .docs-header-stat {
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .docs-header-stat-value {
                font-size: 18px;
                font-weight: 700;
                margin-bottom: 2px;
            }
            .docs-header-stat-label {
                font-size: 11px;
                opacity: 0.8;
                text-transform: uppercase;
                letter-spacing: 0.8px;
            }
            
            .docs-search {
                position: relative;
                width: 100%;
                max-width: 500px;
                margin: 0 auto 40px;
            }
            .docs-search input {
                width: 100%;
                padding: 14px 18px 14px 44px;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                font-size: 14px;
                transition: all 0.3s ease;
                box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            }
            .docs-search input:focus {
                outline: none;
                border-color: var(--el-color-primary);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            }
            .docs-search button {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: #999;
                cursor: pointer;
                font-size: 16px;
                transition: all 0.3s ease;
            }
            .docs-search input:focus ~ button {
                color: var(--el-color-primary);
            }
            
            .tabs {
                display: flex;
                gap: 12px;
                margin-bottom: 32px;
                flex-wrap: wrap;
                justify-content: center;
            }
            .tab-btn {
                padding: 10px 24px;
                background: white;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.3s ease;
                color: #555;
            }
            .tab-btn:hover {
                border-color: var(--el-color-primary);
                color: var(--el-color-primary);
            }
            .tab-btn.active {
                background: var(--el-color-primary);
                color: white;
                border-color: var(--el-color-primary);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            }
            
            .docs-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                gap: 24px;
                margin-bottom: 40px;
            }
            
            .doc-card {
                background: white;
                padding: 24px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                transition: all 0.3s ease;
                cursor: pointer;
                text-decoration: none;
                color: inherit;
                display: flex;
                flex-direction: column;
                border: 1px solid #f0f0f0;
                position: relative;
                overflow: hidden;
            }
            .doc-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--el-color-primary), #5a67d8);
                transform: scaleX(0);
                transform-origin: left;
                transition: transform 0.3s ease;
            }
            .doc-card:hover {
                transform: translateY(-6px);
                box-shadow: 0 12px 24px rgba(0,0,0,0.12);
                border-color: var(--el-color-primary);
            }
            .doc-card:hover::before {
                transform: scaleX(1);
            }
            
            .doc-card-icon {
                width: 48px;
                height: 48px;
                background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(90, 103, 216, 0.1));
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 16px;
                font-size: 24px;
                color: var(--el-color-primary);
            }
            
            .doc-card h3 {
                font-size: 18px;
                margin-bottom: 10px;
                color: #222;
                font-weight: 600;
                line-height: 1.4;
            }
            .doc-card p {
                color: #666;
                font-size: 13px;
                margin-bottom: 16px;
                line-height: 1.6;
                flex: 1;
            }
            .doc-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 12px;
                color: #999;
                padding-top: 12px;
                border-top: 1px solid #f5f5f5;
            }
            .doc-category {
                display: inline-block;
                padding: 4px 10px;
                background: rgba(102, 126, 234, 0.1);
                color: var(--el-color-primary);
                border-radius: 4px;
                font-weight: 500;
            }
            
            .empty {
                text-align: center;
                padding: 60px 20px;
                color: #999;
                grid-column: 1 / -1;
            }
            .empty i {
                font-size: 64px;
                margin-bottom: 20px;
                opacity: 0.3;
                display: block;
            }
            .empty p {
                font-size: 16px;
                margin: 0;
            }
            
            @media (max-width: 768px) {
                  .docs-header {
                      padding: 30px 16px;
                  }
                  .docs-header h1 {
                      font-size: 22px;
                  }
                  .docs-header p {
                      font-size: 13px;
                  }
                  .docs-header-icon {
                      width: 40px;
                      height: 40px;
                      font-size: 22px;
                  }
                  .docs-header-stats {
                      gap: 20px;
                      margin-top: 12px;
                      padding-top: 12px;
                  }
                  .docs-header-stat-value {
                      font-size: 16px;
                  }
                  .docs-header-stat-label {
                      font-size: 10px;
                  }
                 .docs-grid {
                     grid-template-columns: 1fr;
                     gap: 16px;
                 }
                 .tabs {
                     justify-content: flex-start;
                     overflow-x: auto;
                     padding-bottom: 8px;
                 }
             }
        </style>
    </head>
    <body>
        <header class="el-header">
            <div class="el-header__content">
                <a href="index.php" class="el-header__logo">
                    <i class="fa fa-mobile-alt"></i>
                    <?php echo htmlspecialchars($frontendTitle); ?>
                </a>
                <nav class="el-header__nav">
                    <a href="/app.php"><i class="fa fa-th-large"></i> 应用中心</a>
                    <a href="/kami.php"><i class="fa fa-gift"></i> 领取卡密</a>
                    <a href="/docs.php"><i class="fa fa-book"></i> 文档</a>
                </nav>
            </div>
        </header>

        <div class="el-container">
            <div class="docs-header">
                <div class="docs-header-content">
                    <div class="docs-header-icon">
                        <i class="fa fa-book"></i>
                    </div>
                    <h1>文档中心</h1>
                    <p>查看帮助文档、使用指南和常见问题</p>
                    <div class="docs-header-stats">
                        <div class="docs-header-stat">
                            <div class="docs-header-stat-value"><?php echo count($allDocs); ?></div>
                            <div class="docs-header-stat-label">篇文档</div>
                        </div>
                        <div class="docs-header-stat">
                            <div class="docs-header-stat-value"><?php echo array_sum(array_column($allDocs, 'views')); ?></div>
                            <div class="docs-header-stat-label">次浏览</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="docs-search">
                <input type="text" id="search-input" placeholder="搜索文档..." onkeyup="searchDocs()">
                <button><i class="fa fa-search"></i></button>
            </div>

            <div class="tabs">
                <button class="tab-btn active" onclick="filterDocs('all')">全部</button>
                <button class="tab-btn" onclick="filterDocs('help')">帮助</button>
                <button class="tab-btn" onclick="filterDocs('docs')">文档</button>
                <button class="tab-btn" onclick="filterDocs('guide')">指南</button>
            </div>

            <div class="docs-grid" id="docs-container">
                <?php if (empty($allDocs)): ?>
                    <div class="empty">
                        <i class="fa fa-inbox"></i>
                        <p>暂无文档</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($allDocs as $doc): ?>
                    <a href="/docs/<?php echo urlencode($doc['slug']); ?>.html" class="doc-card" data-category="<?php echo htmlspecialchars($doc['category']); ?>" data-title="<?php echo htmlspecialchars($doc['title']); ?>">
                        <div class="doc-card-icon">
                            <?php 
                            $icons = ['help' => 'fa-question-circle', 'docs' => 'fa-book', 'guide' => 'fa-compass'];
                            $icon = $icons[$doc['category']] ?? 'fa-file-alt';
                            ?>
                            <i class="fa <?php echo $icon; ?>"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($doc['title']); ?></h3>
                        <p><?php echo htmlspecialchars($doc['description'] ?? substr(strip_tags($doc['content']), 0, 100)); ?></p>
                        <div class="doc-meta">
                            <span class="doc-category"><?php echo ['help' => '帮助', 'docs' => '文档', 'guide' => '指南'][$doc['category']] ?? $doc['category']; ?></span>
                            <span><i class="fa fa-eye"></i> <?php echo $doc['views']; ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <footer class="el-footer">
            <div class="el-footer__content">
                <?php if ($copyright): ?><p><?php echo $copyright; ?></p><?php endif; ?>
                <?php if ($icpNumber): ?><p><a href="https://beian.miit.gov.cn/" target="_blank"><?php echo htmlspecialchars($icpNumber); ?></a></p><?php endif; ?>
            </div>
        </footer>

        <script>
        function filterDocs(category) {
            const cards = document.querySelectorAll('.doc-card');
            const buttons = document.querySelectorAll('.tab-btn');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            cards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        function searchDocs() {
            const searchInput = document.getElementById('search-input').value.toLowerCase();
            const cards = document.querySelectorAll('.doc-card');
            
            cards.forEach(card => {
                const title = card.dataset.title.toLowerCase();
                if (title.includes(searchInput)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        </script>
    <script>console.log("\n %c Ning.Si软件源管理系统 %c by Ning.Si | https://github.com/XooUooX/iOS_AppStore ", "color:#fff;background:#409EFF;padding:5px 0;", "color:#eee;background:#444;padding:5px 10px;");</script>
</body>
</html>
    <?php
}
?>
