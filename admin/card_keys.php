<?php
/**
 * 后台管理 - 卡密管理
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/../includes/class.cardkey.php';
require_once __DIR__ . '/common.php';

checkAdminLogin();

$cardKey = new CardKey();
$message = '';

// 批量生成卡密
if (isset($_POST['action']) && $_POST['action'] === 'generate') {
    $count = intval($_POST['count'] ?? 10);
    $type = intval($_POST['type'] ?? 1);
    $expireDays = intval($_POST['expire_days'] ?? 30);
    $remark = $_POST['remark'] ?? '';
    $prefix = $_POST['prefix'] ?? '';
    
    if ($count > 0 && $count <= 1000) {
        $result = $cardKey->batchGenerate($count, $type, $expireDays, $remark, $prefix);
        if ($result['success']) {
            $message = showMessage("成功生成 {$result['generated']} 张卡密");
            logOperation('生成卡密', "生成 {$result['generated']} 张卡密");
            // 保存生成的卡密到 session，用于弹窗显示
            $_SESSION['generated_keys'] = $result['keys'];
            $_SESSION['generated_count'] = $result['generated'];
        } else {
            $message = showMessage($result['message'], 'danger');
        }
    } else {
        $message = showMessage('生成数量必须在1-1000之间', 'danger');
    }
}

// 删除卡密
if (isset($_GET['delete'])) {
    $keyToDelete = $_GET['delete'];
    $cardKey->delete($keyToDelete);
    $message = showMessage('卡密已删除');
    logOperation('删除卡密', "删除卡密: {$keyToDelete}");
}

// 禁用卡密
if (isset($_GET['disable'])) {
    $keyToDisable = $_GET['disable'];
    $cardKey->disable($keyToDisable);
    $message = showMessage('卡密已禁用');
    logOperation('禁用卡密', "禁用卡密: {$keyToDisable}");
}

// 导出卡密
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    $exportStatus = isset($_GET['export_status']) ? intval($_GET['export_status']) : null;
    $exportIds = isset($_GET['export_ids']) ? array_filter(explode(',', $_GET['export_ids'])) : [];
    $exportFormat = $_GET['format'] ?? 'csv'; // 默认CSV格式
    
    // 构建查询条件
    $where = '1=1';
    $params = [];
    
    if ($exportType === 'selected' && !empty($exportIds)) {
        // 直接拼接卡密列表到SQL中（卡密是安全的字符串）
        $escapedKeys = array_map(function($key) { 
            return "'" . addslashes($key) . "'"; 
        }, $exportIds);
        $where .= " AND card_key IN (" . implode(',', $escapedKeys) . ")";
    } elseif ($exportType === 'filtered' && $exportStatus !== null) {
        $where .= " AND status = ?";
        $params[] = $exportStatus;
    } elseif ($exportType === 'unused') {
        $where .= " AND status = 0";
    } elseif ($exportType === 'used') {
        $where .= " AND status = 1";
    }
    
    // 添加搜索条件
    if (!empty($keyword)) {
        $where .= " AND (card_key LIKE ? OR remark LIKE ? OR bind_device_id LIKE ?)";
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }
    
    // 获取数据
    $db = Database::getInstance();
    $sql = "SELECT card_key, card_type, expire_days, status, bind_device_id, create_time, remark FROM " . $db->getTable('card_keys') . " WHERE $where ORDER BY id DESC";
    $exportData = $db->fetchAll($sql, $params);
    
    // 调试：如果没有数据，输出错误信息
    if (empty($exportData)) {
        error_log("Export debug: SQL=$sql, Params=" . json_encode($params) . ", exportIds=" . json_encode($exportIds));
        // 输出调试信息到导出文件
        echo "Debug: exportType=$exportType, exportIds=" . json_encode($exportIds) . "\n";
        echo "Debug: SQL=$sql\n";
        echo "Debug: Params=" . json_encode($params) . "\n";
    }
    
    if ($exportFormat === 'txt') {
        // 生成TXT格式 - 只导出卡密，每行一个
        $filename = 'card_keys_' . date('Ymd_His') . '.txt';
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        foreach ($exportData as $row) {
            echo $row['card_key'] . "\n";
        }
    } else {
        // 生成CSV格式
        $filename = 'card_keys_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        // 添加BOM以支持Excel中文
        echo "\xEF\xBB\xBF";
        
        // 输出表头
        echo "卡密,类型,有效期(天),状态,绑定设备,创建时间,备注\n";
        
        // 输出数据
        foreach ($exportData as $row) {
            $statusText = $row['status'] == 0 ? '未使用' : ($row['status'] == 1 ? '已使用' : '已禁用');
            $typeText = CardKey::getTypeName($row['card_type']);
            echo sprintf("%s,%s,%s,%s,%s,%s,%s\n",
                $row['card_key'],
                $typeText,
                $row['expire_days'],
                $statusText,
                $row['bind_device_id'] ?: '-',
                $row['create_time'],
                str_replace([",", "\n", "\r"], ["，", " ", ""], $row['remark'] ?: '-')
            );
        }
    }
    exit;
}
$page = intval($_GET['page'] ?? 1);
$status = $_GET['status'] ?? '';
$keyword = $_GET['keyword'] ?? '';
$list = $cardKey->getList($page, 20, $status !== '' ? intval($status) : null, $keyword);

renderHeader('卡密管理', 'card_keys');
?>

<?php echo $message; ?>

<!-- 生成卡密结果弹窗 -->
<?php if (isset($_SESSION['generated_keys']) && !empty($_SESSION['generated_keys'])): ?>
<div id="generatedKeysModal" class="modal-overlay" style="display: flex;">
    <div class="modal" style="max-width: 600px; max-height: 80vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
            <h3><i class="fa fa-check-circle" style="color: var(--el-color-success); margin-right: 8px;"></i>卡密生成成功</h3>
            <button type="button" class="btn btn-secondary" onclick="closeGeneratedKeysModal()" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body" style="overflow-y: auto; flex: 1;">
            <div class="alert alert-success" style="margin-bottom: 16px;">
                <i class="fa fa-info-circle"></i> 成功生成 <strong><?php echo $_SESSION['generated_count']; ?></strong> 张卡密
            </div>
            <div class="form-group">
                <label style="display: flex; justify-content: space-between; align-items: center;">
                    <span>生成的卡密列表</span>
                    <button type="button" class="btn btn-sm btn-primary" onclick="copyAllKeys()">
                        <i class="fa fa-copy"></i> 复制全部
                    </button>
                </label>
                <textarea id="generatedKeysTextarea" class="form-control" readonly style="min-height: 200px; font-family: monospace; font-size: 13px; line-height: 1.8; resize: vertical;"><?php echo implode("\n", $_SESSION['generated_keys']); ?></textarea>
            </div>
        </div>
        <div class="modal-footer" style="flex-shrink: 0; display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-primary" onclick="copyAllKeys()">
                <i class="fa fa-copy"></i> 复制全部卡密
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeGeneratedKeysModal()">关闭</button>
        </div>
    </div>
</div>
<?php 
    // 清除 session 中的生成数据
    unset($_SESSION['generated_keys']);
    unset($_SESSION['generated_count']);
endif; 
?>

<!-- 卡密列表 -->
<div class="panel">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>卡密列表</h2>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-secondary" onclick="openExportModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline; margin-right: 6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                导出
            </button>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('generateModal').style.display='flex'">+ 批量生成卡密</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="search-box">
            <form method="GET" style="display: flex; gap: 12px; flex: 1; align-items: center;">
                <select name="status" class="form-control" style="width: 110px; flex-shrink: 0;">
                    <option value="">全部状态</option>
                    <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>未使用</option>
                    <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>已使用</option>
                    <option value="2" <?php echo $status === '2' ? 'selected' : ''; ?>>已禁用</option>
                </select>
                <input type="text" name="keyword" class="form-control" placeholder="搜索卡密/备注/设备" value="<?php echo htmlspecialchars($keyword); ?>" style="flex: 1; min-width: 300px;">
                <button type="submit" class="btn btn-primary" style="flex-shrink: 0;">搜索</button>
            </form>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll()"></th>
                        <th>卡密</th>
                        <th>类型</th>
                        <th>有效期</th>
                        <th>状态</th>
                        <th>绑定设备</th>
                        <th>创建时间</th>
                        <th>备注</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list['list'] as $item): ?>
                    <tr>
                        <td><input type="checkbox" name="card_select[]" value="<?php echo htmlspecialchars($item['card_key']); ?>" class="card-checkbox"></td>
                        <td><code><?php echo htmlspecialchars($item['card_key']); ?></code></td>
                        <td><?php echo CardKey::getTypeName($item['card_type']); ?></td>
                        <td><?php echo $item['expire_days']; ?>天</td>
                        <td>
                            <?php
                            $statusClass = '';
                            switch ($item['status']) {
                                case 0: $statusClass = 'badge-secondary'; break;
                                case 1: $statusClass = 'badge-success'; break;
                                case 2: $statusClass = 'badge-danger'; break;
                            }
                            ?>
                            <span class="badge <?php echo $statusClass; ?>"><?php echo CardKey::getStatusName($item['status']); ?></span>
                        </td>
                        <td><?php echo $item['bind_device_id'] ? htmlspecialchars(substr($item['bind_device_id'], 0, 15) . '...') : '-'; ?></td>
                        <td><?php echo $item['create_time']; ?></td>
                        <td><?php echo htmlspecialchars($item['remark'] ?: '-'); ?></td>
                        <td>
                            <?php if ($item['status'] != 2): ?>
                            <a href="?disable=<?php echo urlencode($item['card_key']); ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-secondary" onclick="return confirm('确定禁用此卡密？')">禁用</a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo urlencode($item['card_key']); ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除此卡密？此操作不可恢复！')">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php echo pagination($list['page'], $list['pages'], "?status={$status}&keyword=" . urlencode($keyword)); ?>
    </div>
</div>

<!-- 批量生成卡密弹窗 -->
<div id="generateModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>批量生成卡密</h3>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('generateModal').style.display='none'" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="generate">
                <div class="form-group">
                    <label>生成数量</label>
                    <input type="number" name="count" class="form-control" value="10" min="1" max="1000">
                </div>
                <div class="form-group">
                    <label>卡密类型</label>
                    <select name="type" class="form-control" id="cardType" onchange="updateCardType()">
                        <option value="1">月卡</option>
                        <option value="2">季卡</option>
                        <option value="3">年卡</option>
                        <option value="4">永久</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>有效期（天）</label>
                    <input type="number" id="expireInput" name="expire_days" class="form-control" value="30" min="1" style="flex: 1;">
                    <small style="color: var(--gray-500);">输入天数：月卡=30天，季卡=90天，年卡=365天，永久=36500天</small>
                </div>
                <div class="form-group">
                    <label>卡密前缀</label>
                    <input type="text" name="prefix" class="form-control" placeholder="可选，如: VIP-" style="text-transform: uppercase;">
                    <small style="color: var(--gray-500);">生成的卡密将以此开头，如: VIP-ABC123</small>
                </div>
                <div class="form-group">
                    <label>备注</label>
                    <input type="text" name="remark" class="form-control" placeholder="可选">
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">生成卡密</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('generateModal').style.display='none'">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 导出卡密模态框 -->
<div class="modal-overlay" id="exportModal" style="display: none;">
    <div class="modal">
        <form method="GET" id="exportForm">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--gray-200);">
                <h3 style="margin: 0; font-size: 18px; font-weight: 600;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline; margin-right: 8px; vertical-align: middle;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    导出卡密
                </h3>
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('exportModal').style.display='none'" style="padding: 4px 12px; font-size: 18px; line-height: 1;">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>导出范围</label>
                    <select name="export" id="exportScope" class="form-control">
                        <option value="all">全部卡密</option>
                        <option value="unused">未使用卡密</option>
                        <option value="used">已使用卡密</option>
                        <option value="selected">选中卡密</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>导出格式</label>
                    <div style="display: flex; gap: 20px; margin-top: 8px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="format" value="csv" checked style="width: 18px; height: 18px;">
                            <span>CSV 格式 (带详细信息)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="format" value="txt" style="width: 18px; height: 18px;">
                            <span>TXT 格式 (仅卡密)</span>
                        </label>
                    </div>
                </div>
                <input type="hidden" name="export_ids" id="exportIds" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('exportModal').style.display='none'">取消</button>
                <button type="submit" class="btn btn-primary">开始导出</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateCardType() {
    var type = document.getElementById('cardType').value;
    var input = document.getElementById('expireInput');
    
    if (type === '1') {
        input.value = '30';   // 月卡
    } else if (type === '2') {
        input.value = '90';   // 季卡
    } else if (type === '3') {
        input.value = '365';  // 年卡
    } else if (type === '4') {
        input.value = '36500'; // 永久
    }
}

// 全选/取消全选
function toggleSelectAll() {
    var selectAll = document.getElementById('selectAll');
    var checkboxes = document.querySelectorAll('.card-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = selectAll.checked;
    });
}

// 显示/隐藏导出菜单
function toggleExportMenu() {
    var menu = document.getElementById('exportMenu');
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
}

// 导出选中项
function exportSelected() {
    var checkboxes = document.querySelectorAll('.card-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('请先选择要导出的卡密');
        return;
    }
    var selectedKeys = [];
    checkboxes.forEach(function(cb) {
        selectedKeys.push(cb.value);
    });
    document.getElementById('exportIds').value = selectedKeys.join(',');
    document.getElementById('exportScope').value = 'selected';
    document.getElementById('exportModal').style.display = 'flex';
}

// 当导出范围改变时，自动获取选中的卡密
function handleExportScopeChange() {
    var scope = document.getElementById('exportScope').value;
    if (scope === 'selected') {
        var checkboxes = document.querySelectorAll('.card-checkbox:checked');
        if (checkboxes.length === 0) {
            alert('请先选择要导出的卡密');
            document.getElementById('exportScope').value = 'all';
            return;
        }
        var selectedKeys = [];
        checkboxes.forEach(function(cb) {
            selectedKeys.push(cb.value);
        });
        document.getElementById('exportIds').value = selectedKeys.join(',');
    }
}

// 打开导出弹窗时，自动获取选中的卡密
function openExportModal() {
    var checkboxes = document.querySelectorAll('.card-checkbox:checked');
    if (checkboxes.length > 0) {
        var selectedKeys = [];
        checkboxes.forEach(function(cb) {
            selectedKeys.push(cb.value);
        });
        document.getElementById('exportIds').value = selectedKeys.join(',');
        document.getElementById('exportScope').value = 'selected';
    } else {
        document.getElementById('exportIds').value = '';
        document.getElementById('exportScope').value = 'all';
    }
    document.getElementById('exportModal').style.display = 'flex';
}

// 点击页面其他地方关闭导出菜单
window.onclick = function(event) {
    var modal = document.getElementById('generateModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
    
    var exportMenu = document.getElementById('exportMenu');
    if (exportMenu && !event.target.closest('.dropdown')) {
        exportMenu.style.display = 'none';
    }
}

// 关闭生成卡密结果弹窗
function closeGeneratedKeysModal() {
    document.getElementById('generatedKeysModal').style.display = 'none';
}

// 复制单个卡密
function copySingleKey(key) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(key).then(function() {
            showCopyToast('卡密已复制');
        }).catch(function() {
            fallbackCopy(key);
        });
    } else {
        fallbackCopy(key);
    }
}

// 复制全部卡密
function copyAllKeys() {
    var textarea = document.getElementById('generatedKeysTextarea');
    var allKeys = textarea.value;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(allKeys).then(function() {
            showCopyToast('全部卡密已复制');
        }).catch(function() {
            fallbackCopyAll(textarea);
        });
    } else {
        fallbackCopyAll(textarea);
    }
}

// 降级复制方案 - 单个
function fallbackCopy(text) {
    var input = document.createElement('textarea');
    input.value = text;
    input.style.position = 'fixed';
    input.style.left = '-9999px';
    document.body.appendChild(input);
    input.select();
    try {
        document.execCommand('copy');
        showCopyToast('卡密已复制');
    } catch (err) {
        showCopyToast('复制失败，请手动复制', 'error');
    }
    document.body.removeChild(input);
}

// 降级复制方案 - 全部
function fallbackCopyAll(textarea) {
    textarea.select();
    try {
        document.execCommand('copy');
        showCopyToast('全部卡密已复制');
    } catch (err) {
        showCopyToast('复制失败，请手动复制', 'error');
    }
}

// 显示复制提示
function showCopyToast(message, type) {
    type = type || 'success';
    var toast = document.createElement('div');
    toast.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: ' + (type === 'success' ? 'var(--el-color-success)' : 'var(--el-color-danger)') + '; color: white; padding: 12px 24px; border-radius: 8px; font-size: 14px; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.15); animation: slideDown 0.3s ease;';
    toast.innerHTML = '<i class="fa fa-' + (type === 'success' ? 'check' : 'times') + '" style="margin-right: 8px;"></i>' + message;
    document.body.appendChild(toast);
    
    setTimeout(function() {
        toast.style.animation = 'slideUp 0.3s ease';
        setTimeout(function() {
            document.body.removeChild(toast);
        }, 300);
    }, 2000);
}

// 添加动画样式
var style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        to { opacity: 1; transform: translateX(-50%) translateY(0); }
    }
    @keyframes slideUp {
        from { opacity: 1; transform: translateX(-50%) translateY(0); }
        to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
    }
`;
document.head.appendChild(style);
</script>

<style>
/* 卡密管理页响应式优化 */
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
    
    /* 按钮全宽 */
    .panel-header .btn {
        width: 100% !important;
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

/* 导出弹窗样式 */
#exportModal .modal {
    max-width: 450px;
}
</style>

<?php renderFooter(); ?>
