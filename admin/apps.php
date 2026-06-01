<?php
/**
 * 后台管理 - 应用管理 
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/../includes/class.app.php';
require_once __DIR__ . '/common.php';

checkAdminLogin();

$app = new App();

/**
 * 验证文件类型（检查文件头魔数）
 */
function validateFileType($filePath) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    // 优先使用 finfo 扩展（如果可用）
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        return in_array($mimeType, $allowedTypes);
    }
    
    // 备用方案：使用 getimagesize()
    if (function_exists('getimagesize')) {
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo && isset($imageInfo['mime'])) {
            return in_array($imageInfo['mime'], $allowedTypes);
        }
    }
    
    // 最后的备用方案：使用 mime_content_type()
    if (function_exists('mime_content_type')) {
        $mimeType = @mime_content_type($filePath);
        if ($mimeType) {
            return in_array($mimeType, $allowedTypes);
        }
    }
    
    return false;
}

/**
 * 生成安全的文件名
 */
function generateSecureFilename($originalName) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($ext, $allowedExts)) {
        return false;
    }
    
    // 使用随机字符串而非时间戳，防止猜测
    $random = bin2hex(random_bytes(16));
    return $random . '.' . $ext;
}

/**
 * 上传图标文件
 * @param array $file $_FILES数组中的文件信息
 * @return array ['success' => bool, 'url' => string, 'error' => string]
 */
function uploadIcon($file) {
    $result = ['success' => false, 'url' => '', 'error' => ''];
    
    // 验证文件类型（通过文件头魔数，防止伪造）
    if (!validateFileType($file['tmp_name'])) {
        $result['error'] = '只允许上传 JPG、PNG、GIF、WEBP 格式的图片';
        return $result;
    }
    
    // 获取并验证文件扩展名
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowedExts)) {
        $result['error'] = '文件扩展名不合法';
        return $result;
    }
    
    // 检查文件大小 (最大 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        $result['error'] = '文件大小不能超过 5MB';
        return $result;
    }
    
    // 创建上传目录 /update/年/月/
    $year = date('Y');
    $month = date('n');
    $uploadDir = __DIR__ . '/../update/' . $year . '/' . $month;
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            $result['error'] = '创建上传目录失败';
            return $result;
        }
    }
    
    // 生成安全的随机文件名
    $filename = generateSecureFilename($file['name']);
    if (!$filename) {
        $result['error'] = '文件名生成失败';
        return $result;
    }
    
    $filepath = $uploadDir . '/' . $filename;
    
    // 移动上传文件
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // 设置文件权限，防止执行
        chmod($filepath, 0644);
        
        // 构建完整URL路径
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $fullUrl = $protocol . $_SERVER['HTTP_HOST'] . '/update/' . $year . '/' . $month . '/' . $filename;
        $result['success'] = true;
        $result['url'] = $fullUrl;
    } else {
        $result['error'] = '文件保存失败';
    }
    
    return $result;
}

// 添加应用
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $row = $_POST['row'] ?? [];
    
    // 处理图标上传
    $imageUrl = $row['image'] ?? '';
    if (isset($_FILES['icon_file']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadIcon($_FILES['icon_file']);
        if ($uploadResult['success']) {
            $imageUrl = $uploadResult['url'];
        }
    }
    
    $data = [
        'name' => $row['name'] ?? '',
        'type' => $row['type'] ?? 'default',
        'pid' => intval($row['pid'] ?? 0),
        'nickname' => $row['nickname'] ?? '',
        'keywords' => $row['keywords'] ?? '',
        'bt1a' => $row['bt1a'] ?? '',
        'bt1b' => $row['bt1b'] ?? '',
        'bt2a' => isset($row['bt2a']) ? intval(floatval($row['bt2a']) * 1024 * 1024) : 0,
        'bt2b' => intval($row['bt2b'] ?? 0),
        'flag' => intval($row['flag'] ?? 0),
        'image' => $imageUrl,
        'weigh' => intval($row['weigh'] ?? 0),
        'status' => $row['status'] ?? 'normal',
        'beizhu' => $row['beizhu'] ?? '',
        'updatetime' => time()
    ];
    
    if (empty($data['name']) || empty($data['bt1a'])) {
        redirectAfterPost('apps.php', '应用名称和下载地址不能为空', 'danger');
    } else {
        $app->create($data);
        logOperation('添加应用', "添加应用: {$data['name']}");
        redirectAfterPost('apps.php', '应用添加成功', 'success');
    }
}

