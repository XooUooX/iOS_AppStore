<?php
/**
 * 后台管理 - 软件源复制（我们不生产APP，我们只是APP的搬运工）
 * 复制其他软件源的应用到本地，支持编辑后导入
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/../includes/class.app.php';
require_once __DIR__ . '/common.php';

checkAdminLogin();

$db = Database::getInstance();

// AJAX请求处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    // 获取远程应用列表
    if ($action === 'fetch') {
        $apiUrl = trim($_POST['api_url'] ?? '');
        $udid = trim($_POST['udid'] ?? '');
        
        if (empty($apiUrl)) {
            echo json_encode(['code' => 0, 'msg' => 'API地址不能为空']);
            exit;
        }
        
        try {
            $appData = fetchRemoteAppData($apiUrl, $udid);
            if ($appData) {
                echo json_encode(['code' => 1, 'data' => $appData]);
            } else {
                echo json_encode(['code' => 0, 'msg' => '获取应用数据失败']);
            }
        } catch (Exception $e) {
            echo json_encode(['code' => 0, 'msg' => $e->getMessage()]);
        }
        exit;
    }
    
    // 导入单个应用
    if ($action === 'import_single') {
        $appData = json_decode($_POST['app'] ?? '[]', true);
        $bt2b = intval($_POST['bt2b'] ?? 0);
        $timeType = $_POST['time_type'] ?? 'current';
        $appType = $_POST['app_type'] ?? 'default';
        
        if (empty($appData)) {
            echo json_encode(['code' => 0, 'msg' => '没有应用数据']);
            exit;
        }
        
        $result = importSingleApp($db, $appData, $bt2b, $timeType, $appType);
        echo json_encode($result);
        exit;
    }
    
    // 批量导入
    if ($action === 'import_batch') {
        $apps = json_decode($_POST['apps'] ?? '[]', true);
        $bt2b = intval($_POST['bt2b'] ?? 0);
        $startWeight = intval($_POST['start_weight'] ?? 0);
        $timeType = $_POST['time_type'] ?? 'current';
        $appType = $_POST['app_type'] ?? 'default';
        
        if (empty($apps)) {
            echo json_encode(['code' => 0, 'msg' => '没有要导入的应用']);
            exit;
        }
        
        $result = importBatchApps($db, $apps, $bt2b, $startWeight, $timeType, $appType);
        echo json_encode($result);
        exit;
    }
}

function fetchRemoteAppData($apiUrl, $udid = '') {
    $requestUrl = $apiUrl;
    if (!empty($udid)) {
        $query = parse_url($requestUrl, PHP_URL_QUERY);
        $requestUrl .= $query ? "&udid={$udid}" : "?udid={$udid}";
    }
    
    $ch = curl_init();
    
    $sslVerify = getenv('CURL_SSL_VERIFY') !== 'false';
    
    $curlOptions = [
        CURLOPT_URL => $requestUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_SSL_VERIFYPEER => $sslVerify,
        CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X)',
        CURLOPT_HTTPHEADER => ['Accept: application/json, text/plain, */*', 'Accept-Language: zh-CN,zh;q=0.9']
    ];
    
    if ($sslVerify) {
        $caBundle = ini_get('curl.cainfo');
        if (!$caBundle || !@file_exists($caBundle)) {
            $caBundle = __DIR__ . '/../cacert.pem';
            if (!@file_exists($caBundle)) {
                $caBundle = '/etc/ssl/certs/ca-certificates.crt';
            }
        }
        if (@file_exists($caBundle)) {
            $curlOptions[CURLOPT_CAINFO] = $caBundle;
        }
    }
    
    curl_setopt_array($ch, $curlOptions);
    
    $response = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($curlErrNo > 0) {
        $errorMsg = '请求失败：' . curl_strerror($curlErrNo);
        if ($curlErrNo == 60 || $curlErrNo == 51 || $curlErrNo == 53) {
            $errorMsg .= ' (SSL证书验证失败)。如果是自签名证书，请在 .env 中设置 CURL_SSL_VERIFY=false';
        }
        throw new Exception($errorMsg);
    }
    if ($httpCode !== 200) throw new Exception('HTTP错误：' . $httpCode);
    if (empty($response)) throw new Exception('API返回空内容');
    
    if (substr($response, 0, 3) == pack("CCC", 0xEF, 0xBB, 0xBF)) {
        $response = substr($response, 3);
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $data = json_decode($matches[0], true);
        }
    }
    
    return adaptAppData($data);
}

