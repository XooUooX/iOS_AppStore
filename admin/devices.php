<?php
/**
 * 后台管理 - 设备管理
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/../includes/class.device.php';
require_once __DIR__ . '/common.php';

checkAdminLogin();

$device = new Device();
$message = '';

// 禁用设备
if (isset($_GET['disable'])) {
    $deviceId = $_GET['disable'];
    $device->disable($deviceId);
    $message = showMessage('设备已禁用');
    logOperation('禁用设备', "禁用设备: {$deviceId}");
}

// 启用设备
if (isset($_GET['enable'])) {
    $deviceId = $_GET['enable'];
    $device->enable($deviceId);
    $message = showMessage('设备已启用');
    logOperation('启用设备', "启用设备: {$deviceId}");
}

// 删除设备
if (isset($_GET['delete'])) {
    $deviceId = $_GET['delete'];
    $device->delete($deviceId);
    $message = showMessage('设备已删除');
    logOperation('删除设备', "删除设备: {$deviceId}");
}

// 拉黑设备
if (isset($_POST['action']) && $_POST['action'] === 'blacklist') {
    $deviceId = $_POST['device_id'] ?? '';
    $duration = intval($_POST['duration'] ?? 0);  // 0 = 永久，>0 = 天数
    $reason = $_POST['reason'] ?? '从设备管理页面拉黑';
    
    if (!empty($deviceId)) {
        try {
            $db = Database::getInstance();
            // 检查是否已在黑名单中
            $exists = $db->fetch("SELECT id FROM " . $db->getTable('blacklist') . " WHERE value = ? AND type = 1 AND (expire_time IS NULL OR expire_time > NOW())", [$deviceId]);
            
            if (!$exists) {
                // 计算过期时间
                $expireTime = null;
                if ($duration > 0) {
                    $expireTime = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
                }
                
                // 添加到黑名单
                $db->insert('blacklist', [
                    'type' => 1,  // 1 = 设备ID
                    'value' => $deviceId,
                    'reason' => $reason,
                    'expire_time' => $expireTime,
                    'create_time' => date('Y-m-d H:i:s')
                ]);
                
                $durationText = $duration === 0 ? '永久' : "{$duration}天";
                $message = showMessage("设备已拉黑（{$durationText}）", 'success');
                logOperation('拉黑设备', "拉黑设备: {$deviceId}, 时长: {$durationText}");
            } else {
                $message = showMessage('该设备已在黑名单中', 'warning');
            }
        } catch (Exception $e) {
            $message = showMessage('拉黑失败: ' . $e->getMessage(), 'danger');
        }
    }
}

// 获取列表
$page = intval($_GET['page'] ?? 1);
$status = $_GET['status'] ?? '';
$keyword = $_GET['keyword'] ?? '';
$list = $device->getList($page, 20, $status !== '' ? intval($status) : null, $keyword);

// 获取统计数据
$stats = $device->getStatistics();

renderHeader('设备管理', 'devices');
?>

<?php echo $message; ?>

<!-- 统计 -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['total']; ?></h3>
            <p>总设备数</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['active']; ?></h3>
            <p>正常设备</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['disabled']; ?></h3>
            <p>禁用设备</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['expired']; ?></h3>
            <p>过期设备</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 17"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['today']; ?></h3>
            <p>今日新增</p>
        </div>
    </div>
</div>

<!-- 设备列表 -->
<div class="panel">
    <div class="panel-header"><h2>设备列表</h2></div>
    <div class="panel-body">
        <div class="search-box">
            <form method="GET" style="display: flex; gap: 12px; flex: 1; align-items: center;">
                <select name="status" class="form-control" style="width: 110px; flex-shrink: 0;">
                    <option value="">全部状态</option>
                    <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>禁用</option>
                    <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>正常</option>
                    <option value="2" <?php echo $status === '2' ? 'selected' : ''; ?>>过期</option>
                </select>
                <input type="text" name="keyword" class="form-control" placeholder="搜索设备ID/卡密" value="<?php echo htmlspecialchars($keyword); ?>" style="flex: 1; min-width: 300px;">
                <button type="submit" class="btn btn-primary" style="flex-shrink: 0;">搜索</button>
            </form>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>设备ID</th>
                        <th>卡密</th>
                        <th>绑定时间</th>
                        <th>到期时间</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list['list'] as $item): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars(substr($item['device_id'], 0, 12) . '...'); ?></code></td>
                        <td><code><?php echo htmlspecialchars(substr($item['card_key'], 0, 10) . '...'); ?></code></td>
                        <td><?php echo $item['bind_time'] ? date('Y-m-d', strtotime($item['bind_time'])) : '-'; ?></td>
                        <td><?php echo $item['expire_time'] ? date('Y-m-d', strtotime($item['expire_time'])) : '-'; ?></td>
                        <td>
                            <?php
                            $db = Database::getInstance();
                            $isBlacklisted = $db->fetch("SELECT id FROM " . $db->getTable('blacklist') . " WHERE value = ? AND type = 1 AND (expire_time IS NULL OR expire_time > NOW())", [$item['device_id']]);
                            
                            $statusClass = '';
                            $statusText = '';
                            
                            if ($isBlacklisted) {
                                $statusClass = 'badge-danger';
                                $statusText = '拉黑';
                            } elseif ($item['status'] == 0) {
                                $statusClass = 'badge-danger';
                                $statusText = '已禁用';
                            } elseif ($item['status'] == 1) {
                                $statusClass = 'badge-success';
                                $statusText = '正常';
                            } elseif ($item['status'] == 2) {
                                $statusClass = 'badge-warning';
                                $statusText = '已过期';
                            }
                            ?>
                            <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info" onclick="showDeviceDetails(<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE)); ?>)">详情</button>
                            <button type="button" class="btn btn-sm btn-warning" onclick="showBlacklistModal(<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE)); ?>)">拉黑</button>
                            <?php if ($item['status'] == 0): ?>
                            <a href="?enable=<?php echo urlencode($item['device_id']); ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-success">启用</a>
                            <?php else: ?>
                            <a href="?disable=<?php echo urlencode($item['device_id']); ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-secondary" onclick="return confirm('确定禁用此设备？')">禁用</a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo urlencode($item['device_id']); ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除此设备？此操作不可恢复！')">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php echo pagination($list['page'], $list['pages'], "?status={$status}&keyword=" . urlencode($keyword)); ?>
    </div>
</div>

<style>
/* 设备列表页响应式优化 */
@media (max-width: 768px) {
    /* panel-header */
    .panel-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px !important;
    }
    
    .panel-header h2 {
        font-size: 16px;
        margin-bottom: 0;
    }
    
    /* 搜索框区域垂直堆叠 */
    .search-box form {
        flex-direction: column !important;
        gap: 10px !important;
        width: 100% !important;
    }
    .search-box form select,
    .search-box form input[type="text"] {
        width: 100% !important;
        flex: none !important;
        box-sizing: border-box;
        min-width: auto !important;
    }
    .search-box form button {
        width: 100% !important;
    }
    
    /* 表格横向滚动 */
    .table-container,
    .panel-body {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    .table-container table,
    .panel-body table {
        min-width: 800px;
    }
    
    /* 操作按钮 */
    .action-btns {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    .action-btns .btn {
        padding: 4px 8px;
        font-size: 12px;
    }
}
</style>

<!-- 设备详情弹窗 -->
<div id="deviceDetailsModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>设备详情</h3>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('deviceDetailsModal').style.display='none'" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- 左列 -->
                <div>
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">设备ID</label>
                        <div style="padding: 8px 12px; background: var(--gray-50); border-radius: 6px; word-break: break-all; font-family: monospace; font-size: 13px;">
                            <span id="detailDeviceId"></span>
                        </div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">使用的卡密</label>
                        <div style="padding: 8px 12px; background: var(--gray-50); border-radius: 6px; word-break: break-all; font-family: monospace; font-size: 13px;">
                            <span id="detailCardKey"></span>
                        </div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">IP地址</label>
                        <div style="padding: 8px 12px; background: var(--gray-50); border-radius: 6px;">
                            <span id="detailIpAddress"></span>
                        </div>
                    </div>
                </div>
                
                <!-- 右列 -->
                <div>
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">激活时间</label>
                        <div style="padding: 8px 12px; background: var(--gray-50); border-radius: 6px;">
                            <span id="detailCreateTime"></span>
                        </div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">绑定时间</label>
                        <div style="padding: 8px 12px; background: var(--gray-50); border-radius: 6px;">
                            <span id="detailBindTime"></span>
                        </div>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">到期时间</label>
                        <div style="padding: 8px 12px; background: var(--gray-50); border-radius: 6px;">
                            <span id="detailExpireTime"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('deviceDetailsModal').style.display='none'">关闭</button>
        </div>
    </div>
</div>

<!-- 拉黑设备弹窗 -->
<div id="blacklistModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>拉黑设备</h3>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('blacklistModal').style.display='none'" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">设备ID</label>
                <div style="padding: 8px 12px; background: var(--gray-50); border-radius: 6px; word-break: break-all; font-family: monospace; font-size: 13px;">
                    <span id="blacklistDeviceId"></span>
                </div>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 8px;">拉黑时长</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="blacklist_duration" value="permanent" checked>
                        <span>永久拉黑</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="blacklist_duration" value="custom">
                        <span>自定义时长</span>
                    </label>
                </div>
            </div>
            
            <div id="customDurationDiv" style="margin-bottom: 16px; display: none;">
                <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 8px;">拉黑天数</label>
                <input type="number" id="blacklistDays" class="form-control" placeholder="输入天数" min="1" value="7" style="width: 100%;">
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 8px;">拉黑原因</label>
                <textarea id="blacklistReason" class="form-control" placeholder="输入拉黑原因（可选）" style="width: 100%; min-height: 80px; padding: 8px 12px; border: 1px solid var(--gray-300); border-radius: 6px; font-family: inherit;"></textarea>
            </div>
        </div>
        <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('blacklistModal').style.display='none'">取消</button>
            <button type="button" class="btn btn-danger" onclick="confirmBlacklist()">确认拉黑</button>
        </div>
    </div>
</div>

<script>
let currentDevice = null;

function showDeviceDetails(device) {
    currentDevice = device;
    
    // 填充详情信息
    document.getElementById('detailDeviceId').textContent = device.device_id || '-';
    document.getElementById('detailCardKey').textContent = device.card_key || '-';
    document.getElementById('detailIpAddress').textContent = device.ip_address || '-';
    document.getElementById('detailCreateTime').textContent = device.create_time || '-';
    document.getElementById('detailBindTime').textContent = device.bind_time || '-';
    document.getElementById('detailExpireTime').textContent = device.expire_time || '-';
    
    // 显示弹窗
    document.getElementById('deviceDetailsModal').style.display = 'flex';
}

function showBlacklistModal(device) {
    currentDevice = device;
    document.getElementById('blacklistDeviceId').textContent = device.device_id || '-';
    document.getElementById('blacklistDays').value = '7';
    document.getElementById('blacklistReason').value = '';
    document.querySelector('input[name="blacklist_duration"][value="permanent"]').checked = true;
    document.getElementById('customDurationDiv').style.display = 'none';
    
    document.getElementById('blacklistModal').style.display = 'flex';
}

function confirmBlacklist() {
    if (!currentDevice) return;
    
    const duration = document.querySelector('input[name="blacklist_duration"]:checked').value;
    const days = parseInt(document.getElementById('blacklistDays').value) || 7;
    const reason = document.getElementById('blacklistReason').value || '从设备管理页面拉黑';
    
    if (duration === 'custom' && days <= 0) {
        alert('请输入有效的天数');
        return;
    }
    
    if (confirm('确定要拉黑此设备吗？')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'blacklist';
        
        const deviceIdInput = document.createElement('input');
        deviceIdInput.type = 'hidden';
        deviceIdInput.name = 'device_id';
        deviceIdInput.value = currentDevice.device_id;
        
        const durationInput = document.createElement('input');
        durationInput.type = 'hidden';
        durationInput.name = 'duration';
        durationInput.value = duration === 'permanent' ? '0' : days;
        
        const reasonInput = document.createElement('input');
        reasonInput.type = 'hidden';
        reasonInput.name = 'reason';
        reasonInput.value = reason;
        
        form.appendChild(actionInput);
        form.appendChild(deviceIdInput);
        form.appendChild(durationInput);
        form.appendChild(reasonInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// 监听拉黑时长选择
document.addEventListener('change', function(e) {
    if (e.target.name === 'blacklist_duration') {
        const customDiv = document.getElementById('customDurationDiv');
        if (e.target.value === 'custom') {
            customDiv.style.display = 'block';
        } else {
            customDiv.style.display = 'none';
        }
    }
});

// 点击弹窗外部关闭
document.getElementById('deviceDetailsModal').addEventListener('click', function(event) {
    if (event.target === this) {
        this.style.display = 'none';
    }
});

document.getElementById('blacklistModal').addEventListener('click', function(event) {
    if (event.target === this) {
        this.style.display = 'none';
    }
});
</script>

<?php renderFooter(); ?>