// 编辑应用
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = intval($_POST['id'] ?? 0);
    $row = $_POST['row'] ?? [];
    
    // 处理图标上传
    $imageUrl = $row['image'] ?? '';
    if (isset($_FILES['icon_file']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadIcon($_FILES['icon_file']);
        if ($uploadResult['success']) {
            $imageUrl = $uploadResult['url'];
        }
    }
    
    $data = [
        'name' => $row['name'] ?? '',
        'type' => $row['type'] ?? 'default',
        'pid' => intval($row['pid'] ?? 0),
        'nickname' => $row['nickname'] ?? '',
        'keywords' => $row['keywords'] ?? '',
        'bt1a' => $row['bt1a'] ?? '',
        'bt1b' => $row['bt1b'] ?? '',
        'bt2a' => isset($row['bt2a']) ? intval(floatval($row['bt2a']) * 1024 * 1024) : 0,
        'bt2b' => intval($row['bt2b'] ?? 0),
        'flag' => intval($row['flag'] ?? 0),
        'image' => $imageUrl,
        'weigh' => intval($row['weigh'] ?? 0),
        'status' => $row['status'] ?? 'normal',
        'beizhu' => $row['beizhu'] ?? ''
    ];
    
    if (empty($data['name']) || empty($data['bt1a'])) {
        redirectAfterPost('apps.php', '应用名称和下载地址不能为空', 'danger');
    } else {
        $app->update($id, $data);
        logOperation('编辑应用', "编辑应用: {$data['name']}");
        redirectAfterPost('apps.php', '应用更新成功', 'success');
    }
}

// 删除应用
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $appInfo = $app->get($id);
    $app->delete($id);
    logOperation('删除应用', "删除应用: {$appInfo['name']}");
    redirectAfterPost('apps.php', '应用已删除', 'success');
}

// 启用/禁用
if (isset($_GET['enable'])) {
    $app->enable(intval($_GET['enable']));
    redirectAfterPost('apps.php', '应用已启用', 'success');
}
if (isset($_GET['disable'])) {
    $app->disable(intval($_GET['disable']));
    redirectAfterPost('apps.php', '应用已禁用', 'success');
}

// 保存搬运防护设置
if (isset($_GET['action']) && $_GET['action'] === 'save_protect' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    
    $protectData = [
        'protect_enabled' => $_POST['protect_enabled'] ?? '0',
        'protect_name' => $_POST['protect_name'] ?? '1',
        'protect_type' => intval($_POST['protect_type'] ?? 0),
        'protect_version' => $_POST['protect_version'] ?? '1',
        'protect_versionDescription' => $_POST['protect_versionDescription'] ?? '',
        'protect_downloadURL' => $_POST['protect_downloadURL'] ?? '',
        'protect_iconURL' => $_POST['protect_iconURL'] ?? '',
        'protect_tintColor' => $_POST['protect_tintColor'] ?? '1',
        'protect_size' => intval($_POST['protect_size'] ?? 1048576),
        'protect_isLanZouCloud' => intval($_POST['protect_isLanZouCloud'] ?? 0)
    ];
    
    foreach ($protectData as $name => $value) {
        $exists = $db->fetch("SELECT id FROM " . $db->getTable('config') . " WHERE name = ?", [$name]);
        if ($exists) {
            $db->update('config', ['value' => $value, 'updatetime' => time()], "name = ?", [$name]);
        } else {
            $db->insert('config', [
                'name' => $name,
                'value' => $value,
                'title' => '',
                'tip' => '',
                'type' => 'string',
                'content' => '',
                'rule' => '',
                'extend' => '',
                'createtime' => time(),
                'updatetime' => time()
            ]);
        }
    }
    
    logOperation('搬运防护', '更新搬运防护应用参数');
    redirectAfterPost('apps.php?action=protect', '搬运防护设置已保存', 'success');
}

// 获取列表
$page = intval($_GET['page'] ?? 1);
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$keyword = $_GET['keyword'] ?? '';
$list = $app->getList($page, 20, $type !== '' ? $type : null, $status !== '' ? $status : null, $keyword);

// 获取应用统计
$stats = $app->getStatistics();

renderHeader('应用管理', 'apps');
?>

<?php echo getPrgMessage(); ?>