function adaptAppData($data) {
    if (!$data) return ['apps' => [], 'total' => 0];
    
    if (isset($data['appstore']) && is_string($data['appstore'])) {
        $decoded = json_decode($data['appstore'], true);
        if ($decoded) $data = $decoded;
    }
    
    if (!isset($data['apps'])) {
        $data['apps'] = isset($data['name']) ? [$data] : [];
    }
    
    $data['apps'] = array_map(function($app) {
        return [
            'name' => $app['name'] ?? '未知应用',
            'version' => $app['version'] ?? ($app['nickname'] ?? '1.0'),
            'versionDate' => $app['versionDate'] ?? ($app['updatetime'] ?? ''),
            'iconURL' => $app['iconURL'] ?? ($app['image'] ?? ''),
            'type' => $app['type'] ?? '1',
            'lock' => $app['lock'] ?? ($app['bt2b'] ?? '0'),
            'downloadURL' => $app['downloadURL'] ?? ($app['bt1a'] ?? ''),
            'tintColor' => $app['tintColor'] ?? ($app['bt1b'] ?? ''),
            'size' => $app['size'] ?? ($app['bt2a'] ?? '0'),
            'isLanZouCloud' => $app['isLanZouCloud'] ?? ($app['flag'] ?? '0'),
            'versionDescription' => $app['versionDescription'] ?? ($app['keywords'] ?? ''),
            'bundleId' => $app['bundleId'] ?? ''
        ];
    }, $data['apps']);
    
    $data['total'] = count($data['apps']);
    return $data;
}

function importSingleApp($db, $app, $bt2b, $timeType, $appType) {
    try {
        $exist = $db->fetch("SELECT id FROM " . $db->getTable('category') . " WHERE name = ? AND nickname = ?", [$app['name'], $app['version']]);
        if ($exist) return ['code' => 2, 'msg' => '应用已存在'];
        
        $updateTime = time();
        if ($timeType === 'original' && !empty($app['versionDate'])) {
            $ts = strtotime($app['versionDate']);
            if ($ts !== false) $updateTime = $ts;
        }
        
        $desc = str_replace(['\n', '@@@'], ["\n", "\n"], $app['versionDescription'] ?? '');
        $size = intval($app['size']);
        if ($size > 0 && $size < 1024) $size = $size * 1024 * 1024;
        
        $maxWeight = $db->fetch("SELECT MAX(weigh) as mw FROM " . $db->getTable('category') . "")['mw'] ?? 0;
        
        $db->insert('category', [
            'type' => $appType, 'name' => $app['name'], 'nickname' => $app['version'],
            'image' => $app['iconURL'], 'keywords' => $desc, 'bt1a' => $app['downloadURL'],
            'bt1b' => $app['tintColor'], 'bt2a' => $size, 'bt2b' => $bt2b,
            'flag' => intval($app['isLanZouCloud']), 'status' => 'normal',
            'weigh' => $maxWeight + 1, 'pid' => 0, 'updatetime' => $updateTime, 'createtime' => time()
        ]);
        
        return ['code' => 1, 'msg' => '导入成功'];
    } catch (Exception $e) {
        return ['code' => 0, 'msg' => '导入失败：' . $e->getMessage()];
    }
}

