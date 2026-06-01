<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/../includes/class.cardkey.php';
require_once __DIR__ . '/../includes/class.device.php';
require_once __DIR__ . '/common.php';

checkAdminLogin();

$stats = getDashboardStats();

// 获取最新设备列表，关联 card_key_batch_items 表获取 device_name
$db = Database::getInstance();
$recentDevices = $db->fetchAll("SELECT d.*, c.device_name as batch_device_name FROM " . $db->getTable('devices') . " d LEFT JOIN " . $db->getTable('card_key_batch_items') . " c ON d.device_id = c.device_id ORDER BY d.create_time DESC LIMIT 10");

// 获取最新操作记录
$operationLogs = $db->fetchAll("SELECT * FROM " . $db->getTable('operation_logs') . " ORDER BY create_time DESC LIMIT 5");

// 获取系统信息
$systemInfo = [
    'php_version' => PHP_VERSION,
    'mysql_version' => $db->getPdo()->query('SELECT VERSION()')->fetchColumn(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
];

// 获取程序名称
$licenseInfo = [
    'product_name' => 'Ning.Si软件源管理系统'
];

// 获取当前版本号
$currentVersion = getCurrentVersion();

function getCurrentVersion() {
    $versionFile = __DIR__ . '/../version.txt';
    if (file_exists($versionFile)) {
        return trim(file_get_contents($versionFile));
    }
    return '1.0.0';
}

$defaultStatCardSettings = [
    'device_total' => true,
    'device_active' => true,
    'today_activation' => true,
    'expiring_soon' => true,
    'card_total' => true,
    'app_total' => true,
    'monitor_total' => true,
    'api_today' => true,
    'ip_blacklist_count' => true,
    'blacklist_total' => true,
];

$statCardSettings = getAdminSetting('stat_card_settings', $defaultStatCardSettings);

if (isset($_POST['action']) && $_POST['action'] === 'update_stat_settings') {
    $statCardSettings = [
        'device_total' => isset($_POST['device_total']),
        'device_active' => isset($_POST['device_active']),
        'today_activation' => isset($_POST['today_activation']),
        'expiring_soon' => isset($_POST['expiring_soon']),
        'card_total' => isset($_POST['card_total']),
        'app_total' => isset($_POST['app_total']),
        'monitor_total' => isset($_POST['monitor_total']),
        'api_today' => isset($_POST['api_today']),
        'ip_blacklist_count' => isset($_POST['ip_blacklist_count']),
        'blacklist_total' => isset($_POST['blacklist_total']),
    ];
    saveAdminSetting('stat_card_settings', $statCardSettings);
    redirectAfterPost('index.php', '统计卡片显示设置已保存（永久生效）', 'success');
}

// 确保 CSRF token 存在
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

renderHeader('概览', 'index');
?>

<?php echo getPrgMessage(); ?>

<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

<!-- 统计卡片设置按钮 -->
<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
    <button class="btn btn-secondary" onclick="openStatSettingsModal()" style="padding: 8px 16px; font-size: 14px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline; margin-right: 6px;"><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6M4.22 4.22l4.24 4.24m5.08 5.08l4.24 4.24M1 12h6m6 0h6M4.22 19.78l4.24-4.24m5.08-5.08l4.24-4.24"/></svg>
        统计卡片设置
    </button>
</div>

<!-- 统计卡片设置模态框 -->
<div id="statSettingsModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>统计卡片显示设置</h3>
            <button type="button" class="btn btn-secondary" onclick="closeStatSettingsModal()" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body">
            <form id="stat-settings-form" method="POST" action="">
                <input type="hidden" name="action" value="update_stat_settings">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="device_total" <?php echo $statCardSettings['device_total'] ? 'checked' : ''; ?>>
                        <span>设备总数</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="device_active" <?php echo $statCardSettings['device_active'] ? 'checked' : ''; ?>>
                        <span>活跃设备</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="today_activation" <?php echo $statCardSettings['today_activation'] ? 'checked' : ''; ?>>
                        <span>今日激活</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="expiring_soon" <?php echo $statCardSettings['expiring_soon'] ? 'checked' : ''; ?>>
                        <span>即将过期</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="card_total" <?php echo $statCardSettings['card_total'] ? 'checked' : ''; ?>>
                        <span>卡密总数</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="app_total" <?php echo $statCardSettings['app_total'] ? 'checked' : ''; ?>>
                        <span>应用总数</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="monitor_total" <?php echo $statCardSettings['monitor_total'] ? 'checked' : ''; ?>>
                        <span>监控记录</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="api_today" <?php echo $statCardSettings['api_today'] ? 'checked' : ''; ?>>
                        <span>API请求</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="ip_blacklist_count" <?php echo $statCardSettings['ip_blacklist_count'] ? 'checked' : ''; ?>>
                        <span>IP黑名单</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="blacklist_total" <?php echo $statCardSettings['blacklist_total'] ? 'checked' : ''; ?>>
                        <span>设备黑名单</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn btn-secondary" onclick="closeStatSettingsModal()">取消</button>
            <button type="button" class="btn btn-primary" onclick="saveStatSettings()">保存设置</button>
        </div>
    </div>
</div>

<!-- 统计卡片 -->
<div class="stats-grid">
    <?php if ($statCardSettings['device_total']): ?>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">设备总数</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
        </div>
        <div class="stat-value"><?php echo $stats['device_total']; ?></div>
        <div class="stat-footer">
            <span class="stat-change">较上月 0</span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($statCardSettings['device_active']): ?>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">活跃设备</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-value"><?php echo $stats['device_active']; ?></div>
        <div class="stat-footer">
            <span class="stat-change">较上月 0</span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($statCardSettings['today_activation']): ?>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">今日激活</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 17"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <div class="stat-value"><?php echo $stats['today_activation']; ?></div>
        <div class="stat-footer">
            <span class="stat-change">较上月 0</span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($statCardSettings['expiring_soon']): ?>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">即将过期</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4m-6-2h12"/></svg>
        </div>
        <div class="stat-value"><?php echo $stats['expiring_soon']; ?></div>
        <div class="stat-footer">
            <span class="stat-change">较上月 0</span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($statCardSettings['card_total']): ?>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">卡密总数</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        </div>
        <div class="stat-value"><?php echo $stats['card_total']; ?></div>
        <div class="stat-footer">
            <span class="stat-change">较上月 0</span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($statCardSettings['app_total']): ?>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">应用总数</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        </div>
        <div class="stat-value"><?php echo $stats['app_total']; ?></div>
        <div class="stat-footer">
            <span class="stat-change">较上月 0</span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($statCardSettings['monitor_total']): ?>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">监控记录</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="2" y1="17" x2="22" y2="17"/><path d="M20 21H4a2 2 0 0 1-2-2v-1h20v1a2 2 0 0 1-2 2z"/></svg>
        </div>
        <div class="stat-value"><?php echo $stats['monitor_total']; ?></div>
        <div class="stat-footer">
            <span class="stat-change">较上月 0</span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($statCardSettings['api_today']): ?>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">API请求</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        </div>
        <div class="stat-value"><?php echo $stats['api_today']; ?></div>
        <div class="stat-footer">
            <span class="stat-change">较上月 0</span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($statCardSettings['ip_blacklist_count']): ?>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">IP黑名单</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </div>
        <div class="stat-value"><?php echo $stats['ip_blacklist_count']; ?></div>
        <div class="stat-footer">
            <span class="stat-change">较上月 0</span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($statCardSettings['blacklist_total']): ?>
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">设备黑名单</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </div>
        <div class="stat-value"><?php echo $stats['blacklist_total']; ?></div>
        <div class="stat-footer">
            <span class="stat-change">较上月 0</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- 最新设备 -->
<div class="panel">
    <div class="panel-header">
        <h2>最新激活设备</h2>
        <a href="devices.php" class="btn btn-sm btn-secondary">查看全部 →</a>
    </div>
    <div class="panel-body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>设备ID</th>
                        <th>设备名称</th>
                        <th>绑定卡密</th>
                        <th>到期时间</th>
                        <th>状态</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDevices as $device): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($device['device_id'], 0, 20) . '...'); ?></td>
                        <td><?php echo htmlspecialchars($device['batch_device_name'] ?: ($device['device_name'] ?: '未命名')); ?></td>
                        <td><?php echo htmlspecialchars($device['card_key']); ?></td>
                        <td><?php echo $device['expire_time']; ?></td>
                        <td>
                            <?php 
                            $statusClass = '';
                            $statusText = '';
                            switch ($device['status']) {
                                case 0: $statusClass = 'badge-danger'; $statusText = '禁用'; break;
                                case 1: $statusClass = 'badge-success'; $statusText = '正常'; break;
                                case 2: $statusClass = 'badge-warning'; $statusText = '过期'; break;
                            }
                            ?>
                            <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 操作记录 & 系统信息 -->