<!-- 统计 -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['total']; ?></h3>
            <p>总应用数</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['active']; ?></h3>
            <p>启用应用</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['disabled']; ?></h3>
            <p>禁用应用</p>
        </div>
    </div>
</div>

<!-- 应用列表 -->
<div id="editAppModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 700px; width: 90%;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>编辑应用</h3>
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body">
            <form id="edit-form" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>类型</label>
                        <select name="row[type]" class="form-control">
                            <option value="default">默认</option>
                            <option value="1">应用</option>
                            <option value="2">游戏</option>
                            <option value="3">影音</option>
                            <option value="4">工具</option>
                            <option value="5">插件</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>权重</label>
                        <input type="number" name="row[weigh]" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>应用名称 *</label>
                        <input type="text" name="row[name]" class="form-control" placeholder="应用名称" required>
                    </div>
                    <div class="form-group">
                        <label>版本号 *</label>
                        <input type="text" name="row[nickname]" class="form-control" placeholder="如: 8.0.68" required>
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group icon-upload-group">
                        <label>图标</label>
                        <div class="icon-upload-wrapper">
                            <div class="icon-preview-container">
                                <div class="icon-preview">
                                    <span class="icon-placeholder">📷</span>
                                </div>
                            </div>
                            <div class="icon-inputs">
                                <input type="url" name="row[image]" class="form-control image-url-input" placeholder="输入URL或选择文件">
                                <label class="btn btn-secondary file-select-btn">
                                    <input type="file" name="icon_file" class="icon-file-input" accept="image/jpeg,image/png,image/gif,image/webp">
                                    📁 上传
                                </label>
                                <div class="form-tip icon-tip">支持 JPG、PNG、GIF、WEBP，最大 5MB</div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>文件大小 (MB)</label>
                        <input type="text" name="row[bt2a]" class="form-control" placeholder="如: 448.79">
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>按钮颜色</label>
                        <input type="text" name="row[bt1b]" class="form-control" placeholder="如: #667eea 或 018084">
                    </div>
                    <div class="form-group">
                        <label>是否蓝奏云链接</label>
                        <select name="row[flag]" class="form-control">
                            <option value="0">非蓝奏云</option>
                            <option value="1">蓝奏云</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>软件说明 / 版本描述</label>
                    <textarea name="row[keywords]" class="form-control" rows="4" placeholder="版本更新说明或软件描述" style="resize: vertical;"></textarea>
                </div>

                <div class="form-group">
                    <label>安装包地址 *</label>
                    <input type="url" name="row[bt1a]" class="form-control" placeholder="https://example.com/app.ipa" required>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>是否付费 / 锁定</label>
                        <select name="row[bt2b]" class="form-control">
                            <option value="0">免费 / 未锁定</option>
                            <option value="1">付费 / 锁定</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>状态</label>
                        <select name="row[status]" class="form-control">
                            <option value="normal">正常</option>
                            <option value="hidden">隐藏</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>备注</label>
                    <input type="text" name="row[beizhu]" class="form-control" placeholder="备注信息">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">确定</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 添加应用弹窗 -->
