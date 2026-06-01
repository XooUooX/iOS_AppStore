<?php
/**
 * 后台管理 - 黑名单管理
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../includes/class.cardkeyclaim.php';

checkAdminLogin();

$db = Database::getInstance();
$cardKeyClaim = new CardKeyClaim();

// 添加黑名单
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $type = intval($_POST['type'] ?? 1);
    $value = trim($_POST['value'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $expireDays = intval($_POST['expire_days'] ?? 0);
    
    if (!empty($value)) {
        // 计算过期时间（0表示永久）
        $expireTime = null;
        if ($expireDays > 0) {
            $expireTime = date('Y-m-d H:i:s', strtotime("+{$expireDays} days"));
        }
        
        // 检查是否已存在
        $exists = $db->fetch(
            "SELECT id FROM " . $db->getTable('blacklist') . " WHERE type = ? AND value = ? AND (expire_time IS NULL OR expire_time > NOW())",
            [$type, $value]
        );
        
        if (!$exists) {
            $result = $cardKeyClaim->addToBlacklist(null, $type, $value, $reason, $expireTime);
            if ($result) {
                logOperation('添加黑名单', "类型: {$type}, 值: {$value}");
                redirectAfterPost('black_list.php', '黑名单添加成功', 'success');
            } else {
                redirectAfterPost('black_list.php', '添加黑名单失败', 'danger');
            }
        } else {
            redirectAfterPost('black_list.php', '该记录已在黑名单中', 'warning');
        }
    } else {
        redirectAfterPost('black_list.php', '拉黑值不能为空', 'danger');
    }
}

// 删除黑名单
if (isset($_GET['delete'])) {
    $idToDelete = intval($_GET['delete']);
    $result = $cardKeyClaim->removeFromBlacklist($idToDelete);
    if ($result) {
        logOperation('删除黑名单', "删除黑名单ID: {$idToDelete}");
        redirectAfterPost('black_list.php', '黑名单已删除', 'success');
    } else {
        redirectAfterPost('black_list.php', '删除失败', 'danger');
    }
}

// 批量删除
if (isset($_POST['action']) && $_POST['action'] === 'batch_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->query("DELETE FROM " . $db->getTable('blacklist') . " WHERE id IN ({$placeholders})", $ids);
        logOperation('批量删除黑名单', "删除ID: " . implode(',', $ids));
        redirectAfterPost('black_list.php', '批量删除成功', 'success');
    }
}

// 获取列表
$page = intval($_GET['page'] ?? 1);
$keyword = $_GET['keyword'] ?? '';
$where = "WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $where .= " AND value LIKE ?";
    $params[] = "%{$keyword}%";
}

// 获取总数
$countResult = $db->fetch("SELECT COUNT(*) as total FROM " . $db->getTable('blacklist') . " {$where}", $params);
$total = $countResult['total'];

// 获取列表
$limit = 20;
$offset = ($page - 1) * $limit;
$sql = "SELECT * FROM " . $db->getTable('blacklist') . " {$where} ORDER BY create_time DESC LIMIT ?, ?";
$params[] = (int)$offset;
$params[] = (int)$limit;
$list = $db->fetchAll($sql, $params);

renderHeader('设备黑名单', 'black_list');
?>

<?php echo getPrgMessage(); ?>

<!-- 添加黑名单弹窗 -->
<div id="addBlacklistModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>添加黑名单</h3>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addBlacklistModal').style.display='none'" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>拉黑类型</label>
                    <select name="type" class="form-control">
                        <option value="1">设备ID</option>
                        <option value="2">IP地址</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>拉黑值</label>
                    <input type="text" name="value" class="form-control" placeholder="输入设备ID或IP地址" required>
                </div>
                
                <div class="form-group">
                    <label>拉黑原因（可选）</label>
                    <input type="text" name="reason" class="form-control" placeholder="拉黑原因">
                </div>
                
                <div class="form-group">
                    <label>过期天数（0表示永久）</label>
                    <input type="number" name="expire_days" class="form-control" value="0" min="0">
                    <small style="color: var(--gray-500);">0表示永久拉黑，其他数字表示多少天后自动解除</small>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">添加黑名单</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addBlacklistModal').style.display='none'">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 点击弹窗外部关闭
window.onclick = function(event) {
    var modal = document.getElementById('addBlacklistModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<!-- 黑名单列表 -->
<div class="panel">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <h2>黑名单列表 (共 <?php echo $total; ?> 条)</h2>
        <div style="display: flex; gap: 12px; align-items: center;">
            <form method="GET" style="display: flex; gap: 12px; align-items: center;">
                <input type="text" name="keyword" class="form-control" placeholder="搜索设备ID/IP" value="<?php echo htmlspecialchars($keyword); ?>" style="width: 250px;">
                <button type="submit" class="btn btn-primary" style="flex-shrink: 0;">搜索</button>
            </form>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('addBlacklistModal').style.display='flex'" style="flex-shrink: 0;">+ 添加黑名单</button>
        </div>
    </div>
    <div class="panel-body">
        <form method="POST" id="batchForm">
            <input type="hidden" name="action" value="batch_delete">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="checkAll"></th>
                            <th>ID</th>
                            <th>类型</th>
                            <th>拉黑值</th>
                            <th>批次ID</th>
                            <th>拉黑原因</th>
                            <th>过期时间</th>
                            <th>添加时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $item): ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?php echo $item['id']; ?>"></td>
                            <td><?php echo $item['id']; ?></td>
                            <td><?php echo $item['type'] == 1 ? '设备ID' : 'IP地址'; ?></td>
                            <td><code><?php echo htmlspecialchars($item['value']); ?></code></td>
                            <td><?php echo $item['batch_id'] ?? '全局'; ?></td>
                            <td><?php echo htmlspecialchars($item['reason'] ?: '-'); ?></td>
                            <td><?php echo $item['expire_time'] ?: '永久'; ?></td>
                            <td><?php echo $item['create_time']; ?></td>
                            <td>
                                <div class="action-btns">
                                    <a href="?delete=<?php echo $item['id']; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除此黑名单？')">删除</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total > 0): ?>
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-danger" onclick="return confirm('确定批量删除选中项？')">批量删除</button>
            </div>
            <?php endif; ?>
        </form>

        <?php 
        $pages = ceil($total / $limit);
        if ($pages > 1): 
        ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>&keyword=<?php echo urlencode($keyword); ?>" class="page-link">&laquo;</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page-2); $i <= min($pages, $page+2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&keyword=<?php echo urlencode($keyword); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
            <a href="?page=<?php echo $page+1; ?>&keyword=<?php echo urlencode($keyword); ?>" class="page-link">&raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('checkAll').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('input[name="ids[]"]');
    checkboxes.forEach(function(cb) {
        cb.checked = document.getElementById('checkAll').checked;
    });
});
</script>

<style>
/* 黑名单列表页响应式优化 */
@media (max-width: 768px) {
    /* panel-header 垂直布局 */
    .panel-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px !important;
    }
    
    .panel-header h2 {
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
    
    .panel-header form input[type="text"] {
        width: 100% !important;
        flex: none !important;
        box-sizing: border-box;
    }
    
    .panel-header form button,
    .panel-header .btn {
        width: 100% !important;
    }
    
    /* 搜索框区域 */
    .search-box form {
        flex-direction: column !important;
        gap: 10px !important;
    }
    .search-box form input[type="text"] {
        width: 100% !important;
        flex: none !important;
    }
    .search-box form button,
    .search-box form .btn {
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
        min-width: 600px;
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

<?php renderFooter(); ?>
