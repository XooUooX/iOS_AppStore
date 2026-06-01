<?php
/**
 * 后台管理 - 卡密领取管理
 * 
 * 功能说明：
 * - 创建/编辑/删除卡密领取批次
 * - 批量添加卡密到批次
 * - 查看领取明细，包含设备信息、IP地址、领取时间
 * - 实时显示领取进度（总量/已领/剩余）
 * - 支持UUID脱敏显示
 * 
 * 页面功能：
 * - 批次列表：展示所有领取批次及统计信息
 * - 批次明细：查看批次内的卡密及领取状态
 * - 创建批次：设置批次名称、卡密类型、有效期等
 * - 添加卡密：将现有未使用卡密添加到批次
 * 
 * @author Ning.Si
 * @version 1.0.3
 * @date 2026-02-13
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/../includes/class.cardkey.php';
require_once __DIR__ . '/../includes/class.cardkeyclaim.php';
require_once __DIR__ . '/common.php';

checkAdminLogin();

$cardKey = new CardKey();
$cardKeyClaim = new CardKeyClaim();

// 创建批次
if (isset($_POST['action']) && $_POST['action'] === 'create_batch') {
    $data = [
        'batch_name' => $_POST['batch_name'] ?? '',
        'batch_type' => intval($_POST['batch_type'] ?? 1),
        'expire_days' => intval($_POST['expire_days'] ?? 30),
        'status' => intval($_POST['status'] ?? 1),
        'password' => $_POST['password'] ?? '',
        'remark' => $_POST['remark'] ?? '',
        // 领取限制配置
        'id_limit_type' => intval($_POST['id_limit_type'] ?? 0),
        'id_limit_count' => intval($_POST['id_limit_count'] ?? 1),
        'ip_limit_type' => intval($_POST['ip_limit_type'] ?? 0),
        'ip_limit_count' => intval($_POST['ip_limit_count'] ?? 1)
    ];
    
    $result = $cardKeyClaim->createBatch($data);
    if ($result) {
        logOperation('创建领取批次', $data['batch_name']);
        redirectAfterPost('card_claim.php', '领取批次创建成功', 'success');
    } else {
        redirectAfterPost('card_claim.php', '创建失败', 'danger');
    }
}

// 更新批次
if (isset($_POST['action']) && $_POST['action'] === 'update_batch') {
    $batchId = intval($_POST['batch_id']);
    $data = [
        'batch_name' => $_POST['batch_name'] ?? '',
        'batch_type' => intval($_POST['batch_type'] ?? 1),
        'expire_days' => intval($_POST['expire_days'] ?? 30),
        'status' => intval($_POST['status'] ?? 1),
        'password' => $_POST['password'] ?? '',
        'remark' => $_POST['remark'] ?? '',
        // 领取限制配置
        'id_limit_type' => intval($_POST['id_limit_type'] ?? 0),
        'id_limit_count' => intval($_POST['id_limit_count'] ?? 1),
        'ip_limit_type' => intval($_POST['ip_limit_type'] ?? 0),
        'ip_limit_count' => intval($_POST['ip_limit_count'] ?? 1)
    ];
    
    $result = $cardKeyClaim->updateBatch($batchId, $data);
    if ($result) {
        logOperation('更新领取批次', "ID: {$batchId}");
        redirectAfterPost('card_claim.php', '批次更新成功', 'success');
    } else {
        redirectAfterPost('card_claim.php', '更新失败', 'danger');
    }
}

// 删除批次
if (isset($_GET['delete_batch'])) {
    $batchId = intval($_GET['delete_batch']);
    $result = $cardKeyClaim->deleteBatch($batchId);
    if ($result) {
        logOperation('删除领取批次', "ID: {$batchId}");
        redirectAfterPost('card_claim.php', '批次已删除', 'success');
    } else {
        redirectAfterPost('card_claim.php', '删除失败', 'danger');
    }
}

// 添加卡密到批次
if (isset($_POST['action']) && $_POST['action'] === 'add_cards') {
    $batchId = intval($_POST['batch_id']);
    $cardKeys = $_POST['card_keys'] ?? [];
    
    if (!empty($cardKeys)) {
        $result = $cardKeyClaim->addCardsToBatch($batchId, $cardKeys);
        if ($result['success']) {
            logOperation('添加卡密到批次', "批次ID: {$batchId}, 数量: {$result['added']}");
            redirectAfterPost('card_claim.php', "成功添加 {$result['added']} 张卡密", 'success');
        } else {
            redirectAfterPost('card_claim.php', $result['message'], 'danger');
        }
    } else {
        redirectAfterPost('card_claim.php', '请选择要添加的卡密', 'warning');
    }
}

// 拉黑操作
if (isset($_POST['action']) && $_POST['action'] === 'blacklist') {
    $batchId = intval($_POST['batch_id'] ?? 0);
    $scope = $_POST['blacklist_scope'] ?? 'batch'; // batch 或 global
    $type = intval($_POST['blacklist_type'] ?? 1);
    $value = $_POST['blacklist_value'] ?? '';
    $reason = $_POST['blacklist_reason'] ?? '';
    $expireUnit = $_POST['expire_unit'] ?? 'hour';
    $expireValue = intval($_POST['expire_value'] ?? 1);
    
    if (!empty($value)) {
        // 计算过期时间
        $expireTime = null;
        if ($expireUnit !== 'forever') {
            switch ($expireUnit) {
                case 'hour':
                    $interval = "+{$expireValue} hours";
                    break;
                case 'day':
                    $interval = "+{$expireValue} days";
                    break;
                case 'month':
                    $interval = "+{$expireValue} months";
                    break;
                case 'year':
                    $interval = "+{$expireValue} years";
                    break;
                default:
                    $interval = "+1 hour";
            }
            $expireTime = date('Y-m-d H:i:s', strtotime($interval));
        }
        
        // 根据范围决定 batch_id：global 时为 null，batch 时使用传入的 batchId
        $finalBatchId = ($scope === 'global') ? null : ($batchId > 0 ? $batchId : null);
        
        $result = $cardKeyClaim->addToBlacklist($finalBatchId, $type, $value, $reason, $expireTime);
        if ($result) {
            logOperation('添加黑名单', "批次ID: {$batchId}, 类型: {$type}, 值: {$value}");
            redirectAfterPost('card_claim.php', '已添加到黑名单', 'success');
        } else {
            redirectAfterPost('card_claim.php', '添加黑名单失败', 'danger');
        }
    } else {
        redirectAfterPost('card_claim.php', '请输入拉黑值', 'warning');
    }
}

// 解除黑名单
if (isset($_GET['remove_blacklist'])) {
    $blacklistId = intval($_GET['remove_blacklist']);
    $result = $cardKeyClaim->removeFromBlacklist($blacklistId);
    if ($result) {
        logOperation('移除黑名单', "ID: {$blacklistId}");
        redirectAfterPost('card_claim.php', '已从黑名单移除', 'success');
    } else {
        redirectAfterPost('card_claim.php', '移除失败', 'danger');
    }
}

// 获取开关状态
$db = Database::getInstance();
$claimEnabled = $db->fetch("SELECT value FROM " . $db->getTable('config') . " WHERE name = 'card_claim_enabled'")['value'] ?? '1';

// 切换开关
if (isset($_GET['toggle_switch'])) {
    $newStatus = $claimEnabled === '1' ? '0' : '1';
    $db->update('config', ['value' => $newStatus], "name = ?", ['card_claim_enabled']);
    logOperation('切换卡密领取', $newStatus === '1' ? '启用' : '禁用');
    redirectAfterPost('card_claim.php', $newStatus === '1' ? '卡密领取功能已启用' : '卡密领取功能已禁用', 'success');
}
$page = intval($_GET['page'] ?? 1);
$status = $_GET['status'] ?? '';
$batchList = $cardKeyClaim->getBatchList($page, 20, $status !== '' ? intval($status) : null);

// 获取当前编辑的批次
$editBatch = null;
if (isset($_GET['edit_batch'])) {
    $editBatch = $cardKeyClaim->getBatch(intval($_GET['edit_batch']));
}

// 获取批次明细
$viewBatch = null;
$batchItems = [];
$itemPage = intval($_GET['item_page'] ?? 1);
$itemStatus = $_GET['item_status'] ?? '';

if (isset($_GET['view_batch'])) {
    $viewBatchId = intval($_GET['view_batch']);
    $viewBatch = $cardKeyClaim->getBatch($viewBatchId);
    $batchItems = $cardKeyClaim->getBatchItems(
        $viewBatchId, 
        $itemPage, 
        20, 
        $itemStatus !== '' ? intval($itemStatus) : null
    );
    $itemTotal = $cardKeyClaim->getBatchItemCount($viewBatchId, $itemStatus !== '' ? intval($itemStatus) : null);
    $itemPages = ceil($itemTotal / 20);
}

// 获取可用的未使用卡密列表（用于添加到批次）
$availableCards = [];
if (isset($_GET['view_batch'])) {
    // 获取所有未使用的卡密
    $allCards = $cardKey->getList(1, 1000, 0)['list']; // 未使用的卡密
    
    // 过滤掉已在该批次中的卡密
    $existingCards = $cardKeyClaim->getBatchItems(intval($_GET['view_batch']), 1, 10000);
    $existingKeys = array_column($existingCards, 'card_key');
    
    // 过滤掉已被添加到其他批次的卡密
    $allBatchItems = $db->fetchAll("SELECT DISTINCT card_key FROM " . $db->getTable('card_key_batch_items'));
    $usedInBatchKeys = array_column($allBatchItems, 'card_key');
    
    foreach ($allCards as $card) {
        // 条件1：不在当前批次中
        // 条件2：未被添加到任何其他批次
        if (!in_array($card['card_key'], $existingKeys) && !in_array($card['card_key'], $usedInBatchKeys)) {
            $availableCards[] = $card;
        }
    }
}

renderHeader('卡密领取管理', 'card_claim');
?>

<?php echo getPrgMessage(); ?>

<!-- 批次列表 -->
<div class="panel">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>卡密领取批次</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="?toggle_switch=1" class="btn <?php echo $claimEnabled === '1' ? 'btn-success' : 'btn-secondary'; ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;">
                <?php if ($claimEnabled === '1'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>领取功能已开启</span>
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <span>领取功能已关闭</span>
                <?php endif; ?>
            </a>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('createBatchModal').style.display='flex'" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: 500; transition: all 0.3s ease;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>创建领取批次</span>
            </button>
        </div>
    </div>
    <div class="panel-body">
        <div class="search-box">
            <form method="GET" style="display: flex; gap: 12px; flex: 1; align-items: center;">
                <select name="status" class="form-control" style="width: 110px; flex-shrink: 0;">
                    <option value="">全部状态</option>
                    <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>启用</option>
                    <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>禁用</option>
                </select>
                <button type="submit" class="btn btn-primary" style="flex-shrink: 0;">筛选</button>
            </form>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>批次名称</th>
                        <th>卡密类型</th>
                        <th>有效期</th>
                        <th>总量/已领/剩余</th>
                        <th>状态</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batchList['list'] as $item): 
                        $remaining = $item['total_count'] - $item['used_count'];
                        $progressPercent = $item['total_count'] > 0 ? round($item['used_count'] / $item['total_count'] * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><?php echo $item['id']; ?></td>
                        <td><?php echo htmlspecialchars($item['batch_name']); ?></td>
                        <td><?php echo CardKeyClaim::getTypeName($item['batch_type']); ?></td>
                        <td><?php echo $item['expire_days']; ?>天</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span><?php echo $item['total_count']; ?>/<?php echo $item['used_count']; ?>/<?php echo $remaining; ?></span>
                                <div style="width: 60px; height: 6px; background: var(--gray-200); border-radius: 3px; overflow: hidden;">
                                    <div style="width: <?php echo $progressPercent; ?>%; height: 100%; background: <?php echo $progressPercent >= 100 ? 'var(--danger)' : 'var(--success)'; ?>; transition: width 0.3s;"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($item['status'] == 1): ?>
                            <span class="badge badge-success">启用</span>
                            <?php else: ?>
                            <span class="badge badge-secondary">禁用</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $item['create_time']; ?></td>
                        <td>
                            <a href="?view_batch=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">管理卡密</a>
                            <a href="?edit_batch=<?php echo $item['id']; ?>" class="btn btn-sm btn-success">编辑</a>
                            <a href="?delete_batch=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除此批次？批次内的卡密关联将被清除，但卡密本身不会被删除。')">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php echo pagination($batchList['page'], $batchList['pages'], "?status={$status}"); ?>
    </div>
</div>

<?php if ($viewBatch): ?>
<!-- 批次卡密明细 -->
<div class="panel" style="margin-top: 20px;">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>批次明细：<?php echo htmlspecialchars($viewBatch['batch_name']); ?></h2>
        <div style="display: flex; gap: 10px;">
            <?php if (!empty($availableCards)): ?>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('addCardsModal').style.display='flex'">+ 添加卡密</button>
            <?php else: ?>
            <button type="button" class="btn btn-secondary" disabled>暂无可添加卡密</button>
            <?php endif; ?>
            <a href="?" class="btn btn-secondary">返回列表</a>
        </div>
    </div>
    <div class="panel-body">
        <div class="search-box">
            <form method="GET" style="display: flex; gap: 12px; flex: 1; align-items: center;">
                <input type="hidden" name="view_batch" value="<?php echo $viewBatch['id']; ?>">
                <select name="item_status" class="form-control" style="width: 110px; flex-shrink: 0;">
                    <option value="">全部状态</option>
                    <option value="0" <?php echo $itemStatus === '0' ? 'selected' : ''; ?>>未领取</option>
                    <option value="1" <?php echo $itemStatus === '1' ? 'selected' : ''; ?>>已领取</option>
                </select>
                <button type="submit" class="btn btn-primary" style="flex-shrink: 0;">筛选</button>
            </form>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>卡密</th>
                        <th>状态</th>
                        <th>领取设备ID</th>
                        <th>设备UDID</th>
                        <th>设备名称</th>
                        <th>领取IP</th>
                        <th>领取时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batchItems as $item): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($item['card_key']); ?></code></td>
                        <td>
                            <?php if ($item['status'] == 0): ?>
                            <span class="badge badge-secondary">未领取</span>
                            <?php else: ?>
                            <span class="badge badge-success">已领取</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $item['device_id'] ? htmlspecialchars(substr($item['device_id'], 0, 15) . '...') : '-'; ?></td>
                        <td><?php echo $item['device_uuid'] ? htmlspecialchars(CardKeyClaim::maskUdid($item['device_uuid'])) : '-'; ?></td>
                        <td><?php echo $item['device_name'] ? htmlspecialchars($item['device_name']) : '-'; ?></td>
                        <td><?php echo $item['ip_address'] ?: '-'; ?></td>
                        <td><?php echo $item['claim_time'] ?: '-'; ?></td>
                        <td>
                            <?php if ($item['status'] == 0): ?>
                            <a href="?view_batch=<?php echo $viewBatch['id']; ?>&remove_item=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定从批次中移除此卡密？')">移除</a>
                            <?php else: ?>
                            <div class="action-btns">
                                <button type="button" class="btn btn-sm btn-warning" onclick="showBlacklistModal('<?php echo htmlspecialchars($item['device_id'] ?? ''); ?>', '<?php echo htmlspecialchars($item['ip_address'] ?? ''); ?>')">🚫 拉黑</button>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (isset($itemPages) && $itemPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $itemPages; $i++): ?>
                <?php if ($i == $itemPage): ?>
                <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                <a href="?view_batch=<?php echo $viewBatch['id']; ?>&item_status=<?php echo $itemStatus; ?>&item_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- 创建批次弹窗 -->
<div id="createBatchModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>创建领取批次</h3>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('createBatchModal').style.display='none'" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="create_batch">
                <div class="form-group">
                    <label>批次名称 *</label>
                    <input type="text" name="batch_name" class="form-control" placeholder="如：春节活动卡密" required>
                </div>
                <div class="form-group">
                    <label>卡密类型</label>
                    <select name="batch_type" class="form-control" id="batchType" onchange="updateBatchExpireDays()">
                        <option value="1">月卡</option>
                        <option value="2">季卡</option>
                        <option value="3">年卡</option>
                        <option value="4">永久</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>有效期（天）</label>
                    <input type="number" id="batchExpireInput" name="expire_days" class="form-control" value="30" min="1">
                    <small style="color: var(--gray-500);">月卡=30天，季卡=90天，年卡=365天，永久=36500天</small>
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" class="form-control">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>领取口令</label>
                    <input type="text" name="password" class="form-control" placeholder="留空表示不需要口令">
                    <small style="color: var(--gray-500);">设置后用户需要输入正确口令才能领取</small>
                </div>
                
                <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h4 style="margin-bottom: 15px; color: #333;">🛡️ 领取限制设置</h4>
                    
                    <div class="form-group">
                        <label>设备ID限制</label>
                        <select name="id_limit_type" class="form-control" style="margin-bottom: 8px;">
                            <option value="0">无限制</option>
                            <option value="1">限制同一设备ID</option>
                        </select>
                        <input type="number" name="id_limit_count" class="form-control" value="1" min="1" placeholder="可领取次数">
                        <small style="color: var(--gray-500);">同一设备ID最多可领取次数</small>
                    </div>
                    
                    <div class="form-group">
                        <label>IP限制</label>
                        <select name="ip_limit_type" class="form-control" style="margin-bottom: 8px;">
                            <option value="0">无限制</option>
                            <option value="1">限制单IP</option>
                            <option value="2">限制IP段（前3段）</option>
                        </select>
                        <input type="number" name="ip_limit_count" class="form-control" value="1" min="1" placeholder="可领取次数">
                        <small style="color: var(--gray-500);">同一IP最多可领取次数</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>备注</label>
                    <textarea name="remark" class="form-control" rows="3" placeholder="可选，填写活动说明等"></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">创建</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('createBatchModal').style.display='none'">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editBatch): ?>
<!-- 编辑批次弹窗 -->
<div id="editBatchModal" class="modal-overlay" style="display: flex;">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>编辑领取批次</h3>
            <a href="?" class="btn btn-secondary" style="padding: 6px 12px;">✕</a>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_batch">
                <input type="hidden" name="batch_id" value="<?php echo $editBatch['id']; ?>">
                <div class="form-group">
                    <label>批次名称 *</label>
                    <input type="text" name="batch_name" class="form-control" value="<?php echo htmlspecialchars($editBatch['batch_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>卡密类型</label>
                    <select name="batch_type" class="form-control">
                        <option value="1" <?php echo $editBatch['batch_type'] == 1 ? 'selected' : ''; ?>>月卡</option>
                        <option value="2" <?php echo $editBatch['batch_type'] == 2 ? 'selected' : ''; ?>>季卡</option>
                        <option value="3" <?php echo $editBatch['batch_type'] == 3 ? 'selected' : ''; ?>>年卡</option>
                        <option value="4" <?php echo $editBatch['batch_type'] == 4 ? 'selected' : ''; ?>>永久</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>有效期（天）</label>
                    <input type="number" name="expire_days" class="form-control" value="<?php echo $editBatch['expire_days']; ?>" min="1">
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" class="form-control">
                        <option value="1" <?php echo $editBatch['status'] == 1 ? 'selected' : ''; ?>>启用</option>
                        <option value="0" <?php echo $editBatch['status'] == 0 ? 'selected' : ''; ?>>禁用</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>领取口令</label>
                    <input type="text" name="password" class="form-control" value="<?php echo htmlspecialchars($editBatch['password'] ?? ''); ?>" placeholder="留空表示不需要口令">
                    <small style="color: var(--gray-500);">设置后用户需要输入正确口令才能领取</small>
                </div>
                
                <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h4 style="margin-bottom: 15px; color: #333;">🛡️ 领取限制设置</h4>
                    
                    <div class="form-group">
                        <label>设备ID限制</label>
                        <select name="id_limit_type" class="form-control" style="margin-bottom: 8px;">
                            <option value="0" <?php echo ($editBatch['id_limit_type'] ?? 0) == 0 ? 'selected' : ''; ?>>无限制</option>
                            <option value="1" <?php echo ($editBatch['id_limit_type'] ?? 0) == 1 ? 'selected' : ''; ?>>限制同一设备ID</option>
                        </select>
                        <input type="number" name="id_limit_count" class="form-control" value="<?php echo $editBatch['id_limit_count'] ?? 1; ?>" min="1" placeholder="可领取次数">
                        <small style="color: var(--gray-500);">同一设备ID最多可领取次数</small>
                    </div>
                    
                    <div class="form-group">
                        <label>IP限制</label>
                        <select name="ip_limit_type" class="form-control" style="margin-bottom: 8px;">
                            <option value="0" <?php echo ($editBatch['ip_limit_type'] ?? 0) == 0 ? 'selected' : ''; ?>>无限制</option>
                            <option value="1" <?php echo ($editBatch['ip_limit_type'] ?? 0) == 1 ? 'selected' : ''; ?>>限制单IP</option>
                            <option value="2" <?php echo ($editBatch['ip_limit_type'] ?? 0) == 2 ? 'selected' : ''; ?>>限制IP段（前3段）</option>
                        </select>
                        <input type="number" name="ip_limit_count" class="form-control" value="<?php echo $editBatch['ip_limit_count'] ?? 1; ?>" min="1" placeholder="可领取次数">
                        <small style="color: var(--gray-500);">同一IP最多可领取次数</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>备注</label>
                    <textarea name="remark" class="form-control" rows="3"><?php echo htmlspecialchars($editBatch['remark']); ?></textarea>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <a href="?" class="btn btn-secondary">取消</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($viewBatch && !empty($availableCards)): ?>
<!-- 添加卡密弹窗 -->
<div id="addCardsModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 700px; max-height: 80vh;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>添加卡密到批次</h3>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addCardsModal').style.display='none'" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
            <form method="POST" id="addCardsForm">
                <input type="hidden" name="action" value="add_cards">
                <input type="hidden" name="batch_id" value="<?php echo $viewBatch['id']; ?>">
                
                <!-- 卡密类型筛选 -->
                <div style="margin-bottom: 15px; padding: 12px; background: var(--gray-50); border-radius: 8px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">按卡密类型筛选：</label>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" class="filter-btn active" data-type="all" onclick="filterCardsByType('all', event)">全部</button>
                        <button type="button" class="filter-btn" data-type="1" onclick="filterCardsByType('1', event)">月卡</button>
                        <button type="button" class="filter-btn" data-type="2" onclick="filterCardsByType('2', event)">季卡</button>
                        <button type="button" class="filter-btn" data-type="3" onclick="filterCardsByType('3', event)">年卡</button>
                        <button type="button" class="filter-btn" data-type="4" onclick="filterCardsByType('4', event)">永久卡</button>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="selectAllCards" onchange="toggleSelectAll()">
                        <span>全选所有卡密 (<span id="cardCount"><?php echo count($availableCards); ?></span>张)</span>
                    </label>
                </div>
                <div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: 8px; padding: 10px;">
                    <?php foreach ($availableCards as $card): ?>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 8px; border-bottom: 1px solid var(--gray-100); cursor: pointer;" class="card-item" data-type="<?php echo $card['card_type']; ?>">
                        <input type="checkbox" name="card_keys[]" value="<?php echo htmlspecialchars($card['card_key']); ?>" class="card-checkbox">
                        <div style="flex: 1;">
                            <div><code><?php echo htmlspecialchars($card['card_key']); ?></code></div>
                            <div style="font-size: 12px; color: var(--gray-500);">
                                <?php echo CardKey::getTypeName($card['card_type']); ?> | 
                                有效期: <?php echo $card['expire_days']; ?>天 | 
                                创建: <?php echo $card['create_time']; ?>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">添加选中卡密</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addCardsModal').style.display='none'">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.filter-btn {
    padding: 6px 12px;
    border: 1px solid var(--gray-300);
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.filter-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.filter-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
</style>

<script>
let currentFilter = 'all';

function filterCardsByType(type, event) {
    event.preventDefault();
    currentFilter = type;
    
    // 更新按钮状态
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // 过滤卡密 - 先取消所有隐藏卡密的选中状态
    const items = document.querySelectorAll('.card-item');
    let visibleCount = 0;
    
    items.forEach(item => {
        const checkbox = item.querySelector('.card-checkbox');
        if (type === 'all' || item.dataset.type === type) {
            item.style.display = 'flex';
            visibleCount++;
        } else {
            item.style.display = 'none';
            // 隐藏时立即取消选中
            checkbox.checked = false;
        }
    });
    
    // 更新卡密计数
    document.getElementById('cardCount').textContent = visibleCount;
    
    // 重置全选复选框
    document.getElementById('selectAllCards').checked = false;
}

function toggleSelectAll() {
    const isChecked = document.getElementById('selectAllCards').checked;
    // 只选中当前可见的卡密
    document.querySelectorAll('.card-item').forEach(item => {
        const checkbox = item.querySelector('.card-checkbox');
        // 只有当卡密项可见时才改变其复选框状态
        if (item.style.display !== 'none') {
            checkbox.checked = isChecked;
        }
        // 隐藏的卡密不处理（保持原状）
    });
}

// 表单提交前，确保隐藏的卡密都被取消选中
document.getElementById('addCardsForm').addEventListener('submit', function(e) {
    document.querySelectorAll('.card-item').forEach(item => {
        const checkbox = item.querySelector('.card-checkbox');
        // 如果卡密项被隐藏，取消其选中状态
        if (item.style.display === 'none') {
            checkbox.checked = false;
        }
    });
});
</script>
<?php endif; ?>

<!-- 拉黑弹窗 -->
<div id="blacklistModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>🚫 添加到黑名单</h3>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('blacklistModal').style.display='none'" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="blacklist">
                <input type="hidden" name="batch_id" value="<?php echo $viewBatch['id'] ?? 0; ?>">
                
                <div class="form-group">
                    <label>拉黑范围</label>
                    <select name="blacklist_scope" class="form-control" id="blacklistScope">
                        <option value="batch">仅当前批次</option>
                        <option value="global">全局拉黑（所有批次）</option>
                    </select>
                    <small style="color: var(--gray-500);">全局拉黑将对所有领取批次生效</small>
                </div>
                
                <div class="form-group">
                    <label>拉黑类型</label>
                    <select name="blacklist_type" class="form-control" id="blacklistType">
                        <option value="1">设备ID</option>
                        <option value="2">IP地址</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>拉黑值</label>
                    <input type="text" name="blacklist_value" class="form-control" id="blacklistValue" required>
                </div>
                
                <div class="form-group">
                    <label>拉黑时长</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="number" name="expire_value" class="form-control" value="1" min="1" style="flex: 1;">
                        <select name="expire_unit" class="form-control" style="flex: 2;">
                            <option value="hour">小时</option>
                            <option value="day">天</option>
                            <option value="month">月</option>
                            <option value="year">年</option>
                            <option value="forever">永久</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>拉黑原因（可选）</label>
                    <input type="text" name="blacklist_reason" class="form-control" placeholder="如：恶意刷领取">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">确认拉黑</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('blacklistModal').style.display='none'">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateBatchExpireDays() {
    var type = document.getElementById('batchType').value;
    var input = document.getElementById('batchExpireInput');
    
    if (type === '1') {
        input.value = '30';
    } else if (type === '2') {
        input.value = '90';
    } else if (type === '3') {
        input.value = '365';
    } else if (type === '4') {
        input.value = '36500';
    }
}

function toggleSelectAll() {
    var selectAll = document.getElementById('selectAllCards');
    var checkboxes = document.querySelectorAll('.card-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = selectAll.checked;
    });
}

function showBlacklistModal(deviceId, ipAddress) {
    var modal = document.getElementById('blacklistModal');
    var typeSelect = document.getElementById('blacklistType');
    var valueInput = document.getElementById('blacklistValue');
    
    // 存储设备ID和IP地址供切换时使用
    modal.dataset.deviceId = deviceId || '';
    modal.dataset.ipAddress = ipAddress || '';
    
    // 默认选择设备ID并填充
    if (deviceId) {
        typeSelect.value = '1';
        valueInput.value = deviceId;
    } else if (ipAddress) {
        typeSelect.value = '2';
        valueInput.value = ipAddress;
    }
    
    modal.style.display = 'flex';
}

// 监听拉黑类型切换，自动更新拉黑值
document.getElementById('blacklistType').addEventListener('change', function() {
    var modal = document.getElementById('blacklistModal');
    var valueInput = document.getElementById('blacklistValue');
    var deviceId = modal.dataset.deviceId || '';
    var ipAddress = modal.dataset.ipAddress || '';
    
    if (this.value === '1') {
        valueInput.value = deviceId;
    } else if (this.value === '2') {
        valueInput.value = ipAddress;
    }
});

// 点击弹窗外部关闭
window.onclick = function(event) {
    if (event.target == document.getElementById('createBatchModal')) {
        document.getElementById('createBatchModal').style.display = 'none';
    }
    if (event.target == document.getElementById('addCardsModal')) {
        document.getElementById('addCardsModal').style.display = 'none';
    }
    if (event.target == document.getElementById('blacklistModal')) {
        document.getElementById('blacklistModal').style.display = 'none';
    }
}
</script>

<style>
/* 响应式优化 */
@media (max-width: 768px) {
    .panel-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px !important;
    }
    
    .panel-header h2 {
        font-size: 16px;
    }
    
    .panel-header .btn {
        width: 100% !important;
    }
    
    .search-box form {
        flex-direction: column !important;
        gap: 10px !important;
        width: 100% !important;
    }
    
    .search-box form select,
    .search-box form button {
        width: 100% !important;
    }
    
    .table-container {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    
    .table {
        min-width: 800px;
    }
    
    .modal {
        max-width: 100% !important;
        width: 100% !important;
        max-height: 100vh !important;
        border-radius: 0 !important;
        margin: 0 !important;
    }
    
    .modal-overlay {
        padding: 0 !important;
        align-items: flex-end !important;
    }
    
    .modal-body {
        max-height: calc(100vh - 100px) !important;
        overflow-y: auto !important;
    }
}
</style>

<?php renderFooter(); ?>
