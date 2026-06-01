<?php
/**
 * 后台管理 - 今日API请求
 * 显示今日API请求记录，支持IP拉黑操作
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/common.php';

checkAdminLogin();

$db = Database::getInstance();
$message = '';

// 添加IP到黑名单
if (isset($_POST['action']) && $_POST['action'] === 'blacklist_ip') {
    $ip = trim($_POST['ip'] ?? '');
    $reason = trim($_POST['reason'] ?? 'API请求异常');
    
    if (!empty($ip)) {
        // 获取当前禁止IP配置
        $configResult = $db->fetch("SELECT value FROM " . $db->getTable('config') . " WHERE name = 'forbiddenip'");
        $forbiddenIps = $configResult ? $configResult['value'] : '';
        
        // 检查IP是否已存在
        $ipList = array_filter(array_map('trim', explode("\n", $forbiddenIps)));
        if (!in_array($ip, $ipList)) {
            $ipList[] = $ip;
            $newValue = implode("\n", $ipList);
            
            // 更新配置
            $db->update('config', [
                'value' => $newValue,
                'updatetime' => time()
            ], "name = ?", ['forbiddenip']);
            
            $message = showMessage("IP {$ip} 已成功加入黑名单");
            logOperation('IP黑名单', "添加IP: {$ip}, 原因: {$reason}");
        } else {
            $message = showMessage("IP {$ip} 已在黑名单中", 'error');
        }
    } else {
        $message = showMessage('IP地址不能为空', 'error');
    }
}

// 批量拉黑IP
if (isset($_POST['action']) && $_POST['action'] === 'batch_blacklist') {
    $ips = $_POST['ips'] ?? [];
    $reason = trim($_POST['reason'] ?? 'API请求异常');
    
    if (!empty($ips)) {
        // 获取当前禁止IP配置
        $configResult = $db->fetch("SELECT value FROM " . $db->getTable('config') . " WHERE name = 'forbiddenip'");
        $forbiddenIps = $configResult ? $configResult['value'] : '';
        $ipList = array_filter(array_map('trim', explode("\n", $forbiddenIps)));
        
        $addedCount = 0;
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if (!empty($ip) && !in_array($ip, $ipList)) {
                $ipList[] = $ip;
                $addedCount++;
            }
        }
        
        if ($addedCount > 0) {
            $newValue = implode("\n", $ipList);
            $db->update('config', [
                'value' => $newValue,
                'updatetime' => time()
            ], "name = ?", ['forbiddenip']);
            
            $message = showMessage("成功添加 {$addedCount} 个IP到黑名单");
            logOperation('批量IP黑名单', "添加IP数: {$addedCount}");
        } else {
            $message = showMessage('选中的IP都已在黑名单中', 'error');
        }
    }
}

// 获取今日统计
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');

$stats = [];

// 今日总请求数
$result = $db->fetch("SELECT COUNT(*) as total FROM " . $db->getTable('api_logs') . " WHERE create_time BETWEEN ? AND ?", [$todayStart, $todayEnd]);
$stats['total'] = $result['total'];

// 今日成功请求
$result = $db->fetch("SELECT COUNT(*) as success FROM " . $db->getTable('api_logs') . " WHERE status = 1 AND create_time BETWEEN ? AND ?", [$todayStart, $todayEnd]);
$stats['success'] = $result['success'];

// 今日失败请求
$result = $db->fetch("SELECT COUNT(*) as fail FROM " . $db->getTable('api_logs') . " WHERE status = 0 AND create_time BETWEEN ? AND ?", [$todayStart, $todayEnd]);
$stats['fail'] = $result['fail'];

// 今日独立IP数
$result = $db->fetch("SELECT COUNT(DISTINCT ip_address) as unique_ips FROM " . $db->getTable('api_logs') . " WHERE create_time BETWEEN ? AND ?", [$todayStart, $todayEnd]);
$stats['unique_ips'] = $result['unique_ips'];

// 获取列表
$page = intval($_GET['page'] ?? 1);
$keyword = $_GET['keyword'] ?? '';
$status = $_GET['status'] ?? '';

$where = "WHERE create_time BETWEEN ? AND ?";
$params = [$todayStart, $todayEnd];

if (!empty($keyword)) {
    $where .= " AND (ip_address LIKE ? OR device_id LIKE ? OR api_name LIKE ?)";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
}

if ($status !== '') {
    $where .= " AND status = ?";
    $params[] = intval($status);
}

// 获取总数
$countResult = $db->fetch("SELECT COUNT(*) as total FROM " . $db->getTable('api_logs') . " {$where}", $params);
$total = $countResult['total'];

// 获取列表
$limit = 20;
$offset = ($page - 1) * $limit;
$sql = "SELECT * FROM " . $db->getTable('api_logs') . " {$where} ORDER BY create_time DESC LIMIT ?, ?";
$params[] = (int)$offset;
$params[] = (int)$limit;
$list = $db->fetchAll($sql, $params);

// 获取当前黑名单IP列表
$configResult = $db->fetch("SELECT value FROM " . $db->getTable('config') . " WHERE name = 'forbiddenip'");
$blacklistedIps = array_filter(array_map('trim', explode("\n", $configResult ? $configResult['value'] : '')));

renderHeader('API请求', 'api_request');
?>

<?php echo $message; ?>

<!-- 统计卡片 -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-5.5-1a4 4 0 1 1 0 8 4 4 0 0 1 0-8z"/><path d="M3.3 13H1v8a2 2 0 0 0 2 2h14v-2.3"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['total']; ?></h3>
            <p>今日总请求</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['success']; ?></h3>
            <p>成功请求</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['fail']; ?></h3>
            <p>失败请求</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['unique_ips']; ?></h3>
            <p>独立IP数</p>
        </div>
    </div>
</div>

        <!-- API请求列表 -->
        <div class="panel">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <h3>今日API请求记录 (共 <?php echo $total; ?> 条)</h3>
                <form method="GET" class="search-box" style="display: flex; gap: 12px; flex: 1; align-items: center; max-width: 600px;">
                    <input type="text" name="keyword" class="form-control" placeholder="搜索IP/设备ID/API名称" value="<?php echo htmlspecialchars($keyword); ?>" style="flex: 1; min-width: 200px;">
                    <select name="status" class="form-control" style="width: 110px; flex-shrink: 0;">
                        <option value="">全部状态</option>
                        <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>成功</option>
                        <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>失败</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="flex-shrink: 0;">搜索</button>
                    <a href="api_request.php" class="btn btn-secondary" style="flex-shrink: 0;">重置</a>
                </form>
            </div>
            <div class="panel-body">
                <form method="POST" id="batchForm">
                    <input type="hidden" name="action" value="batch_blacklist">
                    <div style="margin-bottom: 15px;">
                        <button type="button" class="btn btn-danger" onclick="batchBlacklist()">批量拉黑选中IP</button>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="checkbox-col"><input type="checkbox" id="checkAll"></th>
                                <th>ID</th>
                                <th>API名称</th>
                                <th>设备ID</th>
                                <th>IP地址</th>
                                <th>状态</th>
                                <th>请求时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($list as $item): 
                                $isBlacklisted = in_array($item['ip_address'], $blacklistedIps);
                            ?>
                            <tr>
                                <td>
                                    <?php if (!$isBlacklisted): ?>
                                    <input type="checkbox" name="ips[]" value="<?php echo $item['ip_address']; ?>">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $item['id']; ?></td>
                                <td><code><?php echo htmlspecialchars($item['api_name']); ?></code></td>
                                <td><span class="truncate" title="<?php echo htmlspecialchars($item['device_id']); ?>"><?php echo htmlspecialchars($item['device_id'] ?: '-'); ?></span></td>
                                <td>
                                    <span class="<?php echo $isBlacklisted ? 'ip-blacklisted' : 'ip-normal'; ?>" title="<?php echo $isBlacklisted ? '已在黑名单中' : ''; ?>">
                                        <?php echo htmlspecialchars($item['ip_address'] ?: '-'); ?>
                                        <?php if ($isBlacklisted): ?> 🚫<?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['status'] == 1): ?>
                                    <span class="status-badge status-success">成功</span>
                                    <?php else: ?>
                                    <span class="status-badge status-fail">失败</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $item['create_time']; ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="showDetail(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['api_name']); ?>', '<?php echo htmlspecialchars($item['device_id']); ?>', '<?php echo htmlspecialchars($item['card_key']); ?>', '<?php echo htmlspecialchars(addslashes($item['request_data'])); ?>', '<?php echo htmlspecialchars(addslashes($item['response_data'])); ?>', '<?php echo $item['create_time']; ?>')">详情</button>
                                        <?php if (!$isBlacklisted && $item['ip_address']): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="blacklistIp('<?php echo $item['ip_address']; ?>')">拉黑</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

                <?php 
                $pages = ceil($total / $limit);
                if ($pages > 1): 
                ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&keyword=<?php echo urlencode($keyword); ?>&status=<?php echo $status; ?>" class="page-link">上一页</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page-2); $i <= min($pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&keyword=<?php echo urlencode($keyword); ?>&status=<?php echo $status; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&keyword=<?php echo urlencode($keyword); ?>&status=<?php echo $status; ?>" class="page-link">下一页</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 拉黑IP弹窗 -->
    <div id="blacklistModal" class="modal-overlay" style="display: none;">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3>拉黑IP地址</h3>
                <button type="button" class="btn btn-secondary" onclick="closeModal()" style="padding: 6px 12px;">✕</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="blacklist_ip">
                    <input type="hidden" name="ip" id="blacklistIp">
                    <div class="form-group">
                        <label>IP地址</label>
                        <input type="text" id="displayIp" class="form-control" disabled>
                    </div>
                    <div class="form-group">
                        <label>拉黑原因</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="请输入拉黑原因（可选）">API请求异常</textarea>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-danger">确认拉黑</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 详情弹窗 -->
    <div id="detailModal" class="modal-overlay" style="display: none;">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3>请求详情</h3>
                <button type="button" class="btn btn-secondary" onclick="closeDetailModal()" style="padding: 6px 12px;">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>API名称</label>
                    <input type="text" id="detailApiName" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label>设备ID</label>
                    <input type="text" id="detailDeviceId" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label>卡密</label>
                    <input type="text" id="detailCardKey" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label>请求数据</label>
                    <textarea id="detailRequest" class="form-control" rows="4" disabled></textarea>
                </div>
                <div class="form-group">
                    <label>响应数据</label>
                    <textarea id="detailResponse" class="form-control" rows="4" disabled></textarea>
                </div>
                <div class="form-group">
                    <label>请求时间</label>
                    <input type="text" id="detailTime" class="form-control" disabled>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeDetailModal()">关闭</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // 全选/取消全选
    document.getElementById('checkAll').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('input[name="ips[]"]');
        checkboxes.forEach(function(cb) {
            cb.checked = document.getElementById('checkAll').checked;
        });
    });

    // 拉黑IP弹窗
    function blacklistIp(ip) {
        document.getElementById('blacklistIp').value = ip;
        document.getElementById('displayIp').value = ip;
        document.getElementById('blacklistModal').style.display = 'flex';
    }

    // 关闭弹窗
    function closeModal() {
        document.getElementById('blacklistModal').style.display = 'none';
    }

    // 显示详情
    function showDetail(id, apiName, deviceId, cardKey, requestData, responseData, time) {
        document.getElementById('detailApiName').value = apiName || '-';
        document.getElementById('detailDeviceId').value = deviceId || '-';
        document.getElementById('detailCardKey').value = cardKey || '-';
        document.getElementById('detailRequest').value = requestData || '-';
        document.getElementById('detailResponse').value = responseData || '-';
        document.getElementById('detailTime').value = time || '-';
        document.getElementById('detailModal').style.display = 'flex';
    }

    // 关闭详情弹窗
    function closeDetailModal() {
        document.getElementById('detailModal').style.display = 'none';
    }

    // 批量拉黑
    function batchBlacklist() {
        var checkboxes = document.querySelectorAll('input[name="ips[]"]:checked');
        if (checkboxes.length === 0) {
            alert('请选择要拉黑的IP');
            return;
        }
        if (confirm('确定要批量拉黑选中的 ' + checkboxes.length + ' 个IP吗？')) {
            document.getElementById('batchForm').submit();
        }
    }

    // 点击弹窗外部关闭
    window.onclick = function(event) {
        var blacklistModal = document.getElementById('blacklistModal');
        var detailModal = document.getElementById('detailModal');
        if (event.target === blacklistModal) {
            blacklistModal.style.display = 'none';
        }
        if (event.target === detailModal) {
            detailModal.style.display = 'none';
        }
    }
    </script>

<style>
/* API请求日志页响应式优化 */
@media (max-width: 768px) {
    /* panel-header 垂直布局 */
    .panel-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px !important;
    }
    
    .panel-header h2,
    .panel-header h3 {
        font-size: 16px;
        margin-bottom: 0;
    }
    
    /* 右侧搜索区域垂直堆叠 */
    .panel-header > div {
        flex-direction: column !important;
        width: 100% !important;
        gap: 10px !important;
    }
    
    .panel-header form {
        flex-direction: column !important;
        width: 100% !important;
        gap: 8px !important;
    }
    
    .panel-header form input[type="text"],
    .panel-header form select {
        width: 100% !important;
        flex: none !important;
        box-sizing: border-box;
    }
    
    .panel-header form button,
    .panel-header .btn {
        width: 100% !important;
    }
    
    /* 表格横向滚动 */
    .table-responsive,
    .panel-body {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    .table-responsive table,
    .panel-body table {
        min-width: 900px;
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
    
    /* 统计卡片 */
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}
</style>

<?php renderFooter(); ?>
