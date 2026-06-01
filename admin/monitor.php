<?php
/**
 * 后台管理 - 监控记录管理
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/common.php';

checkAdminLogin();

$db = Database::getInstance();
$message = '';

// 手动添加监控记录
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $udid = trim($_POST['udid'] ?? '');
    $identity = trim($_POST['identity'] ?? '添加者');
    
    if (!empty($udid)) {
        $exists = $db->fetch("SELECT id, count FROM " . $db->getTable('monitor') . " WHERE udid = ? AND identity = ?", [$udid, $identity]);
        if ($exists) {
            $db->update('monitor', ['count' => $exists['count'] + 1], "id = ?", [$exists['id']]);
        } else {
            $db->insert('monitor', [
                'udid' => $udid,
                'identity' => $identity,
                'count' => 1,
                'add_time' => time()
            ]);
        }
        $message = showMessage('监控记录添加成功');
        logOperation('添加监控记录', "添加UDID: {$udid}, 身份: {$identity}");
    } else {
        $message = showMessage('UDID不能为空', 'error');
    }
}

// 拉黑并删除监控记录（从监控转移到黑名单）
if (isset($_GET['black'])) {
    $idToBlack = intval($_GET['black']);
    $monitor = $db->fetch("SELECT * FROM " . $db->getTable('monitor') . " WHERE id = ?", [$idToBlack]);
    
    if ($monitor) {
        // 添加到黑名单
        $exists = $db->fetch("SELECT id FROM " . $db->getTable('blacklist') . " WHERE udid = ?", [$monitor['udid']]);
        if (!$exists) {
            $db->insert('black_list', [
                'udid' => $monitor['udid'],
                'reason' => '从监控记录拉黑 - ' . $monitor['identity'],
                'add_time' => time()
            ]);
        }
        // 删除监控记录
        $db->delete('monitor', "id = ?", [$idToBlack]);
        $message = showMessage('已拉黑并删除监控记录');
        logOperation('拉黑监控设备', "拉黑UDID: {$monitor['udid']}");
    }
}

// 删除监控记录
if (isset($_GET['delete'])) {
    $idToDelete = intval($_GET['delete']);
    $db->delete('monitor', "id = ?", [$idToDelete]);
    $message = showMessage('监控记录已删除');
    logOperation('删除监控记录', "删除记录ID: {$idToDelete}");
}

// 批量删除
if (isset($_POST['action']) && $_POST['action'] === 'batch_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->query("DELETE FROM " . $db->getTable('monitor') . " WHERE id IN ({$placeholders})", $ids);
        $message = showMessage('批量删除成功');
        logOperation('批量删除监控记录', "删除ID: " . implode(',', $ids));
    }
}

// 获取列表
$page = intval($_GET['page'] ?? 1);
$identity = $_GET['identity'] ?? '';
$keyword = $_GET['keyword'] ?? '';
$where = "WHERE 1=1";
$params = [];

if (!empty($identity)) {
    $where .= " AND identity = ?";
    $params[] = $identity;
}

if (!empty($keyword)) {
    $where .= " AND udid LIKE ?";
    $params[] = "%{$keyword}%";
}

// 获取总数
$countResult = $db->fetch("SELECT COUNT(*) as total FROM " . $db->getTable('monitor') . " {$where}", $params);
$total = $countResult['total'];

// 获取列表
$limit = 20;
$offset = ($page - 1) * $limit;
$sql = "SELECT * FROM " . $db->getTable('monitor') . " {$where} ORDER BY count DESC, add_time DESC LIMIT ?, ?";
$params[] = (int)$offset;
$params[] = (int)$limit;
$list = $db->fetchAll($sql, $params);

// 获取统计
$stats = $db->fetchAll("SELECT identity, COUNT(*) as count, SUM(count) as total_times FROM " . $db->getTable('monitor') . " GROUP BY identity");

renderHeader('监控记录', 'monitor');
?>

<?php echo $message; ?>

<!-- 统计卡片 -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="2" y1="17" x2="22" y2="17"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $total; ?></h3>
            <p>监控设备总数</p>
        </div>
    </div>
    <?php foreach ($stats as $stat): ?>
    <div class="stat-card">
        <div class="stat-icon <?php echo $stat['identity'] == '破解者' ? 'danger' : 'warning'; ?>">
            <?php if ($stat['identity'] == '破解者'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3.05h16.94a2 2 0 0 0 1.71-3.05L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <?php endif; ?>
        </div>
        <div class="stat-info">
            <h3><?php echo $stat['count']; ?></h3>
            <p><?php echo $stat['identity']; ?> (<?php echo $stat['total_times']; ?>次)</p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

        <!-- 添加监控记录弹窗 -->
        <div id="addMonitorModal" class="modal-overlay" style="display: none;">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>添加监控记录</h3>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addMonitorModal').style.display='none'" style="padding: 6px 12px;">✕</button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>设备UDID</label>
                            <input type="text" name="udid" class="form-control" placeholder="输入设备UDID" required>
                        </div>
                        <div class="form-group">
                            <label>身份</label>
                            <select name="identity" class="form-control">
                                <option value="添加者">添加者</option>
                                <option value="破解者">破解者</option>
                            </select>
                        </div>
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">添加记录</button>
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addMonitorModal').style.display='none'">取消</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        // 点击弹窗外部关闭
        window.onclick = function(event) {
            var modal = document.getElementById('addMonitorModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        </script>

        <!-- 监控记录列表 -->
        <div class="panel">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <h3>监控记录列表</h3>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <form method="GET" style="display: flex; gap: 12px; align-items: center;">
                        <select name="identity" class="form-control" style="width: 110px; flex-shrink: 0;">
                            <option value="">全部身份</option>
                            <option value="添加者" <?php echo $identity === '添加者' ? 'selected' : ''; ?>>添加者</option>
                            <option value="破解者" <?php echo $identity === '破解者' ? 'selected' : ''; ?>>破解者</option>
                        </select>
                        <input type="text" name="keyword" class="form-control" placeholder="搜索UDID" value="<?php echo htmlspecialchars($keyword); ?>" style="width: 200px; flex-shrink: 0;">
                        <button type="submit" class="btn btn-primary" style="flex-shrink: 0;">搜索</button>
                    </form>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('addMonitorModal').style.display='flex'" style="flex-shrink: 0;">+ 添加监控记录</button>
                </div>
            </div>
            <div class="panel-body">
                <form method="POST" id="batchForm">
                    <input type="hidden" name="action" value="batch_delete">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="checkAll"></th>
                                <th>ID</th>
                                <th>UDID</th>
                                <th>身份</th>
                                <th>次数</th>
                                <th>添加时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($list as $item): ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?php echo $item['id']; ?>"></td>
                                <td><?php echo $item['id']; ?></td>
                                <td><code><?php echo htmlspecialchars($item['udid']); ?></code></td>
                                <td>
                                    <?php if ($item['identity'] == '破解者'): ?>
                                    <span class="badge badge-danger">破解者</span>
                                    <?php else: ?>
                                    <span class="badge badge-warning">添加者</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $item['count']; ?></td>
                                <td><?php echo date('Y-m-d H:i:s', $item['add_time']); ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="?black=<?php echo $item['id']; ?>&page=<?php echo $page; ?>" class="btn btn-warning" onclick="return confirm('确定拉黑此设备？')">拉黑</a>
                                        <a href="?delete=<?php echo $item['id']; ?>&page=<?php echo $page; ?>" class="btn btn-danger" onclick="return confirm('确定删除此记录？')">删除</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($total > 0): ?>
                    <div style="margin-top: 10px;">
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
                    <a href="?page=<?php echo $page-1; ?>&identity=<?php echo urlencode($identity); ?>&keyword=<?php echo urlencode($keyword); ?>" class="page-link">上一页</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page-2); $i <= min($pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&identity=<?php echo urlencode($identity); ?>&keyword=<?php echo urlencode($keyword); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&identity=<?php echo urlencode($identity); ?>&keyword=<?php echo urlencode($keyword); ?>" class="page-link">下一页</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
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
/* 监控记录页响应式优化 */
@media (max-width: 768px) {
    /* panel-header 垂直布局 */
    .panel-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px !important;
    }
    
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
    
    .panel-header form select,
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
    .search-box form select,
    .search-box form input[type="text"] {
        width: 100% !important;
        flex: none !important;
    }
    .search-box form button,
    .search-box form .btn {
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
        min-width: 500px;
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