<div id="addAppModal" class="modal-overlay" style="display: none;">
    <div class="modal" style="max-width: 700px; width: 90%;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>添加应用</h3>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addAppModal').style.display='none'" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body">
            <form id="add-form" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>类型</label>
                        <select name="row[type]" class="form-control">
                            <option value="default">默认</option>
                            <option value="1">应用</option>
                            <option value="2">游戏</option>
                            <option value="3">影音</option>
                            <option value="4">工具</option>
                            <option value="5">插件</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>权重</label>
                        <input type="number" name="row[weigh]" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>应用名称 *</label>
                        <input type="text" name="row[name]" class="form-control" placeholder="应用名称" required>
                    </div>
                    <div class="form-group">
                        <label>版本号 *</label>
                        <input type="text" name="row[nickname]" class="form-control" placeholder="如: 8.0.68" required>
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group icon-upload-group">
                        <label>图标</label>
                        <div class="icon-upload-wrapper">
                            <div class="icon-preview-container">
                                <div class="icon-preview">
                                    <span class="icon-placeholder">📷</span>
                                </div>
                            </div>
                            <div class="icon-inputs">
                                <input type="url" name="row[image]" class="form-control image-url-input" placeholder="输入URL或选择文件">
                                <label class="btn btn-secondary file-select-btn">
                                    <input type="file" name="icon_file" class="icon-file-input" accept="image/jpeg,image/png,image/gif,image/webp">
                                    📁 上传
                                </label>
                                <div class="form-tip icon-tip">支持 JPG、PNG、GIF、WEBP，最大 5MB</div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>文件大小 (MB)</label>
                        <input type="text" name="row[bt2a]" class="form-control" placeholder="如: 448.79">
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>按钮颜色</label>
                        <input type="text" name="row[bt1b]" class="form-control" placeholder="如: #667eea 或 018084">
                    </div>
                    <div class="form-group">
                        <label>是否蓝奏云链接</label>
                        <select name="row[flag]" class="form-control">
                            <option value="0">非蓝奏云</option>
                            <option value="1">蓝奏云</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>软件说明 / 版本描述</label>
                    <textarea name="row[keywords]" class="form-control" rows="4" placeholder="版本更新说明或软件描述" style="resize: vertical;"></textarea>
                </div>

                <div class="form-group">
                    <label>安装包地址 *</label>
                    <input type="url" name="row[bt1a]" class="form-control" placeholder="https://example.com/app.ipa" required>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>是否付费 / 锁定</label>
                        <select name="row[bt2b]" class="form-control">
                            <option value="0">免费 / 未锁定</option>
                            <option value="1">付费 / 锁定</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>状态</label>
                        <select name="row[status]" class="form-control">
                            <option value="normal">正常</option>
                            <option value="hidden">隐藏</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>备注</label>
                    <input type="text" name="row[beizhu]" class="form-control" placeholder="备注信息">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">确定</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addAppModal').style.display='none'">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 应用列表 -->
<div class="panel">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>应用列表</h2>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-warning" onclick="document.getElementById('protectModal').style.display='flex'">🛡️ 搬运防护</button>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('addAppModal').style.display='flex'">+ 添加应用</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="search-box">
            <form method="GET" style="display: flex; gap: 12px; flex: 1; align-items: center;">
                <select name="type" class="form-control" style="width: 110px; flex-shrink: 0;">
                    <option value="">全部类型</option>
                    <option value="1" <?php echo $type === '1' ? 'selected' : ''; ?>>应用</option>
                    <option value="2" <?php echo $type === '2' ? 'selected' : ''; ?>>游戏</option>
                    <option value="3" <?php echo $type === '3' ? 'selected' : ''; ?>>影音</option>
                    <option value="4" <?php echo $type === '4' ? 'selected' : ''; ?>>工具</option>
                    <option value="5" <?php echo $type === '5' ? 'selected' : ''; ?>>插件</option>
                </select>
                <select name="status" class="form-control" style="width: 110px; flex-shrink: 0;">
                    <option value="">全部状态</option>
                    <option value="normal" <?php echo $status === 'normal' ? 'selected' : ''; ?>>正常</option>
                    <option value="hidden" <?php echo $status === 'hidden' ? 'selected' : ''; ?>>隐藏</option>
                </select>
                <input type="text" name="keyword" class="form-control" placeholder="搜索应用名称" value="<?php echo htmlspecialchars($keyword); ?>" style="flex: 1; min-width: 300px;">
                <button type="submit" class="btn btn-primary" style="flex-shrink: 0;">搜索</button>
            </form>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>图标</th>
                        <th>名称</th>
                        <th>类型</th>
                        <th>版本</th>
                        <th>大小</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list['list'] as $item): 
                        $sizeFormatted = $item['bt2a'] > 0 ? round($item['bt2a'] / 1024 / 1024, 2) . ' MB' : '-';
                        $typeName = [
                            'default' => '默认',
                            '1' => '应用',
                            '2' => '游戏', 
                            '3' => '影音',
                            '4' => '工具',
                            '5' => '插件'
                        ][$item['type']] ?? $item['type'];
                    ?>
                    <tr>
                        <td>
                            <?php if ($item['image']): ?>
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;" alt="图标">
                            <?php else: ?>
                            <div style="width: 40px; height: 40px; background: var(--gray-200); display: flex; align-items: center; justify-content: center; border-radius: 8px; color: var(--gray-500);">📱</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo $typeName; ?></td>
                        <td><?php echo htmlspecialchars($item['nickname'] ?: '-'); ?></td>
                        <td><?php echo $sizeFormatted; ?></td>
                        <td>
                            <?php if ($item['status'] == 'normal'): ?>
                            <span class="badge badge-success">正常</span>
                            <?php else: ?>
                            <span class="badge badge-secondary">隐藏</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-success" onclick='openEditModal(<?php echo json_encode($item); ?>)'>编辑</button>
                            <?php if ($item['status'] == 'hidden'): ?>
                            <a href="?enable=<?php echo $item['id']; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-primary">启用</a>
                            <?php else: ?>
                            <a href="?disable=<?php echo $item['id']; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-secondary">禁用</a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $item['id']; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除此应用？')">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php echo pagination($list['page'], $list['pages'], "?type={$type}&status={$status}&keyword=" . urlencode($keyword)); ?>
    </div>
