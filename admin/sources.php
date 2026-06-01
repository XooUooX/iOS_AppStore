<?php
/**
 * 后台管理 - 软件源配置管理
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/common.php';

/**
 * 验证文件类型（检查文件头魔数）
 */
function validateFileType($filePath) {
    // 优先使用 mime_content_type（支持更广泛）
    if (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($filePath);
    } elseif (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
    } else {
        // 后备方案：使用 getimagesize 或检查文件扩展名
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo) {
            $mimeType = $imageInfo['mime'];
        } else {
            return false;
        }
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    return in_array($mimeType, $allowedTypes);
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

checkAdminLogin();

$db = Database::getInstance();
$message = '';

// 软件源配置项定义
$configFields = [
    'name' => ['title' => '站点名称', 'type' => 'string', 'group' => 'basic', 'tip' => '请填写站点名称，如：XXX软件源'],
    'version' => ['title' => '版本号', 'type' => 'string', 'group' => 'basic', 'tip' => '如：1.0.1'],
    'timezone' => ['title' => '时区', 'type' => 'string', 'group' => 'basic', 'tip' => '如：Asia/Shanghai'],
    'forbiddenip' => ['title' => '禁止IP', 'type' => 'text', 'group' => 'basic', 'tip' => '一行一条记录'],
    'sourceURL' => ['title' => '软件来源', 'type' => 'string', 'group' => 'basic', 'tip' => '软件来源地址'],
    'sourceicon' => ['title' => '源图标', 'type' => 'string', 'group' => 'basic', 'tip' => '源图标URL'],
    'payURL' => ['title' => '解锁发卡地址', 'type' => 'string', 'group' => 'basic', 'tip' => '发卡地址'],
    'unlockURL' => ['title' => '解锁接口地址', 'type' => 'string', 'group' => 'basic', 'tip' => '如用本后台卡密验证请填写源地址'],
    'identifier' => ['title' => '源识别标符', 'type' => 'string', 'group' => 'basic', 'tip' => '源识别标符'],
    'message' => ['title' => '软件源公告板', 'type' => 'text', 'group' => 'basic', 'tip' => '支持模板变量：[刷新时间]、[到期时间]、[软件个数]、[更新数量]、[拉黑数量]、[设备ID]、[IP地址]'],
    'opencry' => ['title' => '软件源加密', 'type' => 'switch', 'group' => 'basic', 'tip' => '开启加密'],
    'openblack' => ['title' => '自动拉黑添加者', 'type' => 'switch', 'group' => 'basic', 'tip' => '自动拉黑'],
    'openblack2' => ['title' => '自动拉黑破解者', 'type' => 'switch', 'group' => 'basic', 'tip' => '自动拉黑'],
    'api_key' => ['title' => 'API密钥', 'type' => 'string', 'group' => 'basic', 'tip' => '用于API接口认证，留空则不验证（不推荐）'],
];

// 保存配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    foreach ($configFields as $name => $field) {
        $value = $_POST[$name] ?? '';
        
        // 处理源图标上传
        if ($name === 'sourceicon' && isset($_FILES['sourceicon_file']) && $_FILES['sourceicon_file']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadIcon($_FILES['sourceicon_file']);
            if ($uploadResult['success']) {
                $value = $uploadResult['url'];
            }
        }
        
        // 使用INSERT ... ON DUPLICATE KEY UPDATE语法
        $db->query("INSERT INTO " . $db->getTable('config') . " (name, `group`, title, tip, type, value, content, rule, extend, createtime, updatetime) VALUES (?, ?, ?, ?, ?, ?, '', '', '', ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updatetime = VALUES(updatetime)", [
            $name,
            $field['group'],
            $field['title'],
            $field['tip'],
            $field['type'],
            $value,
            time(),
            time()
        ]);
    }
    
    $message = showMessage('软件源配置保存成功');
    logOperation('更新软件源配置', '更新系统配置');
}

// 获取当前配置
$currentConfig = [];
$configRows = $db->fetchAll("SELECT name, value FROM " . $db->getTable('config') . " WHERE `group` = 'basic'");
foreach ($configRows as $row) {
    $currentConfig[$row['name']] = $row['value'];
}