function importBatchApps($db, $apps, $bt2b, $startWeight, $timeType, $appType) {
    $success = 0; $duplicate = 0; $failed = 0;
    $maxWeight = $db->fetch("SELECT MAX(weigh) as mw FROM " . $db->getTable('category') . "")['mw'] ?? 0;
    $currentWeight = $startWeight > 0 ? $startWeight : ($maxWeight + 1);
    
    foreach ($apps as $app) {
        try {
            $exist = $db->fetch("SELECT id FROM " . $db->getTable('category') . " WHERE name = ? AND nickname = ?", [$app['name'], $app['version']]);
            if ($exist) { $duplicate++; continue; }
            
            $updateTime = time();
            if ($timeType === 'original' && !empty($app['versionDate'])) {
                $ts = strtotime($app['versionDate']);
                if ($ts !== false) $updateTime = $ts;
            }
            
            $desc = str_replace(['\n', '@@@'], ["\n", "\n"], $app['versionDescription'] ?? '');
            $size = intval($app['size']);
            if ($size > 0 && $size < 1024) $size = $size * 1024 * 1024;
            
            $db->insert('category', [
                'type' => $appType, 'name' => $app['name'], 'nickname' => $app['version'],
                'image' => $app['iconURL'], 'keywords' => $desc, 'bt1a' => $app['downloadURL'],
                'bt1b' => $app['tintColor'], 'bt2a' => $size, 'bt2b' => $bt2b,
                'flag' => intval($app['isLanZouCloud']), 'status' => 'normal',
                'weigh' => $currentWeight++, 'pid' => 0, 'updatetime' => $updateTime, 'createtime' => time()
            ]);
            $success++;
        } catch (Exception $e) { $failed++; }
    }
    
    $msg = "成功导入 {$success} 个应用";
    if ($duplicate > 0) $msg .= "，跳过 {$duplicate} 个重复";
    if ($failed > 0) $msg .= "，失败 {$failed} 个";
    return ['code' => 1, 'msg' => $msg];
}

renderHeader('软件源复制', 'copy_source');
?>