</div>

<?php
// 获取搬运防护配置
$protectConfig = [];
try {
    $db = Database::getInstance();
    $protectRows = $db->fetchAll("SELECT name, value FROM " . $db->getTable('config') . " WHERE name LIKE 'protect_%'");
    foreach ($protectRows as $row) {
        $protectConfig[$row['name']] = $row['value'];
    }
} catch (Exception $e) {}

$protect_name = $protectConfig['protect_name'] ?? '未授权访问';
$protect_type = intval($protectConfig['protect_type'] ?? 0);
$protect_version = $protectConfig['protect_version'] ?? '1.0';
$protect_versionDescription = $protectConfig['protect_versionDescription'] ?? '⚠️ 请使用正版授权设备访问本软件源，如需授权请联系源主获取卡密。';
$protect_downloadURL = $protectConfig['protect_downloadURL'] ?? '';
$protect_iconURL = $protectConfig['protect_iconURL'] ?? '/uploads/Ban.png';
$protect_tintColor = $protectConfig['protect_tintColor'] ?? 'FF3B30';
$protect_size = intval($protectConfig['protect_size'] ?? 1048576);
$protect_isLanZouCloud = intval($protectConfig['protect_isLanZouCloud'] ?? 0);
$protect_enabled = $protectConfig['protect_enabled'] ?? '0';
?>

<!-- 搬运防护设置弹窗 -->
<div id="protectModal" class="modal-overlay" style="display: none;" onclick="if(event.target===this)closeProtectModal()">
    <div class="modal" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>🛡️ 搬运防护设置</h3>
            <button type="button" class="btn btn-secondary" onclick="closeProtectModal()" style="padding: 6px 12px;">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="?action=save_protect" id="protectForm">
                <div class="form-group" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--gray-200);">
                    <label style="display: inline-block; margin-right: 10px; font-weight: 600;">启用搬运防护</label>
                    <label class="switch">
                        <input type="checkbox" name="protect_enabled" value="1" <?php echo ($protect_enabled === '1') ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <small style="color: var(--gray-500); display: block; margin-top: 8px;">开启后，当未授权用户访问软件源时将显示防护应用</small>
                </div>
                
                <div class="form-row protect-form-row" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>应用名称</label>
                        <input type="text" name="protect_name" class="form-control" value="<?php echo htmlspecialchars($protect_name); ?>" placeholder="如: 未授权访问">
                    </div>
                    <div class="form-group">
                        <label>类型</label>
                        <select name="protect_type" class="form-control">
                            <option value="0" <?php echo $protect_type === 0 ? 'selected' : ''; ?>>0 - 默认</option>
                            <option value="1" <?php echo $protect_type === 1 ? 'selected' : ''; ?>>1 - 应用</option>
                            <option value="2" <?php echo $protect_type === 2 ? 'selected' : ''; ?>>2 - 游戏</option>
                            <option value="3" <?php echo $protect_type === 3 ? 'selected' : ''; ?>>3 - 影音</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>版本号</label>
                        <input type="text" name="protect_version" class="form-control" value="<?php echo htmlspecialchars($protect_version); ?>" placeholder="如: 1.0">
                    </div>
                    <div class="form-group">
                        <label>按钮颜色</label>
                        <input type="text" name="protect_tintColor" class="form-control" value="<?php echo htmlspecialchars($protect_tintColor); ?>" placeholder="如: FF3B30">
                    </div>
                </div>
                
                <div class="form-row protect-form-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>下载地址</label>
                        <input type="url" name="protect_downloadURL" class="form-control" value="<?php echo htmlspecialchars($protect_downloadURL); ?>" placeholder="https://example.com/app.ipa">
                    </div>
                    <div class="form-group">
                        <label>图标地址</label>
                        <input type="text" name="protect_iconURL" class="form-control" value="<?php echo htmlspecialchars($protect_iconURL); ?>" placeholder="如: https://example.com/icon.png 或 /uploads/icon.png">
                    </div>
                    <div class="form-group">
                        <label>文件大小</label>
                        <input type="number" name="protect_size" class="form-control" value="<?php echo $protect_size; ?>" placeholder="如: 1048576 (1MB)">
                    </div>
                </div>
                
                <div class="form-row protect-form-row" style="display: grid; grid-template-columns: 3fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>版本描述</label>
                        <textarea name="protect_versionDescription" class="form-control" rows="3" placeholder="版本更新说明" style="resize: vertical;"><?php echo htmlspecialchars($protect_versionDescription); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>是否蓝奏云</label>
                        <select name="protect_isLanZouCloud" class="form-control">
                            <option value="0" <?php echo $protect_isLanZouCloud === 0 ? 'selected' : ''; ?>>否</option>
                            <option value="1" <?php echo $protect_isLanZouCloud === 1 ? 'selected' : ''; ?>>是</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">保存设置</button>
                    <button type="button" class="btn btn-secondary" onclick="closeProtectModal()">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php renderFooter(); ?>