renderHeader('软件源配置', 'sources');
?>

<?php echo $message; ?>

<div class="panel">
    <div class="panel-header">
        <h2>软件源基础配置</h2>
    </div>
    <div class="panel-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="save_config" value="1">
            
            <div class="config-section">
                <h4 style="color: var(--primary); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--gray-200);">站点信息</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>站点名称</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($currentConfig['name']); ?>">
                        <div class="form-tip">显示在软件源顶部的名称</div>
                    </div>
                    <div class="form-group">
                        <label>版本号</label>
                        <input type="text" name="version" class="form-control" value="<?php echo htmlspecialchars($currentConfig['version']); ?>">
                    </div>
                    <div class="form-group">
                        <label>时区</label>
                        <input type="text" name="timezone" class="form-control" value="<?php echo htmlspecialchars($currentConfig['timezone']); ?>">
                    </div>
                </div>
            </div>

            <div class="config-section">
                <h4 style="color: var(--primary); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--gray-200);">软件源地址</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>软件来源</label>
                        <input type="text" name="sourceURL" class="form-control" value="<?php echo htmlspecialchars($currentConfig['sourceURL']); ?>">
                        <div class="form-tip">软件源完整URL，如：https://xxx.com/appstore</div>
                    </div>
                    <div class="form-group icon-upload-group">
                        <label>源图标</label>
                        <div class="icon-upload-wrapper">
                            <div class="icon-preview-container">
                                <div class="icon-preview">
                                    <?php if (!empty($currentConfig['sourceicon'])): ?>
                                    <img src="<?php echo htmlspecialchars($currentConfig['sourceicon']); ?>" alt="源图标">
                                    <?php else: ?>
                                    <span class="icon-placeholder">📷</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="icon-inputs">
                                <div class="icon-input-row">
                                    <input type="url" name="sourceicon" class="form-control image-url-input" value="<?php echo htmlspecialchars($currentConfig['sourceicon']); ?>" placeholder="输入URL或选择文件">
                                    <label class="btn btn-secondary file-select-btn">
                                        <input type="file" name="sourceicon_file" class="icon-file-input" accept="image/jpeg,image/png,image/gif,image/webp">
                                        📁 上传
                                    </label>
                                </div>
                                <div class="form-tip icon-tip">支持 JPG、PNG、GIF、WEBP，最大 5MB</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>解锁发卡地址</label>
                        <input type="text" name="payURL" class="form-control" value="<?php echo htmlspecialchars($currentConfig['payURL']); ?>">
                        <div class="form-tip">用户购买卡密的发卡地址</div>
                    </div>
                    <div class="form-group">
                        <label>解锁接口地址</label>
                        <input type="text" name="unlockURL" class="form-control" value="<?php echo htmlspecialchars($currentConfig['unlockURL']); ?>">
                        <div class="form-tip">如用本后台卡密验证，填写源地址即可</div>
                    </div>
                </div>
            </div>

            <div class="config-section">
                <h4 style="color: var(--primary); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--gray-200);">其他配置</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>源识别标符</label>
                        <input type="text" name="identifier" class="form-control" value="<?php echo htmlspecialchars($currentConfig['identifier']); ?>">
                        <div class="form-tip">用于识别软件源的唯一标识</div>
                    </div>
                    <div class="form-group">
                        <label>API密钥</label>
                        <input type="text" name="api_key" class="form-control" value="<?php echo htmlspecialchars($currentConfig['api_key']); ?>" placeholder="留空则不验证（不推荐）">
                        <div class="form-tip">用于API接口认证，建议使用强密钥。可使用以下命令生成：php -r "echo bin2hex(random_bytes(32));"</div>
                    </div>
                </div>
                <div class="form-group">
                    <label>禁止IP</label>
                    <textarea name="forbiddenip" class="form-control" rows="3"><?php echo htmlspecialchars($currentConfig['forbiddenip']); ?></textarea>
                    <div class="form-tip">一行一条IP记录</div>
                </div>
            </div>

            <div class="config-section">
                <h4 style="color: var(--primary); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--gray-200);">公告与开关</h4>
                <div class="form-group">
                    <label>软件源公告板</label>
                    <textarea name="message" class="form-control" rows="8"><?php echo htmlspecialchars($currentConfig['message']); ?></textarea>
                    <div class="form-tip">支持模板变量：[刷新时间]、[到期时间]、[软件个数]、[更新数量]、[拉黑数量]、[设备ID]、[IP地址]</div>
                </div>
                
                <div class="form-row" style="margin-top: 20px;">
                    <div class="form-group">
                        <label>软件源加密</label>
                        <label class="switch">
                            <input type="checkbox" name="opencry" value="1" <?php echo $currentConfig['opencry'] == '1' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>自动拉黑添加者</label>
                        <label class="switch">
                            <input type="checkbox" name="openblack" value="1" <?php echo $currentConfig['openblack'] == '1' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>自动拉黑破解者</label>
                        <label class="switch">
                            <input type="checkbox" name="openblack2" value="1" <?php echo $currentConfig['openblack2'] == '1' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">保存配置</button>
        </form>
    </div>