<style>
.app-row:hover { background: var(--gray-50); }
.app-icon-small { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: var(--gray-200); }
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; padding: 20px; }
.modal { background: white; border-radius: 12px; width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.modal-header { padding: 20px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; }
.modal-body { padding: 20px; }
.modal-footer { padding: 15px 20px; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 10px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid var(--gray-300); border-radius: 8px; font-size: 14px; font-family: inherit; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); }
textarea { resize: vertical; min-height: 80px; }
.selected-row { background: rgba(99, 102, 241, 0.1) !important; }
.required::after { content: ' *'; color: #e74c3c; }

.search-box {
    position: relative;
    display: inline-block;
    width: 100%;
    max-width: 350px;
}

.search-box input {
    width: 100%;
    padding: 10px 12px 10px 40px;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    font-size: 14px;
    background: var(--gray-50);
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.search-box input::placeholder {
    color: var(--gray-400);
}
</style>

<div class="panel">
    <div class="panel-header">
        <h2>我们不生产APP，我们只是APP的搬运工</h2>
    </div>
    <div class="panel-body">
        <div class="form-group">
            <label>UDID（可选，用于解锁付费应用）</label>
            <input type="text" id="udid" class="form-control" placeholder="输入设备UDID">
        </div>
        
        <div class="form-group">
            <label>目标软件源API地址</label>
            <div style="display: flex; gap: 10px;">
                <input type="text" id="api_url" class="form-control" placeholder="例如：https://example.com/appstore" list="api-history" style="flex: 1;">
                <datalist id="api-history"></datalist>
                <button type="button" class="btn btn-secondary" onclick="clearApiHistory()">清除历史</button>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>应用类型</label>
                <select id="app_type" class="form-control">
                    <option value="default">默认</option>
                    <option value="1">应用</option>
                    <option value="2">游戏</option>
                    <option value="3">影音</option>
                    <option value="4">工具</option>
                    <option value="5">插件</option>
                </select>
            </div>
            <div class="form-group">
                <label>付费设置</label>
                <select id="bt2b" class="form-control">
                    <option value="0">免费</option>
                    <option value="1">付费</option>
                </select>
            </div>
            <div class="form-group">
                <label>时间设置</label>
                <select id="time_type" class="form-control">
                    <option value="current">使用当前时间</option>
                    <option value="original">使用原始时间</option>
                </select>
            </div>
            <div class="form-group">
                <label>起始权重</label>
                <input type="number" id="start_weight" class="form-control" placeholder="默认最大+1" min="1">
            </div>
        </div>
        
        <div class="form-group">
            <button type="button" class="btn btn-success" onclick="fetchApps()">获取应用列表</button>
            <button type="button" class="btn btn-primary" onclick="selectAll()" id="selectAllBtn">全选</button>
            <button type="button" class="btn btn-warning" onclick="openBatchImportModal()" id="batchImportBtn" style="display:none;">批量导入选中</button>
        </div>
        
        <div class="search-box" style="margin-bottom: 20px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--gray-400); pointer-events: none;">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input type="text" id="search_input" class="form-control" placeholder="搜索应用名称、版本号..." onkeyup="handleSearch()" style="padding-left: 40px; background: var(--gray-50); border: 1px solid var(--gray-300); max-width: 350px;">
        </div>
        
        <div id="app-result"></div>
    </div>
</div>

<!-- 单条导入编辑模态框 -->
<div id="singleImportModal" class="modal-overlay" onclick="if(event.target===this)closeSingleModal()">
    <div class="modal">
        <div class="modal-header">
            <h3>编辑并导入应用</h3>
            <button type="button" class="btn btn-secondary" onclick="closeSingleModal()">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="edit_index">
            <div class="form-row">
                <div class="form-group">
                    <label class="required">应用名称</label>
                    <input type="text" id="edit_name" placeholder="应用名称">
                </div>
                <div class="form-group">
                    <label class="required">版本号</label>
                    <input type="text" id="edit_version" placeholder="如: 8.0.68">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>图标 URL</label>
                    <input type="url" id="edit_iconURL" placeholder="https://example.com/icon.png">
                </div>
                <div class="form-group">
                    <label>文件大小 (字节)</label>
                    <input type="number" id="edit_size" placeholder="如: 448790528">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>按钮颜色</label>
                    <input type="text" id="edit_tintColor" placeholder="如: #667eea 或 018084">
                </div>
                <div class="form-group">
                    <label>是否蓝奏云链接</label>
                    <select id="edit_isLanZouCloud">
                        <option value="0">非蓝奏云</option>
                        <option value="1">蓝奏云</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>应用类型</label>
                    <select id="edit_type">
                        <option value="default">默认</option>
                        <option value="1">应用</option>
                        <option value="2">游戏</option>
                        <option value="3">影音</option>
                        <option value="4">工具</option>
                        <option value="5">插件</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>付费/锁定</label>
                    <select id="edit_lock">
                        <option value="0">免费 / 未锁定</option>
                        <option value="1">付费 / 锁定</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="required">安装包下载地址</label>
                <input type="url" id="edit_downloadURL" placeholder="https://example.com/app.ipa">
            </div>
            <div class="form-group">
                <label>版本描述 / 更新说明</label>
                <textarea id="edit_versionDescription" placeholder="版本更新说明或软件描述"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeSingleModal()">取消</button>
            <button type="button" class="btn btn-primary" onclick="saveSingleImport()">导入应用</button>
        </div>
    </div>
</div>

<!-- 批量导入确认模态框 -->
<div id="batchImportModal" class="modal-overlay" onclick="if(event.target===this)closeBatchModal()">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3>批量导入确认</h3>
            <button type="button" class="btn btn-secondary" onclick="closeBatchModal()">✕</button>
        </div>
        <div class="modal-body">
            <p>即将导入 <strong id="batchCount">0</strong> 个应用</p>
            
            <div class="form-row" style="margin-top: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label>批量应用类型</label>
                    <select id="batch_app_type" class="form-control">
                        <option value="default">默认</option>
                        <option value="1">应用</option>
                        <option value="2">游戏</option>
                        <option value="3">影音</option>
                        <option value="4">工具</option>
                        <option value="5">插件</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>批量付费设置</label>
                    <select id="batch_lock" class="form-control">
                        <option value="0">免费 / 未锁定</option>
                        <option value="1">付费 / 锁定</option>
                    </select>
                </div>
            </div>
            
            <div id="batchAppList" style="max-height: 250px; overflow-y: auto; margin-top: 15px;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeBatchModal()">取消</button>
            <button type="button" class="btn btn-primary" onclick="confirmBatchImport()">确认导入</button>
        </div>
    </div>
</div>

<script>
var allApps = [];
var filteredApps = [];
var selectedIndices = new Set();
var currentPage = 1;
var pageSize = 20;
var totalPages = 1;
var filterType = 'all'; // all, free, paid

function loadApiHistory() {
    var history = localStorage.getItem('api_history');
    if (history) {
        var urls = JSON.parse(history);
        var datalist = document.getElementById('api-history');
        datalist.innerHTML = '';
        urls.forEach(function(url) {
            var option = document.createElement('option');
            option.value = url;
            datalist.appendChild(option);
        });
    }
}

function saveApiHistory(url) {
    if (!url) return;
    var history = localStorage.getItem('api_history');
    var urls = history ? JSON.parse(history) : [];
    urls = urls.filter(function(item) { return item !== url; });
    urls.unshift(url);
    if (urls.length > 10) urls = urls.slice(0, 10);
    localStorage.setItem('api_history', JSON.stringify(urls));
    loadApiHistory();
}

function clearApiHistory() {
    localStorage.removeItem('api_history');
    document.getElementById('api-history').innerHTML = '';
}

function fetchApps() {
    var apiUrl = document.getElementById('api_url').value.trim();
    var udid = document.getElementById('udid').value.trim();
    
    if (!apiUrl) { alert('请输入API地址'); return; }
    saveApiHistory(apiUrl);
    
    document.getElementById('app-result').innerHTML = '<div class="alert alert-info">正在加载...</div>';
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=fetch&api_url=' + encodeURIComponent(apiUrl) + '&udid=' + encodeURIComponent(udid)
    })
    .then(function(res) {
        if (!res.ok) {
            throw new Error('HTTP ' + res.status + ': ' + res.statusText);
        }
        return res.text();
    })
    .then(function(text) {
        try {
            var res = JSON.parse(text);
            if (res.code === 1) {
                allApps = res.data.apps || [];
                filterType = 'all';
                applyFilter();
                selectedIndices.clear();
                renderAppTable();
            } else {
                var errorMsg = res.msg || '获取失败';
                document.getElementById('app-result').innerHTML = '<div class="alert alert-danger" style="white-space: pre-wrap; word-break: break-word;">' + escapeHtml(errorMsg) + '</div>';
            }
        } catch (parseError) {
            console.error('JSON 解析错误:', parseError);
            console.error('响应内容:', text);
            var errorMsg = '服务器返回了无效的 JSON 响应。\n\n可能的原因：\n1. 服务器出现 PHP 错误\n2. API 地址不正确\n3. 服务器返回了 HTML 错误页面\n\n请检查浏览器控制台查看完整的响应内容。';
            document.getElementById('app-result').innerHTML = '<div class="alert alert-danger" style="white-space: pre-wrap; word-break: break-word;">请求失败：' + escapeHtml(errorMsg) + '</div>';
        }
    })
    .catch(function(err) {
        console.error('获取应用列表错误:', err);
        var errorMsg = err.message;
        if (errorMsg.includes('Failed to fetch')) {
            errorMsg = '网络请求失败，可能原因：\n1. API 地址无法访问\n2. 网络连接问题\n3. 服务器返回错误\n\n请检查 API 地址是否正确，或查看浏览器控制台获取更多信息。';
        }
        document.getElementById('app-result').innerHTML = '<div class="alert alert-danger" style="white-space: pre-wrap; word-break: break-word;">请求失败：' + escapeHtml(errorMsg) + '</div>';
    });
}