<style>
/* 模态框样式 */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    padding: 20px;
}

.modal {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

/* 图标上传区域样式 */
.icon-upload-wrapper {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.icon-preview-container {
    flex-shrink: 0;
}

.icon-preview {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 2px dashed #c0c4cc;
    transition: all 0.3s;
}

.icon-placeholder {
    font-size: 32px;
}

.icon-inputs {
    flex: 1;
    min-width: 0;
}

/* 输入框和按钮水平排列 */
.icon-input-row {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 6px;
}

.icon-input-row .image-url-input {
    flex: 1;
    margin-bottom: 0;
}

.icon-input-row .file-select-btn {
    flex-shrink: 0;
    white-space: nowrap;
}

.file-select-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    margin: 0;
    padding: 8px 16px;
    font-size: 14px;
    white-space: nowrap;
}

/* 手机端按钮优化 */
@media screen and (max-width: 768px) {
    .file-select-btn {
        padding: 10px 16px;
        font-size: 15px;
    }
    
    .icon-input-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .icon-input-row .image-url-input,
    .icon-input-row .file-select-btn {
        width: 100%;
    }
    
    .icon-input-row .file-select-btn {
        text-align: center;
        justify-content: center;
    }
}

.file-select-btn input {
    display: none;
}

.icon-tip {
    font-size: 12px;
    color: var(--gray-500);
}

/* ========== 响应式适配手机端 ========== */
@media screen and (max-width: 768px) {
    /* 图标上传区域优化 - 手机端垂直布局 */
    .icon-upload-wrapper {
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    
    .icon-preview {
        width: 100px;
        height: 100px;
    }
    
    .icon-inputs {
        width: 100%;
    }
    
    .icon-inputs .image-url-input,
    .icon-inputs .file-select-btn {
        width: 100%;
        text-align: center;
    }
    
    .icon-tip {
        text-align: center;
    }
    
    /* 搬运防护弹窗手机端优化 */
    #protectModal .modal {
        max-width: 100% !important;
        width: 100% !important;
        max-height: 100vh !important;
        border-radius: 0 !important;
        margin: auto !important;
    }
    
    #protectModal.modal-overlay {
        padding: 0 !important;
    }
    
    #protectModal .modal-body {
        padding: 16px !important;
        max-height: calc(100vh - 120px) !important;
        overflow-y: auto !important;
    }
    
    /* 添加/编辑应用弹窗优化 - 使用更高优先级 */
    #addAppModal .modal[style*="max-width: 700px"],
    #editAppModal .modal[style*="max-width: 700px"] {
        max-width: 100% !important;
        width: 100% !important;
        max-height: 100vh !important;
        border-radius: 0 !important;
        margin: auto !important;
        display: flex;
        flex-direction: column;
    }
    
    #addAppModal.modal-overlay,
    #editAppModal.modal-overlay {
        padding: 0 !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    #addAppModal .modal-body,
    #editAppModal .modal-body {
        padding: 16px !important;
        max-height: calc(100vh - 120px) !important;
        overflow-y: auto !important;
        flex: 1;
    }
    
    #addAppModal .form-row,
    #editAppModal .form-row {
        grid-template-columns: 1fr !important;
    }
    
    /* 按钮区域优化 */
    #add-form > div[style*="display: flex"],
    #edit-form > div[style*="display: flex"] {
        flex-direction: column !important;
        gap: 8px !important;
    }
    
    #add-form .btn,
    #edit-form .btn {
        width: 100%;
        margin-bottom: 0;
    }
    
    /* 搜索框手机端适配 */
    .search-box form {
        flex-wrap: wrap !important;
    }
    
    .search-box form select {
        flex: 1;
        min-width: 100px;
        width: auto !important;
    }
    
    .search-box form input[type="text"] {
        flex: 2;
        min-width: 150px;
        width: auto !important;
    }
    
    .search-box form button {
        flex: 0 0 auto;
    }
    
    /* 表格横向滚动 */
    .table-container {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    
    .table {
        min-width: 650px;
    }
    
    /* 操作按钮紧凑排列 */
    td .btn-sm {
        padding: 4px 8px !important;
        font-size: 12px !important;
    }
}

