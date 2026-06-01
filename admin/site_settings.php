<?php
/**
 * 后台管理 - 网站设置
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/common.php';

checkAdminLogin();

$db = Database::getInstance();

// 保存设置
if (isset($_POST['action']) && $_POST['action'] === 'save') {
    $settings = [
        'site_title' => $_POST['site_title'] ?? '',
        'site_keywords' => $_POST['site_keywords'] ?? '',
        'site_description' => $_POST['site_description'] ?? '',
        'frontend_title' => $_POST['frontend_title'] ?? '',
        'icp_number' => $_POST['icp_number'] ?? '',
        'copyright' => $_POST['copyright'] ?? '',
        'announcement' => $_POST['announcement'] ?? '',
        'announcement_enabled' => $_POST['announcement_enabled'] ?? '0',
        'show_shortcut_after_activate' => $_POST['show_shortcut_after_activate'] ?? '0'
    ];
    
    $debugMessage = '';
    
    foreach ($settings as $name => $value) {
        try {
            $db->query("INSERT INTO " . $db->getTable('config') . " (name, `group`, title, tip, type, value, content, rule, extend, createtime, updatetime) VALUES (?, 'system', ?, '', 'string', ?, '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE value = VALUES(value), updatetime = VALUES(updatetime)", [
                $name,
                $name,
                $value
            ]);
        } catch (Exception $e) {
            $debugMessage .= "{$name} 错误: " . $e->getMessage() . "<br>";
        }
    }
    
    logOperation('修改网站设置', '更新网站配置');
    
    if (empty($debugMessage)) {
        redirectAfterPost('site_settings.php', '网站设置已保存', 'success');
    } else {
        redirectAfterPost('site_settings.php', '部分设置保存异常: ' . $debugMessage, 'warning');
    }
}

// 获取设置
$settings = [];
$settingNames = ['site_title', 'site_keywords', 'site_description', 'frontend_title', 'icp_number', 'copyright', 'announcement', 'announcement_enabled', 'show_shortcut_after_activate'];
$placeholders = implode(',', array_fill(0, count($settingNames), '?'));
$rows = $db->fetchAll("SELECT name, value FROM " . $db->getTable('config') . " WHERE name IN ($placeholders)", $settingNames);
foreach ($rows as $row) {
    $settings[$row['name']] = $row['value'];
}

renderHeader('网站设置', 'site_settings');
?>

<?php echo getPrgMessage(); ?>

<div class="panel">
    <div class="panel-header">
        <h2>网站设置</h2>
    </div>
    <div class="panel-body">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            
            <div class="form-group">
                <label>网站标题 (Title)</label>
                <input type="text" name="site_title" class="form-control" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>" placeholder="网站标题，显示在浏览器标签页">
                <small style="color: var(--gray-500);">用于浏览器标签页标题和SEO</small>
            </div>
            
            <div class="form-group">
                <label>网站关键词 (Keywords)</label>
                <input type="text" name="site_keywords" class="form-control" value="<?php echo htmlspecialchars($settings['site_keywords'] ?? ''); ?>" placeholder="关键词1, 关键词2, 关键词3">
                <small style="color: var(--gray-500);">多个关键词用逗号分隔</small>
            </div>
            
            <div class="form-group">
                <label>网站描述 (Description)</label>
                <textarea name="site_description" class="form-control" rows="3" placeholder="网站描述信息"><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
                <small style="color: var(--gray-500);">网站简介，用于搜索引擎展示</small>
            </div>
            
            <div class="form-group">
                <label>前台标题</label>
                <input type="text" name="frontend_title" class="form-control" value="<?php echo htmlspecialchars($settings['frontend_title'] ?? ''); ?>" placeholder="前台页面显示的大标题">
                <small style="color: var(--gray-500);">显示在网站前台页面顶部</small>
            </div>
            
            <div class="form-group">
                <label>备案号 (ICP)</label>
                <input type="text" name="icp_number" class="form-control" value="<?php echo htmlspecialchars($settings['icp_number'] ?? ''); ?>" placeholder="如: 京ICP备12345678号">
                <small style="color: var(--gray-500);">显示在网站底部</small>
            </div>
            
            <div class="form-group">
                <label>版权信息</label>
                <input type="text" name="copyright" class="form-control" value="<?php echo htmlspecialchars($settings['copyright'] ?? ''); ?>" placeholder="如: © 2024 公司名称. All rights reserved.">
                <small style="color: var(--gray-500);">显示在网站底部版权位置</small>
            </div>
            
            <div class="form-group" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--gray-200);">
                <label>一键添加全能签、轻松签开关</label>
                <label class="switch">
                    <input type="checkbox" name="show_shortcut_after_activate" value="1" <?php echo ($settings['show_shortcut_after_activate'] ?? '1') === '1' ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
                <small style="color: var(--gray-500); display: block; margin-top: 8px;">关闭后，快捷入口将只在卡密激活成功后显示</small>
            </div>
            
            <div class="form-group" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--gray-200);">
                <label>启用前端公告</label>
                <label class="switch">
                    <input type="checkbox" name="announcement_enabled" value="1" <?php echo ($settings['announcement_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
                <small style="color: var(--gray-500); display: block; margin-top: 8px;">开启后将在前台首页显示公告弹窗</small>
            </div>
            
            <div class="form-group">
                <label>公告内容 (支持HTML)</label>
                <textarea name="announcement" class="form-control" rows="6" placeholder="在此输入公告内容，支持HTML标签"><?php echo htmlspecialchars($settings['announcement'] ?? ''); ?></textarea>
                <small style="color: var(--gray-500);">支持HTML标签，如 &lt;p&gt;, &lt;a&gt;, &lt;strong&gt; 等</small>
            </div>
            
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary">保存设置</button>
            </div>
        </form>
    </div>
</div>

<?php renderFooter(); ?>