function escapeHtml(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function applyFilter() {
    if (filterType === 'all') {
        filteredApps = allApps;
    } else if (filterType === 'free') {
        filteredApps = allApps.filter(function(app) { return app.lock !== '1' && app.lock !== 1; });
    } else if (filterType === 'paid') {
        filteredApps = allApps.filter(function(app) { return app.lock === '1' || app.lock === 1; });
    }
    currentPage = 1;
}

function setFilter(type) {
    filterType = type;
    applyFilter();
    renderAppTable();
}

function handleSearch() {
    var searchText = document.getElementById('search_input').value.trim().toLowerCase();
    
    if (searchText === '') {
        filteredApps = allApps;
    } else {
        filteredApps = allApps.filter(function(app) {
            var name = (app.name || '').toLowerCase();
            var version = (app.version || '').toLowerCase();
            var description = (app.versionDescription || '').toLowerCase();
            
            return name.includes(searchText) || version.includes(searchText) || description.includes(searchText);
        });
    }
    
    currentPage = 1;
    renderAppTable();
}

function renderAppTable() {
    // 计算分页
    totalPages = Math.ceil(filteredApps.length / pageSize);
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    
    var freeCount = allApps.filter(function(app) { return app.lock !== '1' && app.lock !== 1; }).length;
    var paidCount = allApps.filter(function(app) { return app.lock === '1' || app.lock === 1; }).length;
    
    var html = '<div class="stats-grid" style="margin-bottom: 20px;">' +
        '<div class="stat-card"><h3>' + allApps.length + '</h3><p>总应用数</p></div>' +
        '<div class="stat-card"><h3>' + freeCount + '</h3><p>免费应用</p></div>' +
        '<div class="stat-card"><h3>' + paidCount + '</h3><p>付费应用</p></div>' +
        '<div class="stat-card"><h3 id="selectedCount">0</h3><p>已选择</p></div>' +
        '</div>';
    
    // 筛选按钮
    html += '<div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap;">' +
        '<button type="button" class="btn ' + (filterType === 'all' ? 'btn-primary' : 'btn-secondary') + '" onclick="setFilter(\'all\')">全部 (' + allApps.length + ')</button>' +
        '<button type="button" class="btn ' + (filterType === 'free' ? 'btn-success' : 'btn-secondary') + '" onclick="setFilter(\'free\')" ' + (freeCount === 0 ? 'disabled' : '') + '>免费 (' + freeCount + ')</button>' +
        '<button type="button" class="btn ' + (filterType === 'paid' ? 'btn-warning' : 'btn-secondary') + '" onclick="setFilter(\'paid\')" ' + (paidCount === 0 ? 'disabled' : '') + '>付费 (' + paidCount + ')</button>' +
        '</div>';
    
    // 如果筛选结果为空，显示提示并返回
    if (filteredApps.length === 0) {
        html += '<div class="alert alert-warning" style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; color: #856404;">' +
            '该分类暂无应用，请选择其他筛选条件' +
            '</div>';
        document.getElementById('app-result').innerHTML = html;
        updateSelectionUI();
        return;
    }
    
    var start = (currentPage - 1) * pageSize;
    var end = Math.min(start + pageSize, filteredApps.length);
    var pageApps = filteredApps.slice(start, end);
    
    html += '<div class="table-container"><table class="table">';
    html += '<thead><tr><th><input type="checkbox" id="checkAll" onchange="toggleAll()"></th><th>图标</th><th>名称</th><th>版本</th><th>时间</th><th>描述</th><th>付费设置</th><th>操作</th></tr></thead><tbody>';
    
    pageApps.forEach(function(app, idx) {
        var index = filteredApps.indexOf(app);
        var isSelected = selectedIndices.has(index);
        var lockText = (app.lock === '1' || app.lock === 1) ? '<span style="color:#e74c3c;">付费</span>' : '<span style="color:#27ae60;">免费</span>';
        var versionDate = app.versionDate ? new Date(app.versionDate).toLocaleDateString('zh-CN') : '-';
        html += '<tr class="app-row ' + (isSelected ? 'selected-row' : '') + '">' +
            '<td><input type="checkbox" class="app-check" data-index="' + index + '" ' + (isSelected ? 'checked' : '') + ' onchange="toggleSelection(' + index + ')"></td>' +
            '<td><img src="' + (app.iconURL || '') + '" class="app-icon-small" onerror="this.style.display=\'none\'"></td>' +
            '<td>' + (app.name || '未知') + '</td>' +
            '<td>' + (app.version || '-') + '</td>' +
            '<td style="font-size: 12px; color: #666;">' + versionDate + '</td>' +
            '<td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">' + ((app.versionDescription || '').substring(0, 60)) + '</td>' +
            '<td>' + lockText + '</td>' +
            '<td><button type="button" class="btn btn-sm btn-success" onclick="openSingleImportModal(' + index + ')">导入</button></td>' +
            '</tr>';
    });
    
    html += '</tbody></table></div>';
    
    // 分页控件
    html += '<div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px; flex-wrap: wrap;">';
    html += '<button type="button" class="btn btn-sm btn-secondary" onclick="goToPage(1)" ' + (currentPage === 1 ? 'disabled' : '') + '>首页</button>';
    html += '<button type="button" class="btn btn-sm btn-secondary" onclick="goToPage(' + (currentPage - 1) + ')" ' + (currentPage === 1 ? 'disabled' : '') + '>上一页</button>';
    html += '<span style="padding: 0 10px;">第 ' + currentPage + ' / ' + totalPages + ' 页</span>';
    html += '<button type="button" class="btn btn-sm btn-secondary" onclick="goToPage(' + (currentPage + 1) + ')" ' + (currentPage === totalPages ? 'disabled' : '') + '>下一页</button>';
    html += '<button type="button" class="btn btn-sm btn-secondary" onclick="goToPage(' + totalPages + ')" ' + (currentPage === totalPages ? 'disabled' : '') + '>末页</button>';
    html += '<select onchange="changePageSize(this.value)" style="padding: 5px 10px; border: 1px solid var(--gray-300); border-radius: 6px; margin-left: 10px;">';
    html += '<option value="10"' + (pageSize === 10 ? ' selected' : '') + '>10条/页</option>';
    html += '<option value="20"' + (pageSize === 20 ? ' selected' : '') + '>20条/页</option>';
    html += '<option value="50"' + (pageSize === 50 ? ' selected' : '') + '>50条/页</option>';
    html += '<option value="100"' + (pageSize === 100 ? ' selected' : '') + '>100条/页</option>';
    html += '</select>';
    html += '</div>';
    
    document.getElementById('app-result').innerHTML = html;
    updateSelectionUI();
}

function goToPage(page) {
    currentPage = page;
    renderAppTable();
}

function changePageSize(size) {
    pageSize = parseInt(size);
    currentPage = 1;
    renderAppTable();
}

function toggleSelection(index) {
    if (selectedIndices.has(index)) selectedIndices.delete(index);
    else selectedIndices.add(index);
    updateSelectionUI();
}

function toggleAll() {
    var checkAll = document.getElementById('checkAll').checked;
    if (checkAll) {
        for (var i = 0; i < allApps.length; i++) selectedIndices.add(i);
    } else {
        selectedIndices.clear();
    }
    renderAppTable();
}

function selectAll() {
    var btn = document.getElementById('selectAllBtn');
    var isSelectAll = btn.textContent === '全选';
    if (isSelectAll) {
        for (var i = 0; i < allApps.length; i++) selectedIndices.add(i);
        btn.textContent = '取消全选';
    } else {
        selectedIndices.clear();
        btn.textContent = '全选';
    }
    renderAppTable();
}

function updateSelectionUI() {
    var count = selectedIndices.size;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('batchImportBtn').style.display = count > 0 ? 'inline-block' : 'none';
    document.getElementById('selectAllBtn').textContent = count === allApps.length && count > 0 ? '取消全选' : '全选';
}

function openSingleImportModal(index) {
    var app = filteredApps[index];
    
    // 找到该应用在 allApps 中的真实索引
    var realIndex = allApps.indexOf(app);
    
    document.getElementById('edit_index').value = realIndex;
    document.getElementById('edit_name').value = app.name || '';
    document.getElementById('edit_version').value = app.version || '';
    document.getElementById('edit_iconURL').value = app.iconURL || '';
    document.getElementById('edit_size').value = app.size || '';
    document.getElementById('edit_tintColor').value = app.tintColor || '';
    document.getElementById('edit_isLanZouCloud').value = app.isLanZouCloud || '0';
    document.getElementById('edit_type').value = app.type || 'default';
    document.getElementById('edit_lock').value = app.lock || '0';
    document.getElementById('edit_downloadURL').value = app.downloadURL || '';
    document.getElementById('edit_versionDescription').value = app.versionDescription || '';
    document.getElementById('singleImportModal').style.display = 'flex';
}

function closeSingleModal() {
    document.getElementById('singleImportModal').style.display = 'none';
}

function saveSingleImport() {
    var index = parseInt(document.getElementById('edit_index').value);
    var app = {
        name: document.getElementById('edit_name').value.trim(),
        version: document.getElementById('edit_version').value.trim(),
        iconURL: document.getElementById('edit_iconURL').value.trim(),
        size: document.getElementById('edit_size').value,
        tintColor: document.getElementById('edit_tintColor').value.trim(),
        isLanZouCloud: document.getElementById('edit_isLanZouCloud').value,
        type: document.getElementById('edit_type').value,
        lock: document.getElementById('edit_lock').value,
        downloadURL: document.getElementById('edit_downloadURL').value.trim(),
        versionDescription: document.getElementById('edit_versionDescription').value.trim(),
        versionDate: allApps[index].versionDate
    };
    
    if (!app.name || !app.version || !app.downloadURL) {
        alert('请填写必填项：名称、版本号、下载地址');
        return;
    }
    
    var bt2b = document.getElementById('bt2b').value;
    var timeType = document.getElementById('time_type').value;
    var appType = document.getElementById('app_type').value;
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=import_single&app=' + encodeURIComponent(JSON.stringify(app)) +
              '&bt2b=' + bt2b + '&time_type=' + timeType + '&app_type=' + appType
    })
    .then(function(res) {
        if (!res.ok) {
            throw new Error('HTTP ' + res.status + ': ' + res.statusText);
        }
        return res.text();
    })
    .then(function(text) {
        try {
            var res = JSON.parse(text);
            if (res.code === 1) {
                allApps[index] = app;
                renderAppTable();
                alert(res.msg);
                closeSingleModal();
            } else if (res.code === 2) {
                if (confirm('该应用已存在，是否仍要导入？')) {
                    // 强制导入逻辑
                }
            } else {
                alert('导入失败：' + res.msg);
            }
        } catch (parseError) {
            console.error('JSON 解析错误:', parseError);
            console.error('响应内容:', text);
            alert('导入失败：服务器返回了无效的 JSON 响应。请检查浏览器控制台查看详细信息。');
        }
    })
    .catch(function(err) {
        console.error('导入应用错误:', err);
        alert('导入失败：' + err.message);
    });
}