</div>

<style>
/* 软件源设置页响应式优化 */
/* 电脑端三个开关在一行 */
.form-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}
.form-row .form-group {
    flex: 1;
    min-width: 150px;
}
.form-row .icon-upload-group {
    flex: 1;
    min-width: 300px;
}
/* 图标上传区域在一行 */
.icon-upload-group .icon-upload-wrapper {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.icon-upload-group .icon-preview-container {
    flex-shrink: 0;
    width: 80px;
    height: 80px;
}
.icon-upload-group .icon-inputs {
    flex: 0 1 auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
}
.icon-input-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.icon-input-row .form-control {
    flex: 1;
    min-width: 200px;
}
.icon-input-row .file-select-btn {
    margin-top: 0;
    flex-shrink: 0;
    white-space: nowrap;
}

@media (max-width: 768px) {
    /* 手机端垂直堆叠 */
    .form-row {
        flex-direction: column;
        gap: 15px;
    }
    .form-row .form-group {
        min-width: 100%;
    }
    /* 图标上传区域手机端垂直布局 */
    .icon-upload-group .icon-upload-wrapper {
        flex-direction: column;
        gap: 15px;
    }
    .icon-upload-group .icon-preview-container {
        align-self: center;
    }
    .icon-upload-group .icon-inputs {
        width: 100%;
    }
    .icon-input-row .form-control {
        min-width: 100%;
    }
}

/* 图标预览 */
.icon-preview {
    width: 100%;
    height: 100%;
    border: 2px dashed var(--gray-300);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: var(--gray-50);
}
.icon-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.icon-placeholder {
    font-size: 32px;
    color: var(--gray-400);
}
/* 图标上传按钮 */
.icon-file-input {
    display: none;
}
.file-select-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    margin-top: 8px;
}
.icon-tip {
    margin-top: 8px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 源图标文件选择预览
    var sourceIconFile = document.querySelector('input[name="sourceicon_file"]');
    var sourceIconInput = document.querySelector('input[name="sourceicon"]');
    var sourceIconPreview = document.querySelector('.icon-upload-group .icon-preview');
    
    if (sourceIconFile && sourceIconPreview) {
        sourceIconFile.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                // 验证文件类型
                var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('只允许上传 JPG、PNG、GIF、WEBP 格式的图片');
                    e.target.value = '';
                    return;
                }
                // 验证文件大小 (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('文件大小不能超过 5MB');
                    e.target.value = '';
                    return;
                }
                // 显示预览
                var reader = new FileReader();
                reader.onload = function(e) {
                    sourceIconPreview.innerHTML = '<img src="' + e.target.result + '" alt="预览">';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // 监听URL输入变化，更新预览
        if (sourceIconInput) {
            sourceIconInput.addEventListener('input', function() {
                var url = this.value.trim();
                if (url) {
                    sourceIconPreview.innerHTML = '<img src="' + url + '" alt="预览">';
                } else {
                    sourceIconPreview.innerHTML = '<span class="icon-placeholder">📷</span>';
                }
            });
        }
    }
});
</script>

<?php renderFooter(); ?>
