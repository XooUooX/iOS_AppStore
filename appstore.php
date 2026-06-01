<?php
/**
 * Ning.Si软件源管理系统 - AppStore API
 * 软件源标准接口: 域名/appstore
 */


// 设置执行时间和内存限制
set_time_limit(30);
ini_set('memory_limit', '128M');

// 禁用错误显示，确保纯JSON输出
error_reporting(0);
ini_set('display_errors', '0');

// 判断是否已安装
if (!is_file(__DIR__ . '/install.lock')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => '系统未安装'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 定义系统常量
define('IN_SYSTEM', true);

// 加载配置文件
require_once __DIR__ . '/config.php';

// 加载数据库类
require_once __DIR__ . '/includes/class.database.php';

// 加载其他类
require_once __DIR__ . '/includes/class.device.php';
require_once __DIR__ . '/includes/class.app.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// 从fa_config表获取站点配置
$db = Database::getInstance();
$configRows = $db->fetchAll("SELECT name, value FROM " . $db->getTable('config') . " WHERE `group` = 'basic'");
$siteConfig = [];
foreach ($configRows as $row) {
    $siteConfig[$row['name']] = $row['value'];
}

// 获取 site_title 配置
$siteTitle = $siteConfig['site_title'] ?? 'Ning.Si软件源';

// 设置默认值
$defaults = [
    'name' => $siteTitle,
    'version' => '1.0.0',
    'timezone' => 'Asia/Shanghai',
    'forbiddenip' => '',
    'sourceURL' => SYSTEM_URL . '/appstore',
    'sourceicon' => '',
    'payURL' => '',
    'unlockURL' => SYSTEM_URL . '/appstore',
    'identifier' => 'com.' . preg_replace('/[^a-z0-9]/i', '', strtolower($siteTitle)) . '.source',
    'message' => '',
    'opencry' => '0',
    'openblack' => '0',
    'openblack2' => '0',
    'protect_enabled' => '0'
];

foreach ($defaults as $key => $val) {
    if (empty($siteConfig[$key])) {
        $siteConfig[$key] = $val;
    }
}

// IP黑名单检查
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!empty($siteConfig['forbiddenip'])) {
    $forbiddenIps = array_filter(array_map('trim', explode("\n", $siteConfig['forbiddenip'])));
    foreach ($forbiddenIps as $forbiddenIp) {
        if ($forbiddenIp === $clientIp || fnmatch($forbiddenIp, $clientIp)) {
            echo json_encode(['error' => 'IP已被拉黑'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// 获取请求参数
$deviceId = isset($_REQUEST['device_id']) ? substr(trim($_REQUEST['device_id'] ?? ''), 0, 255) : '';
$udid = isset($_REQUEST['udid']) ? substr(trim($_REQUEST['udid'] ?? ''), 0, 50) : '';
$kcode = isset($_REQUEST['code']) ? substr(trim($_REQUEST['code'] ?? ''), 0, 50) : '';

// 如果未提供 device_id，尝试从其他参数获取
if (empty($deviceId)) {
    $deviceId = isset($_REQUEST['deviceId']) ? substr(trim($_REQUEST['deviceId']), 0, 255) : '';
}

// 如果仍然没有 device_id，使用 udid 作为设备标识（解锁后的访问）
if (empty($deviceId) && !empty($udid)) {
    $deviceId = $udid;
}

// 搬运防护：如果没有UDID参数且开启搬运防护，返回占位应用
if (empty($udid) && $siteConfig['protect_enabled'] == '1') {
    // 从配置获取搬运防护应用参数
    $protectConfig = [];
    $protectRows = $db->fetchAll("SELECT name, value FROM " . $db->getTable('config') . " WHERE name LIKE 'protect_%'");
    foreach ($protectRows as $row) {
        $protectConfig[$row['name']] = $row['value'];
    }
    
    // 获取真实的应用总数（用于模板变量显示）
    $realTotalApps = 0;
    try {
        $countResult = $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('category') . " WHERE status = 'normal'");
        $realTotalApps = $countResult['count'] ?? 0;
    } catch (Exception $e) {}
    
    // 构建占位应用
    $protectApp = [
        'name' => $protectConfig['protect_name'] ?? '1',
        'type' => intval($protectConfig['protect_type'] ?? 0),
        'version' => $protectConfig['protect_version'] ?? '1',
        'versionDate' => date('Y-m-d\TH:i:sP'),
        'versionDescription' => $protectConfig['protect_versionDescription'] ?? '',
        'lock' => '1',
        'downloadURL' => '',
        'isLanZouCloud' => $protectConfig['protect_isLanZouCloud'] ?? '0',
        'iconURL' => $protectConfig['protect_iconURL'] ?? '/uploads/Ban.png',
        'tintColor' => $protectConfig['protect_tintColor'] ?? '1',
        'size' => intval($protectConfig['protect_size'] ?? 1048576)
    ];
    
    // 处理模板变量
    $refreshTime = date('Y-m-d H:i:s');
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $message = $siteConfig['message'] ?? '';
    
    // 获取拉黑数量
    $blacklistCount = 0;
    $realTodayUpdateCount = 0;
    try {
        // 只统计有效的设备拉黑（type=1 且未过期）
        $blackResult = $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('blacklist') . " WHERE type = 1 AND (expire_time IS NULL OR expire_time > NOW())");
        if ($blackResult) {
            $blacklistCount = $blackResult['count'] ?? 0;
        }
    } catch (Exception $e) {}
    
    // 获取今天更新的应用数量
    try {
        $today = date('Y-m-d');
        $todayStart = strtotime($today . ' 00:00:00');
        $todayEnd = strtotime($today . ' 23:59:59');
        $countResult = $db->fetch(
            "SELECT COUNT(*) as count FROM " . $db->getTable('category') . " 
             WHERE status = 'normal' AND updatetime >= ? AND updatetime <= ?",
            [$todayStart, $todayEnd]
        );
        if ($countResult) {
            $realTodayUpdateCount = $countResult['count'] ?? 0;
        }
    } catch (Exception $e) {}
    
    // 替换模板变量（使用真实的应用总数和拉黑数量）
    $replacements = [
        '[刷新时间]' => $refreshTime,
        '[到期时间]' => '未解锁',
        '[软件个数]' => $realTotalApps,
        '[更新数量]' => $realTodayUpdateCount,
        '[拉黑数量]' => $blacklistCount,
        '[设备ID]' => $deviceId,
        '[IP地址]' => $clientIp
    ];
    $message = str_replace(array_keys($replacements), array_values($replacements), $message);
    
    $response = [
        'name' => $siteConfig['name'],
        'message' => $message,
        'identifier' => $siteConfig['identifier'],
        'sourceURL' => $siteConfig['sourceURL'],
        'sourceicon' => $siteConfig['sourceicon'],
        'payURL' => $siteConfig['payURL'],
        'unlockURL' => $siteConfig['unlockURL'],
        'apps' => [$protectApp]
    ];
    
    // 加密输出支持
    if ($siteConfig['opencry'] == '1') {
        $content = json_encode($response, JSON_UNESCAPED_UNICODE);
        $content = base64_encode($content);
        $return = ['appstore' => $content];
        echo json_encode($return, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 获取请求体中的value参数（base64编码的UDID对）
$inputValue = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents("php://input");
    $inputData = json_decode($input, true);
    if (isset($inputData['value'])) {
        $inputValue = $inputData['value'];
    }
}

// 处理自动拉黑和监控
if ($inputValue) {
    $decodedValue = base64_decode($inputValue);
    $udidArr = explode('|', $decodedValue);
    $udid1 = $udidArr[0] ?? ''; // 添加者
    $udid2 = $udidArr[1] ?? ''; // 破解者
    
    // 自动拉黑 - 添加者
    if ($siteConfig['openblack'] == '1' && $udid1) {
        if (strlen($udid1) == 25 || strlen($udid1) == 40) {
            $exists = $db->fetch("SELECT id FROM " . $db->getTable('blacklist') . " WHERE udid = ?", [$udid1]);
            if (!$exists) {
                $db->insert('black_list', [
                    'udid' => $udid1,
                    'reason' => '自动拉黑-添加者',
                    'add_time' => time()
                ]);
            }
            $db->delete('monitor', "udid = ?", [$udid1]);
        }
    }
    
    // 自动拉黑 - 破解者
    if ($siteConfig['openblack2'] == '1' && $udid2) {
        if (strlen($udid2) == 25 || strlen($udid2) == 40) {
            $exists = $db->fetch("SELECT id FROM " . $db->getTable('blacklist') . " WHERE udid = ?", [$udid2]);
            if (!$exists) {
                $db->insert('black_list', [
                    'udid' => $udid2,
                    'reason' => '自动拉黑-破解者',
                    'add_time' => time()
                ]);
            }
            $db->delete('monitor', "udid = ?", [$udid2]);
        }
    }
    
    // 监控记录 - 添加者
    if ($udid1 && $siteConfig['openblack'] == '1') {
        if (strlen($udid1) == 25 || strlen($udid1) == 40) {
            $inBlack = $db->fetch("SELECT id FROM " . $db->getTable('blacklist') . " WHERE udid = ?", [$udid1]);
            if (!$inBlack) {
                $monitor = $db->fetch("SELECT id, count FROM " . $db->getTable('monitor') . " WHERE udid = ? AND identity = ?", [$udid1, '添加者']);
                if ($monitor) {
                    $db->update('monitor', ['count' => $monitor['count'] + 1], "id = ?", [$monitor['id']]);
                } else {
                    $db->insert('monitor', [
                        'udid' => $udid1,
                        'identity' => '添加者',
                        'count' => 1,
                        'add_time' => time()
                    ]);
                }
            }
        }
    }
    
    // 监控记录 - 破解者
    if ($udid2 && $siteConfig['openblack2'] == '1') {
        if (strlen($udid2) == 25 || strlen($udid2) == 40) {
            $inBlack = $db->fetch("SELECT id FROM " . $db->getTable('blacklist') . " WHERE type = 1 AND value = ? AND (expire_time IS NULL OR expire_time > NOW())", [$udid2]);
            if (!$inBlack) {
                $monitor = $db->fetch("SELECT id, count FROM " . $db->getTable('monitor') . " WHERE udid = ? AND identity = ?", [$udid2, '破解者']);
                if ($monitor) {
                    $db->update('monitor', ['count' => $monitor['count'] + 1], "id = ?", [$monitor['id']]);
                } else {
                    $db->insert('monitor', [
                        'udid' => $udid2,
                        'identity' => '破解者',
                        'count' => 1,
                        'add_time' => time()
                    ]);
                }
            }
        }
    }
}

// 检查是否在黑名单中
if ($udid) {
    $black = $db->fetch("SELECT id FROM " . $db->getTable('blacklist') . " WHERE type = 1 AND value = ? AND (expire_time IS NULL OR expire_time > NOW()) LIMIT 1", [$udid]);
    if ($black) {
        returnBlacklistedResponse($siteConfig, $udid);
        exit;
    }
}

// 卡密解锁逻辑
if ($kcode && $udid) {
    handleUnlock($db, $kcode, $udid, $deviceId, $siteConfig);
    exit;
}

// 如果已通过卡密解锁，返回已解锁的应用列表
if ($udid && !$kcode) {
    // 检查该 UDID 是否已解锁
    $device = $db->fetch("SELECT * FROM " . $db->getTable('devices') . " WHERE device_id = ? LIMIT 1", [$udid]);
    if ($device && $device['expire_time'] && strtotime($device['expire_time']) > time()) {
        // 已解锁且未过期
        returnUnlockedSource($db, $siteConfig, $udid, $udid);
        exit;
    }
}

// 验证设备
$device = new Device();
$validation = $device->validate($deviceId);

if (!$validation['valid']) {
    // 返回未授权的软件源数据（不包含下载链接）
    returnUnauthorizedSource($siteConfig, $deviceId);
    exit;
}

// 获取设备是否已过期
$isExpired = true;
if ($validation['valid']) {
    $deviceInfo = $db->fetch("SELECT expire_time FROM " . $db->getTable('devices') . " WHERE device_id = ?", [$deviceId]);
    if ($deviceInfo && $deviceInfo['expire_time']) {
        $isExpired = strtotime($deviceInfo['expire_time']) < time();
    }
}

// 获取所有应用 - 优化查询，只选择必要字段
$appObj = new App();
$apps = $appObj->getAllActive();

// 获取统计信息 - 简化统计
$totalApps = count($apps);
$blacklistCount = 0;
$todayUpdateCount = 0;
try {
    // 只统计有效的设备拉黑（type=1 且未过期）
    $blackResult = $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('blacklist') . " WHERE type = 1 AND (expire_time IS NULL OR expire_time > NOW())");
    if ($blackResult) {
        $blacklistCount = $blackResult['count'] ?? 0;
    }
} catch (Exception $e) {}

// 获取今天更新的应用数量
try {
    $today = date('Y-m-d');
    $todayStart = strtotime($today . ' 00:00:00');
    $todayEnd = strtotime($today . ' 23:59:59');
    $countResult = $db->fetch(
        "SELECT COUNT(*) as count FROM " . $db->getTable('category') . " 
         WHERE status = 'normal' AND updatetime >= ? AND updatetime <= ?",
        [$todayStart, $todayEnd]
    );
    if ($countResult) {
        $todayUpdateCount = $countResult['count'] ?? 0;
    }
} catch (Exception $e) {}

// 获取设备到期时间 - 优化为单次查询
$expireTimeStr = '已过期 或 未解锁本源';
if ($deviceId && $validation['valid']) {
    try {
        $deviceInfo = $db->fetch("SELECT expire_time FROM " . $db->getTable('devices') . " WHERE device_id = ? LIMIT 1", [$deviceId]);
        if ($deviceInfo && $deviceInfo['expire_time']) {
            $expireTimeStr = $deviceInfo['expire_time'];
            $isExpired = strtotime($deviceInfo['expire_time']) < time();
        }
    } catch (Exception $e) {}
}

// 处理模板变量
$refreshTime = date('Y-m-d H:i:s');
$message = $siteConfig['message'];
$replacements = [
    '[刷新时间]' => $refreshTime,
    '[到期时间]' => $expireTimeStr,
    '[软件个数]' => $totalApps,
    '[更新数量]' => $todayUpdateCount,
    '[拉黑数量]' => $blacklistCount,
    '[设备ID]' => $deviceId,
    '[IP地址]' => $clientIp
];
$message = str_replace(array_keys($replacements), array_values($replacements), $message);

// 构建应用列表
$appList = [];
foreach ($apps as $app) {
    $appData = App::formatForApi($app);
    
    // lock 字段处理：如果 lock=1 且设备已过期，则隐藏下载链接
    if ($appData['lock'] === '1' || $appData['lock'] === 1) {
        if ($isExpired || !$validation['valid']) {
            $appData['downloadURL'] = '';
        }
    }
    
    // 处理 type 字段
    if ($appData['type'] === 'default') {
        $appData['type'] = 0;
    }
    
    // 处理 versionDescription 中的换行
    $appData['versionDescription'] = str_replace("\n", "\r\n", $appData['versionDescription']);
    
    $appList[] = $appData;
}

// 构建响应数据
$response = [
    'name' => $siteConfig['name'],
    'message' => $message,
    'identifier' => $siteConfig['identifier'],
    'sourceURL' => $siteConfig['sourceURL'],
    'sourceicon' => $siteConfig['sourceicon'],
    'payURL' => $siteConfig['payURL'],
    'unlockURL' => $siteConfig['unlockURL'],
    'UDID' => $udid,
    'Time' => $refreshTime,
    'apps' => $appList
];

// 如果未授权，移除 UDID 和 Time
if (!$validation['valid']) {
    unset($response['UDID']);
    unset($response['Time']);
}

// 加密输出支持
if ($siteConfig['opencry'] == '1') {
    $content = json_encode($response, JSON_UNESCAPED_UNICODE);
    $content = base64_encode($content);
    $return = ['appstore' => $content];
    echo json_encode($return, JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

// 记录请求日志 - 异步记录，不阻塞响应
try {
    $logData = [
        'api_name' => 'appstore',
        'device_id' => substr($deviceId, 0, 100),
        'card_key' => substr($kcode, 0, 50),
        'request_data' => json_encode(array_keys($_REQUEST)),
        'response_data' => json_encode(['apps_count' => count($appList)]),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'status' => $validation['valid'] ? 1 : 0,
        'create_time' => date('Y-m-d H:i:s')
    ];
    $db->insert('api_logs', $logData);
} catch (Exception $e) {}

/**
 * 返回未授权的软件源数据
 * 开启搬运防护：返回占位应用
 * 关闭搬运防护：返回正常应用列表（付费应用无下载链接）
 */
function returnUnauthorizedSource($siteConfig, $deviceId = 'unknown') {
    $db = Database::getInstance();
    
    // 如果开启搬运防护，返回占位应用
    if ($siteConfig['protect_enabled'] == '1') {
        // 从配置获取搬运防护应用参数
        $protectConfig = [];
        $protectRows = $db->fetchAll("SELECT name, value FROM " . $db->getTable('config') . " WHERE name LIKE 'protect_%'");
        foreach ($protectRows as $row) {
            $protectConfig[$row['name']] = $row['value'];
        }
        
        // 构建占位应用（未授权提示）
        $protectApp = [
            'name' => $protectConfig['protect_name'] ?? '未授权访问',
            'type' => intval($protectConfig['protect_type'] ?? 0),
            'version' => $protectConfig['protect_version'] ?? '1.0',
            'versionDate' => date('Y-m-d\TH:i:sP'),
            'versionDescription' => $protectConfig['protect_versionDescription'] ?? '⚠️ 请使用正版授权设备访问本软件源，如需授权请联系源主获取卡密。',
            'lock' => '1',
            'downloadURL' => '',
            'isLanZouCloud' => $protectConfig['protect_isLanZouCloud'] ?? '0',
            'iconURL' => $protectConfig['protect_iconURL'] ?? '/uploads/Ban.png',
            'tintColor' => $protectConfig['protect_tintColor'] ?? 'FF3B30',
            'size' => intval($protectConfig['protect_size'] ?? 1048576)
        ];
        
        // 使用传入的 deviceId，支持模板变量替换
        $refreshTime = date('Y-m-d H:i:s');
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // 获取真实的应用总数（用于模板变量显示）
        $realTotalApps = 0;
        try {
            $countResult = $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('category') . " WHERE status = 'normal'");
            $realTotalApps = $countResult['count'] ?? 0;
        } catch (Exception $e) {}
        
        // 获取拉黑数量
        $blacklistCount = 0;
        try {
            // 只统计有效的设备拉黑（type=1 且未过期）
            $blackResult = $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('blacklist') . " WHERE type = 1 AND (expire_time IS NULL OR expire_time > NOW())");
            if ($blackResult) {
                $blacklistCount = $blackResult['count'] ?? 0;
            }
        } catch (Exception $e) {}
        
        // 获取今天更新的应用数量
        $todayUpdateCount = 0;
        try {
            $today = date('Y-m-d');
            $todayStart = strtotime($today . ' 00:00:00');
            $todayEnd = strtotime($today . ' 23:59:59');
            $countResult = $db->fetch(
                "SELECT COUNT(*) as count FROM " . $db->getTable('category') . " 
                 WHERE status = 'normal' AND updatetime >= ? AND updatetime <= ?",
                [$todayStart, $todayEnd]
            );
            if ($countResult) {
                $todayUpdateCount = $countResult['count'] ?? 0;
            }
        } catch (Exception $e) {}
        
        // 处理模板变量 - 未授权状态
        $message = $siteConfig['message'] ?? '';
        $replacements = [
            '[站点名称]' => $siteConfig['name'] ?: '',
            '[刷新时间]' => $refreshTime,
            '[到期时间]' => '未解锁',
            '[软件个数]' => $realTotalApps,
            '[更新数量]' => $todayUpdateCount,
            '[拉黑数量]' => $blacklistCount,
            '[设备ID]' => $deviceId,
            '[IP地址]' => $clientIp
        ];
        $message = str_replace(array_keys($replacements), array_values($replacements), $message);
        
        // 返回未授权的响应（只包含占位应用）
        $response = [
            'name' => $siteConfig['name'] ?: 'Ning.Si软件源',
            'message' => $message,
            'identifier' => $siteConfig['identifier'] ?: 'niing.si',
            'sourceURL' => $siteConfig['sourceURL'] ?: (SYSTEM_URL . '/appstore'),
            'sourceicon' => $siteConfig['sourceicon'] ?: '',
            'payURL' => $siteConfig['payURL'] ?: '',
            'unlockURL' => $siteConfig['unlockURL'] ?: (SYSTEM_URL . '/appstore'),
            'apps' => [$protectApp]  // 只返回占位应用，不返回真实应用列表
        ];
        
        // 加密输出支持
        if ($siteConfig['opencry'] == '1') {
            $content = json_encode($response, JSON_UNESCAPED_UNICODE);
            $content = base64_encode($content);
            $return = ['appstore' => $content];
            echo json_encode($return, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        }
        
        // 记录未授权请求
        try {
            $db->insert('api_logs', [
                'api_name' => 'appstore_unauthorized',
                'device_id' => substr($deviceId, 0, 100),
                'card_key' => '',
                'request_data' => json_encode(array_keys($_REQUEST)),
                'response_data' => json_encode(['response_type' => 'placeholder_app']),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'status' => 0,
                'create_time' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {}
        
        exit;
    }
    
    // 关闭搬运防护时，返回正常应用列表（付费应用无下载链接）
    // 获取应用列表
    $apps = $db->fetchAll("SELECT id, name, nickname, type, image, keywords, bt1a, bt1b, bt2a, bt2b, flag, updatetime FROM " . $db->getTable('category') . " WHERE status = 'normal' ORDER BY weigh DESC, id DESC LIMIT 200");
    
    if (empty($apps)) {
        $apps = $db->fetchAll("SELECT id, name, nickname, type, image, keywords, bt1a, bt1b, bt2a, bt2b, flag, updatetime FROM " . $db->getTable('category') . " ORDER BY weigh DESC, id DESC LIMIT 200");
    }
    
    $appList = [];
    foreach ($apps as $app) {
        $appData = App::formatForApi($app);
        // 未授权用户：lock=1 付费应用隐藏下载链接，lock=0 免费应用显示下载链接
        if ($appData['lock'] === '1' || $appData['lock'] === 1) {
            $appData['downloadURL'] = '';
        }
        // 确保所有必需字段存在
        $appData['versionDate'] = $appData['versionDate'] ?: date('Y-m-d\TH:i:sP');
        $appList[] = $appData;
    }
    
    // 获取统计信息
    $totalApps = count($appList);
    $blacklistCount = 0;
    $todayUpdateCount = 0;
    $realTodayUpdateCount = 0;
    try {
        // 只统计有效的设备拉黑（type=1 且未过期）
        $blacklistCount = $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('blacklist') . " WHERE type = 1 AND (expire_time IS NULL OR expire_time > NOW())")['count'] ?? 0;
    } catch (Exception $e) {}
    
    // 获取今天更新的应用数量
    try {
        $today = date('Y-m-d');
        $todayStart = strtotime($today . ' 00:00:00');
        $todayEnd = strtotime($today . ' 23:59:59');
        $countResult = $db->fetch(
            "SELECT COUNT(*) as count FROM " . $db->getTable('category') . " 
             WHERE status = 'normal' AND updatetime >= ? AND updatetime <= ?",
            [$todayStart, $todayEnd]
        );
        if ($countResult) {
            $todayUpdateCount = $countResult['count'] ?? 0;
            $realTodayUpdateCount = $countResult['count'] ?? 0;  // 保存真实的更新数
        }
    } catch (Exception $e) {}
    
    // 使用传入的 deviceId，支持模板变量替换
    $refreshTime = date('Y-m-d H:i:s');
    
    // 使用数据库中的 message 配置，替换模板变量
    $messageTemplate = $siteConfig['message'] ?? '';
    if (empty($messageTemplate)) {
        // 使用默认模板（空）
        $messageTemplate = '';
    }
    
    // 获取客户端IP
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // 获取拉黑数量
    $blacklistCount = 0;
    try {
        $blackResult = $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('blacklist') . " WHERE type = 1 AND (expire_time IS NULL OR expire_time > NOW())");
        if ($blackResult) {
            $blacklistCount = $blackResult['count'] ?? 0;
        }
    } catch (Exception $e) {}
    
    // 获取今天更新的应用数量
    $todayUpdateCount = 0;
    try {
        $today = date('Y-m-d');
        $todayStart = strtotime($today . ' 00:00:00');
        $todayEnd = strtotime($today . ' 23:59:59');
        $countResult = $db->fetch(
            "SELECT COUNT(*) as count FROM " . $db->getTable('category') . " 
             WHERE status = 'normal' AND updatetime >= ? AND updatetime <= ?",
            [$todayStart, $todayEnd]
        );
        if ($countResult) {
            $todayUpdateCount = $countResult['count'] ?? 0;
        }
    } catch (Exception $e) {}
    
    // 替换模板变量
    $message = str_replace(
        ['[站点名称]', '[刷新时间]', '[到期时间]', '[软件个数]', '[更新数量]', '[拉黑数量]', '[设备ID]', '[IP地址]'],
        [
            $siteConfig['name'] ?: '',
            $refreshTime,
            '未解锁',
            $totalApps,
            $todayUpdateCount,
            $blacklistCount,
            $deviceId,
            $clientIp
        ],
        $messageTemplate
    );
    
    // 返回未授权的提示信息（包含应用列表但无下载链接）
    $response = [
        'name' => $siteConfig['name'] ?: '',
        'message' => $message,
        'identifier' => $siteConfig['identifier'] ?: 'com.unknown.source',
        'sourceURL' => $siteConfig['sourceURL'] ?: (SYSTEM_URL . '/appstore'),
        'sourceicon' => $siteConfig['sourceicon'] ?: '',
        'payURL' => $siteConfig['payURL'] ?: '',
        'unlockURL' => $siteConfig['unlockURL'] ?: (SYSTEM_URL . '/appstore'),
        'ip' => $clientIp,
        'apps' => $appList
    ];
    
    // 加密输出支持
    if ($siteConfig['opencry'] == '1') {
        $content = json_encode($response, JSON_UNESCAPED_UNICODE);
        $content = base64_encode($content);
        $return = ['appstore' => $content];
        echo json_encode($return, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    
    // 记录未授权请求
    try {
        $db->insert('api_logs', [
            'api_name' => 'appstore',
            'device_id' => substr($deviceId, 0, 100),
            'card_key' => '',
            'request_data' => json_encode(array_keys($_REQUEST)),
            'response_data' => json_encode(['apps_count' => count($appList)]),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'status' => 0,
            'create_time' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {}
    
    exit;
}

/**
 * 处理卡密解锁
 */
function handleUnlock($db, $kcode, $udid, $deviceId, $siteConfig) {
    try {
        // 如果没有提供 device_id，使用 udid 作为设备标识
        if (empty($deviceId)) {
            $deviceId = $udid;
        }
        
        $cardKey = $db->fetch("SELECT * FROM " . $db->getTable('card_keys') . " WHERE card_key = ? ORDER BY id DESC LIMIT 1", [$kcode]);
        if ($cardKey) {
            if ($cardKey['status'] == 1) {
                // 卡密已使用，检查是否是同一设备
                if ($cardKey['bind_device_id'] == $deviceId) {
                    // 同一设备，返回成功
                    echo json_encode(['code' => 0, 'msg' => 'ok，解锁成功'], JSON_UNESCAPED_UNICODE);
                    return;
                }
                echo json_encode(['code' => 0, 'msg' => '解锁码已使用'], JSON_UNESCAPED_UNICODE);
                return;
            } else {
                // 清理该设备已到期的卡密
                $existingCards = $db->fetchAll("SELECT * FROM " . $db->getTable('card_keys') . " WHERE bind_device_id = ?", [$deviceId]);
                foreach ($existingCards as $existing) {
                    if ($existing['expire_time'] && strtotime($existing['expire_time']) < time()) {
                        $db->delete('card_keys', "id = ?", [$existing['id']]);
                    }
                }
                
                // 计算到期时间 - 永久卡设为2099-12-31
                $now = time();
                if ($cardKey['card_type'] == 4) {
                    $expireTime = '2099-12-31 23:59:59';
                } else {
                    $expireDays = $cardKey['expire_days'] ?? 30;
                    // 确保 expire_days 是有效的数字
                    if (empty($expireDays) || !is_numeric($expireDays) || $expireDays <= 0) {
                        $expireDays = 30;
                    }
                    $expireTime = date('Y-m-d H:i:s', $now + (intval($expireDays) * 86400));
                }
                
                // 更新卡密
                $db->update('card_keys', [
                    'status' => 1,
                    'bind_device_id' => $deviceId,
                    'use_time' => date('Y-m-d H:i:s', $now),
                    'expire_time' => $expireTime
                ], "id = ?", [$cardKey['id']]);
                
                // 更新或创建设备
                $device = $db->fetch("SELECT id FROM " . $db->getTable('devices') . " WHERE device_id = ? LIMIT 1", [$deviceId]);
                if ($device) {
                    $db->update('devices', [
                        'card_key' => $kcode,
                        'bind_time' => date('Y-m-d H:i:s', $now),
                        'expire_time' => $expireTime,
                        'status' => 1
                    ], "id = ?", [$device['id']]);
                } else {
                    $db->insert('devices', [
                        'device_id' => $deviceId,
                        'card_key' => $kcode,
                        'bind_time' => date('Y-m-d H:i:s', $now),
                        'expire_time' => $expireTime,
                        'status' => 1,
                        'create_time' => date('Y-m-d H:i:s', $now)
                    ]);
                }
                
                // 解锁成功，返回成功状态
                echo json_encode(['code' => 0, 'msg' => 'ok，解锁成功'], JSON_UNESCAPED_UNICODE);
                return;
            }
        } else {
            echo json_encode(['code' => 0, 'msg' => '解锁码不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
    } catch (Exception $e) {
        echo json_encode(['code' => -1, 'msg' => '系统错误: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        return;
    }
}

/**
 * 返回已解锁的软件源数据（包含完整下载链接）
 */
function returnUnlockedSource($db, $siteConfig, $udid, $deviceId) {
    // 获取所有应用
    $apps = $db->fetchAll("SELECT id, name, nickname, type, image, keywords, bt1a, bt1b, bt2a, bt2b, flag, updatetime FROM " . $db->getTable('category') . " WHERE status = 'normal' ORDER BY weigh DESC, id DESC LIMIT 200");
    
    if (empty($apps)) {
        $apps = $db->fetchAll("SELECT id, name, nickname, type, image, keywords, bt1a, bt1b, bt2a, bt2b, flag, updatetime FROM " . $db->getTable('category') . " ORDER BY weigh DESC, id DESC LIMIT 200");
    }
    
    $appList = [];
    foreach ($apps as $app) {
        $appData = App::formatForApi($app);
        $appData['versionDate'] = $appData['versionDate'] ?: date('Y-m-d\TH:i:sP');
        $appList[] = $appData;
    }
    
    $refreshTime = date('Y-m-d H:i:s');
    $totalApps = count($appList);
    
    // 获取设备到期时间
    $expireTimeStr = '未解锁';
    try {
        $deviceInfo = $db->fetch("SELECT expire_time FROM " . $db->getTable('devices') . " WHERE device_id = ? LIMIT 1", [$deviceId]);
        if ($deviceInfo && $deviceInfo['expire_time']) {
            $expireTimeStr = $deviceInfo['expire_time'];
        }
    } catch (Exception $e) {}
    
    // 获取客户端IP
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // 获取拉黑数量
    $blacklistCount = 0;
    try {
        $blackResult = $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('blacklist') . " WHERE type = 1 AND (expire_time IS NULL OR expire_time > NOW())");
        if ($blackResult) {
            $blacklistCount = $blackResult['count'] ?? 0;
        }
    } catch (Exception $e) {}
    
    // 获取今天更新的应用数量
    $todayUpdateCount = 0;
    try {
        $today = date('Y-m-d');
        $todayStart = strtotime($today . ' 00:00:00');
        $todayEnd = strtotime($today . ' 23:59:59');
        $countResult = $db->fetch(
            "SELECT COUNT(*) as count FROM " . $db->getTable('category') . " 
             WHERE status = 'normal' AND updatetime >= ? AND updatetime <= ?",
            [$todayStart, $todayEnd]
        );
        if ($countResult) {
            $todayUpdateCount = $countResult['count'] ?? 0;
        }
    } catch (Exception $e) {}
    
    // 处理模板变量
    $message = $siteConfig['message'];
    $replacements = [
        '[站点名称]' => $siteConfig['name'] ?: '',
        '[刷新时间]' => $refreshTime,
        '[到期时间]' => $expireTimeStr,
        '[软件个数]' => $totalApps,
        '[更新数量]' => $todayUpdateCount,
        '[拉黑数量]' => $blacklistCount,
        '[设备ID]' => $deviceId,
        '[IP地址]' => $clientIp
    ];
    $message = str_replace(array_keys($replacements), array_values($replacements), $message);
    
    $response = [
        'name' => $siteConfig['name'],
        'message' => $message,
        'identifier' => $siteConfig['identifier'],
        'sourceURL' => $siteConfig['sourceURL'],
        'sourceicon' => $siteConfig['sourceicon'],
        'payURL' => $siteConfig['payURL'],
        'unlockURL' => $siteConfig['unlockURL'],
        'UDID' => $udid,
        'Time' => $refreshTime,
        'apps' => $appList
    ];
    
    // 加密输出支持
    if ($siteConfig['opencry'] == '1') {
        $content = json_encode($response, JSON_UNESCAPED_UNICODE);
        $content = base64_encode($content);
        $return = ['appstore' => $content];
        echo json_encode($return, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * 返回被拉黑的响应
 */
function returnBlacklistedResponse($siteConfig, $udid) {
    $db = Database::getInstance();
    $nowtime = date("Y-m-d H:i:s");
    
    // 查询黑名单信息
    $blacklistInfo = $db->fetch(
        "SELECT reason, expire_time FROM " . $db->getTable('blacklist') . " 
         WHERE type = 1 AND value = ? AND (expire_time IS NULL OR expire_time > NOW()) 
         LIMIT 1",
        [$udid]
    );
    
    // 构建拉黑信息
    $blacklistReason = $blacklistInfo['reason'] ?? '你已被源主拉黑！';
    $expireTime = $blacklistInfo['expire_time'] ?? null;
    
    // 构建拉黑时长显示
    $durationText = '永久拉黑';
    $unlockDate = '永久';
    if ($expireTime) {
        $expireTimestamp = strtotime($expireTime);
        $nowTimestamp = time();
        $remainingDays = ceil(($expireTimestamp - $nowTimestamp) / 86400);
        if ($remainingDays > 0) {
            $durationText = "拉黑 {$remainingDays} 天";
            $unlockDate = date('Y-m-d', $expireTimestamp);
        }
    }
    
    // 构建完整的拉黑提示信息
    $message = "你已被源主拉黑！\n\n原因：{$blacklistReason}\n时长：{$durationText}";
    
    $json = [
        'name' => '已被源主拉黑',
        'message' => $message,
        'identifier' => '长按此处删除软件源',
        'payURL' => '',
        'unlockURL' => '',
        'UDID' => $udid,
        'Time' => $nowtime,
        'apps' => [
            [
                'name' => '你已被源主拉黑！',
                'version' => '9.9.9',
                'type' => '1.0',
                'versionDate' => $unlockDate,
                'versionDescription' => $message,
                'lock' => '1',
                'downloadURL' => '',
                'isLanZouCloud' => '0',
                'tintColor' => '',
                'size' => '123973140.48'
            ]
        ]
    ];
    
    if ($siteConfig['opencry'] == '1') {
        $content = json_encode($json, JSON_UNESCAPED_UNICODE);
        $content = base64_encode($content);
        $return = ['appstore' => $content];
        echo json_encode($return, JSON_UNESCAPED_UNICODE);
    } else {
        unset($json['UDID']);
        unset($json['Time']);
        echo json_encode($json, JSON_UNESCAPED_UNICODE);
    }
    exit;
}