<div class="two-column-grid">
    <!-- 操作记录（左侧） -->
    <div class="panel">
        <div class="panel-header">
            <h2>操作记录</h2>
        </div>
        <div class="panel-body">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>操作类型</th>
                            <th>操作人</th>
                            <th>IP地址</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($operationLogs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['operator'] ?: '系统'); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td><?php echo $log['create_time']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 系统信息（右侧） -->
    <div class="panel">
        <div class="panel-header">
            <h2>系统信息</h2>
        </div>
        <div class="panel-body">
            <div class="form-row">
                <div class="form-group">
                    <label>程序名称</label>
                    <div class="form-control" style="background: var(--gray-50);"><?php echo htmlspecialchars($licenseInfo['product_name']); ?></div>
                </div>
                <div class="form-group">
                    <label>当前版本</label>
                    <div class="form-control" style="background: var(--gray-50); display: flex; justify-content: space-between; align-items: center;">
                        <span><?php echo htmlspecialchars($currentVersion); ?></span><span class="badge badge-success" style="margin-left: 8px;">最新</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>作者QQ</label>
                    <div class="form-control" style="background: var(--gray-50);">881450</div>
                </div>
                <div class="form-group">
                    <label>PHP版本</label>
                    <div class="form-control" style="background: var(--gray-50);"><?php echo $systemInfo['php_version']; ?></div>
                </div>
                <div class="form-group">
                    <label>MySQL版本</label>
                    <div class="form-control" style="background: var(--gray-50);"><?php echo $systemInfo['mysql_version']; ?></div>
                </div>
                <div class="form-group">
                    <label>Nginx版本</label>
                    <div class="form-control" style="background: var(--gray-50);"><?php echo $systemInfo['server_software']; ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/index.js"></script>

<?php renderFooter(); ?>