/* 更小屏幕的额外优化 */
@media screen and (max-width: 480px) {
    .icon-preview {
        width: 80px;
        height: 80px;
    }
    
    #addAppModal .modal-header,
    #editAppModal .modal-header,
    #protectModal .modal-header {
        padding: 16px;
    }
    
    #addAppModal .modal-header h3,
    #editAppModal .modal-header h3,
    #protectModal .modal-header h3 {
        font-size: 16px;
    }
}

/* 搬运防护设置 - 手机端适配 */
@media screen and (max-width: 768px) {
    .protect-form-row {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }
    
    .protect-form-row .form-group {
        margin-bottom: 0;
    }
    
    .protect-form-row label {
        font-size: 14px;
        margin-bottom: 6px;
        display: block;
    }
    
    .protect-form-row .form-control {
        width: 100%;
    }
}
</style>

<script>
function openEditModal(appData) {
    document.getElementById('editAppModal').style.display = 'flex';
    document.getElementById('editId').value = appData.id;
    document.querySelector('#edit-form [name="row[type]"]').value = appData.type || 'default';
    document.querySelector('#edit-form [name="row[name]"]').value = appData.name || '';
    document.querySelector('#edit-form [name="row[nickname]"]').value = appData.nickname || '';
    document.querySelector('#edit-form [name="row[weigh]"]').value = appData.weigh || 0;
    document.querySelector('#edit-form [name="row[image]"]').value = appData.image || '';
    document.querySelector('#edit-form [name="row[bt1b]"]').value = appData.bt1b || '';
    document.querySelector('#edit-form [name="row[bt2a]"]').value = appData.bt2a > 0 ? (appData.bt2a / 1024 / 1024).toFixed(2) : '';
    document.querySelector('#edit-form [name="row[keywords]"]').value = appData.keywords || '';
    document.querySelector('#edit-form [name="row[bt1a]"]').value = appData.bt1a || '';
    document.querySelector('#edit-form [name="row[flag]"]').value = appData.flag || 0;
    document.querySelector('#edit-form [name="row[bt2b]"]').value = appData.bt2b || 0;
    document.querySelector('#edit-form [name="row[status]"]').value = appData.status || 'normal';
    document.querySelector('#edit-form [name="row[beizhu]"]').value = appData.beizhu || '';
    
    // 显示图标预览
    var editPreview = document.querySelector('#edit-form .icon-preview');
    if (editPreview && appData.image) {
        editPreview.innerHTML = '<img src="' + appData.image + '" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display=\'none\'">';
        editPreview.style.border = '2px solid #667eea';
    } else if (editPreview) {
        editPreview.innerHTML = '<span style="font-size: 32px;">📷</span>';
        editPreview.style.border = '2px dashed #c0c4cc';
    }
    
    // 清空文件输入
    var editFileInput = document.querySelector('#edit-form .icon-file-input');
    if (editFileInput) {
        editFileInput.value = '';
    }
}

function closeEditModal() {
    document.getElementById('editAppModal').style.display = 'none';
}

// 点击弹窗外部关闭
window.onclick = function(event) {
    var addModal = document.getElementById('addAppModal');
    var editModal = document.getElementById('editAppModal');
    var protectModal = document.getElementById('protectModal');
    if (event.target == addModal) {
        addModal.style.display = 'none';
    }
    if (event.target == editModal) {
        editModal.style.display = 'none';
    }
    if (event.target == protectModal) {
        protectModal.style.display = 'none';
    }
}