function openBatchImportModal() {
    if (selectedIndices.size === 0) { alert('请先选择要导入的应用'); return; }
    
    var selectedApps = [];
    selectedIndices.forEach(function(idx) { 
        var app = filteredApps[idx];
        selectedApps.push(app);
    });
    
    document.getElementById('batchCount').textContent = selectedApps.length;
    
    var listHtml = '<table class="table" style="font-size: 13px;"><tbody>';
    selectedApps.forEach(function(app) {
        listHtml += '<tr><td style="width:50px;"><img src="' + (app.iconURL || '') + '" class="app-icon-small" onerror="this.style.display=\'none\'"></td>' +
            '<td>' + (app.name || '未知') + '</td><td>' + (app.version || '-') + '</td></tr>';
    });
    listHtml += '</tbody></table>';
    document.getElementById('batchAppList').innerHTML = listHtml;
    document.getElementById('batchImportModal').style.display = 'flex';
}

function closeBatchModal() {
    document.getElementById('batchImportModal').style.display = 'none';
}

function confirmBatchImport() {
    var selectedApps = [];
    selectedIndices.forEach(function(idx) { 
        var app = filteredApps[idx];
        selectedApps.push(app);
    });
    
    var bt2b = document.getElementById('batch_lock').value;
    var startWeight = document.getElementById('start_weight').value;
    var timeType = document.getElementById('time_type').value;
    var appType = document.getElementById('batch_app_type').value;
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=import_batch&apps=' + encodeURIComponent(JSON.stringify(selectedApps)) +
              '&bt2b=' + bt2b + '&start_weight=' + startWeight + '&time_type=' + timeType + '&app_type=' + appType
    })
    .then(function(res) {
        if (!res.ok) {
            throw new Error('HTTP ' + res.status + ': ' + res.statusText);
        }
        return res.text();
    })
    .then(function(text) {
        try {
            var res = JSON.parse(text);
            alert(res.msg);
            if (res.code === 1) {
                closeBatchModal();
                selectedIndices.clear();
                renderAppTable();
            }
        } catch (parseError) {
            console.error('JSON 解析错误:', parseError);
            console.error('响应内容:', text);
            alert('批量导入失败：服务器返回了无效的 JSON 响应。请检查浏览器控制台查看详细信息。');
        }
    })
    .catch(function(err) {
        console.error('批量导入错误:', err);
        alert('批量导入失败：' + err.message);
    });
}

document.addEventListener('DOMContentLoaded', function() { loadApiHistory(); });
</script>

<?php renderFooter(); ?>