// 关闭搬运防护弹窗
function closeProtectModal() {
    document.getElementById('protectModal').style.display = 'none';
}

// 文件预览功能
document.addEventListener('DOMContentLoaded', function() {
    // 优化图标上传区域布局 - 将输入框和按钮水平排列
    function optimizeIconUploadLayout() {
        var iconInputs = document.querySelectorAll('.icon-inputs');
        iconInputs.forEach(function(iconInput) {
            var urlInput = iconInput.querySelector('.image-url-input');
            var fileBtn = iconInput.querySelector('.file-select-btn');
            var tip = iconInput.querySelector('.icon-tip');
            
            if (urlInput && fileBtn && !iconInput.querySelector('.icon-input-row')) {
                // 创建水平排列的容器
                var row = document.createElement('div');
                row.className = 'icon-input-row';
                
                // 将输入框和按钮移到容器中
                row.appendChild(urlInput);
                row.appendChild(fileBtn);
                
                // 插入到icon-inputs的开头
                iconInput.insertBefore(row, tip);
            }
        });
    }
    
    // 执行布局优化
    optimizeIconUploadLayout();
    // 处理编辑表单的文件预览
    var editFileInput = document.querySelector('#edit-form .icon-file-input');
    var editUrlInput = document.querySelector('#edit-form .image-url-input');
    var editPreview = document.querySelector('#edit-form .icon-preview');
    
    if (editFileInput) {
        editFileInput.addEventListener('change', function() {
            var file = this.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    editPreview.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
                    editPreview.style.border = '2px solid #667eea';
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // 处理添加表单的文件预览
    var addFileInput = document.querySelector('#add-form .icon-file-input');
    var addUrlInput = document.querySelector('#add-form .image-url-input');
    var addPreview = document.querySelector('#add-form .icon-preview');
    
    if (addFileInput) {
        addFileInput.addEventListener('change', function() {
            var file = this.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    addPreview.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
                    addPreview.style.border = '2px solid #667eea';
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // URL输入时显示预览
    function setupUrlPreview(urlInput, previewDiv) {
        if (urlInput) {
            urlInput.addEventListener('input', function() {
                var url = this.value.trim();
                // 自动补全URL（如果不是完整URL，添加当前域名）
                if (url && !url.match(/^https?:\/\//i) && !url.startsWith('//')) {
                    // 如果是相对路径，自动补全域名
                    if (url.startsWith('/')) {
                        url = window.location.origin + url;
                    } else if (url.length > 0) {
                        // 如果不是以/开头，添加/update/前缀（上传图片默认路径）
                        url = window.location.origin + '/' + url;
                    }
                }
                if (this.value.trim()) {
                    previewDiv.innerHTML = '<img src="' + url + '" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display=\'none\'">';
                    previewDiv.style.border = '2px solid #667eea';
                } else {
                    previewDiv.innerHTML = '<span style="font-size: 32px;">📷</span>';
                    previewDiv.style.border = '2px dashed #c0c4cc';
                }
            });
            
            // 失去焦点时自动补全输入框的值
            urlInput.addEventListener('blur', function() {
                var url = this.value.trim();
                if (url && !url.match(/^https?:\/\//i) && !url.startsWith('//')) {
                    if (url.startsWith('/')) {
                        this.value = window.location.origin + url;
                    } else if (url.length > 0) {
                        this.value = window.location.origin + '/' + url;
                    }
                }
            });
        }
    }
    
    // 表单提交前自动补全所有图片URL
    function autoCompleteImageUrl(input) {
        var url = input.value.trim();
        if (url && !url.match(/^https?:\/\//i) && !url.startsWith('//')) {
            if (url.startsWith('/')) {
                input.value = window.location.origin + url;
            } else if (url.length > 0) {
                input.value = window.location.origin + '/' + url;
            }
        }
    }
    
    // 绑定表单提交事件
    var addForm = document.getElementById('add-form');
    var editForm = document.getElementById('edit-form');
    
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            autoCompleteImageUrl(addUrlInput);
        });
    }
    
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            autoCompleteImageUrl(editUrlInput);
        });
    }
    
    setupUrlPreview(editUrlInput, editPreview);
    setupUrlPreview(addUrlInput, addPreview);
});
</script>

<?php renderFooter(); ?>
