<?php
/**
 * API接口
 * 支持操作：
 * 1. activate - 激活卡密
 * 2. validate - 验证设备
 * 3. sources - 获取软件源列表
 * 4. heartbeat - 设备心跳
 */

// 判断是否已安装
if (!is_file(__DIR__ . '/install.lock')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => '系统未安装，请先运行 install.php'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 定义系统常量
define('IN_SYSTEM', true);

// 加载配置文件
require_once __DIR__ . '/config.php';

// 加载数据库类
require_once __DIR__ . '/includes/class.database.php';

// 加载其他类
require_once __DIR__ . '/includes/class.cardkey.php';
require_once __DIR__ . '/includes/class.device.php';
require_once __DIR__ . '/includes/class.app.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// 获取请求参数
$action = isset($_REQUEST['action']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_REQUEST['action']) : '';
$apiKey = isset($_REQUEST['api_key']) ? trim($_REQUEST['api_key']) : '';
$deviceId = isset($_REQUEST['device_id']) ? substr(trim($_REQUEST['device_id']), 0, 255) : '';
$cardKey = isset($_REQUEST['card_key']) ? substr(trim($_REQUEST['card_key']), 0, 50) : '';

// IP黑名单检查
$db = Database::getInstance();
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// 获取系统配置
$configRows = $db->fetchAll("SELECT name, value FROM " . $db->getTable('config') . " WHERE `group` = 'basic'");
$siteConfig = [];
foreach ($configRows as $row) {
    $siteConfig[$row['name']] = $row['value'];
}

// API 密钥验证函数
function validateApiKey($apiKey, $siteConfig) {
    // 如果未配置 API 密钥，则允许所有请求（向后兼容）
    $configuredApiKey = $siteConfig['api_key'] ?? '';
    
    if (empty($configuredApiKey)) {
        // 未配置 API 密钥时，允许请求但记录警告
        return true;
    }
    
    // 如果配置了 API 密钥，则必须验证
    if (empty($apiKey)) {
        return false;
    }
    
    // 使用 hash_equals 防止时序攻击
    return hash_equals($configuredApiKey, $apiKey);
}

// API请求频率限制
function checkRateLimit($ip, $limit = 100, $window = 3600) {
    $rateLimitFile = __DIR__ . '/cache/ratelimit/' . md5($ip) . '.json';
    $rateLimitDir = dirname($rateLimitFile);
    
    if (!is_dir($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
    }
    
    $now = time();
    $requests = [];
    
    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        if ($data && isset($data['requests'])) {
            // 只保留在窗口期内的请求
            $requests = array_filter($data['requests'], function($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            });
        }
    }
    
    // 检查是否超过限制
    if (count($requests) >= $limit) {
        return false;
    }
    
    // 添加当前请求
    $requests[] = $now;
    
    // 保存数据
    file_put_contents($rateLimitFile, json_encode(['requests' => array_values($requests)]), LOCK_EX);
    
    return true;
}

// 执行频率限制检查
if (!checkRateLimit($clientIp, API_RATE_LIMIT, 3600)) {
    response(['success' => false, 'code' => 429, 'message' => '请求过于频繁，请稍后再试']);
}

// 验证API密钥
if (!validateApiKey($apiKey, $siteConfig)) {
    response(['success' => false, 'code' => 401, 'message' => 'API密钥无效或未提供']);
}

// 检查IP是否在禁止列表中
if (!empty($siteConfig['forbiddenip'])) {
    $forbiddenIps = array_filter(array_map('trim', explode("\n", $siteConfig['forbiddenip'])));
    foreach ($forbiddenIps as $forbiddenIp) {
        if ($forbiddenIp === $clientIp || fnmatch($forbiddenIp, $clientIp)) {
            response(['success' => false, 'code' => 403, 'message' => 'IP已被拉黑']);
        }
    }
}

// 记录API请求日志
function logApiRequest($action, $deviceId, $cardKey, $request, $response, $status) {
    try {
        $db = Database::getInstance();
        $db->insert('api_logs', [
            'api_name' => $action,
            'device_id' => $deviceId,
            'card_key' => $cardKey,
            'request_data' => json_encode($request, JSON_UNESCAPED_UNICODE),
            'response_data' => json_encode($response, JSON_UNESCAPED_UNICODE),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'status' => $status ? 1 : 0,
            'create_time' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // 日志记录失败不影响主流程
    }
}

// 统一响应函数
function response($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 参数验证函数
function validateRequired($params) {
    foreach ($params as $param => $value) {
        if (empty($value)) {
            response(['success' => false, 'code' => 400, 'message' => "参数 {$param} 不能为空"]);
        }
    }
}

// 路由处理
try {
    switch ($action) {
        case 'activate':
            // 激活卡密
            validateRequired(['card_key' => $cardKey, 'device_id' => $deviceId]);
            
            $cardKeyObj = new CardKey();
            $result = $cardKeyObj->activate($cardKey, $deviceId);
            
            logApiRequest($action, $deviceId, $cardKey, $_REQUEST, $result, $result['success']);
            response($result);
            break;
            
        case 'validate':
            // 验证设备有效性
            validateRequired(['device_id' => $deviceId]);
            
            $device = new Device();
            $result = $device->validate($deviceId);
            
            $response = [
                'success' => $result['valid'],
                'code' => $result['valid'] ? 200 : 403,
                'message' => $result['message']
            ];
            
            if ($result['valid']) {
                $response['data'] = [
                    'expire_time' => $result['expire_time'],
                    'days_left' => $result['days_left']
                ];
            }
            
            logApiRequest($action, $deviceId, '', $_REQUEST, $response, $result['valid']);
            response($response);
            break;
            
        case 'sources':
            // 获取软件源配置
            validateRequired(['device_id' => $deviceId]);
            
            $db = Database::getInstance();
            $configRows = $db->fetchAll("SELECT name, value FROM " . $db->getTable('config') . " WHERE `group` = 'basic'");
            $siteConfig = [];
            foreach ($configRows as $row) {
                $siteConfig[$row['name']] = $row['value'];
            }
            
            // 设置默认值
            $defaults = [
                'name' => 'Ning.Si软件源',
                'version' => '1.0.0',
                'timezone' => 'Asia/Shanghai',
                'forbiddenip' => '',
                'sourceURL' => '',
                'sourceicon' => '',
                'payURL' => '',
                'unlockURL' => '',
                'identifier' => '',
                'message' => ''
            ];
            foreach ($defaults as $key => $val) {
                if (empty($siteConfig[$key])) {
                    $siteConfig[$key] = $val;
                }
            }
            
            // 获取所有应用
            $appObj = new App();
            $apps = $appObj->getAllActive();
            
            // 处理模板变量
            $extraVars = [
                '刷新时间' => date('Y-m-d H:i:s'),
                '到期时间' => '未激活',
                '软件个数' => count($apps),
                '设备ID' => substr($deviceId, 0, 16) . '...'
            ];
            
            $message = $siteConfig['message'];
            foreach ($siteConfig as $key => $value) {
                $message = str_replace('{$site.' . $key . '}', $value, $message);
            }
            foreach ($extraVars as $key => $value) {
                $message = str_replace('[' . $key . ']', $value, $message);
            }
            
            $result = [
                'success' => true,
                'code' => 200,
                'data' => [
                    'name' => $siteConfig['name'],
                    'message' => $message,
                    'identifier' => $siteConfig['identifier'],
                    'sourceURL' => $siteConfig['sourceURL'],
                    'sourceicon' => $siteConfig['sourceicon'],
                    'payURL' => $siteConfig['payURL'],
                    'unlockURL' => $siteConfig['unlockURL'],
                    'apps' => []
                ]
            ];
            
            // 添加应用列表
            foreach ($apps as $app) {
                $result['data']['apps'][] = App::formatForApi($app);
            }
            
            logApiRequest($action, $deviceId, '', $_REQUEST, $result, true);
            response($result);
            break;
            
        case 'heartbeat':
            // 设备心跳（保持在线状态）
            validateRequired(['device_id' => $deviceId]);
            
            $device = new Device();
            $deviceInfo = $device->get($deviceId);
            
            if (!$deviceInfo) {
                logApiRequest($action, $deviceId, '', $_REQUEST, ['success' => false], false);
                response(['success' => false, 'code' => 404, 'message' => '设备未注册']);
            }
            
            // 更新最后活跃时间
            $device->update($deviceId, [
                'last_active_time' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
            
            // 检查是否过期
            $isExpired = strtotime($deviceInfo['expire_time']) < time();
            
            $response = [
                'success' => true,
                'code' => 200,
                'data' => [
                    'device_id' => $deviceId,
                    'status' => $isExpired ? 2 : $deviceInfo['status'],
                    'expire_time' => $deviceInfo['expire_time'],
                    'is_expired' => $isExpired
                ]
            ];
            
            logApiRequest($action, $deviceId, '', $_REQUEST, $response, true);
            response($response);
            break;
            
        case 'count':
            // 应用下载计数更新（适配gmm项目）
            $appId = intval($_REQUEST['app_id'] ?? 0);
            
            if (!$appId) {
                response(['success' => false, 'code' => 400, 'message' => '应用ID不能为空']);
            }
            
            $db = Database::getInstance();
            
            // 获取当前时间（日）
            $currentDay = intval(date('d'));
            
            // 查询应用当前计数状态
            $app = $db->fetch("SELECT cs, cstime FROM " . $db->getTable('category') . " WHERE id = ?", [$appId]);
            
            if (!$app) {
                response(['success' => false, 'code' => 404, 'message' => '应用不存在']);
            }
            
            // 如果计数时间是今天，则增加计数；否则重置计数
            if ($app['cstime'] == $currentDay) {
                $db->update('category', [
                    'cs' => intval($app['cs']) + 1
                ], "id = ?", [$appId]);
            } else {
                $db->update('category', [
                    'cs' => 1,
                    'cstime' => $currentDay
                ], "id = ?", [$appId]);
            }
            
            logApiRequest($action, $deviceId, '', $_REQUEST, ['success' => true], true);
            response(['success' => true, 'code' => 200, 'message' => 'ok']);
            break;
            
        case 'query':
            // 查询卡密状态
            validateRequired(['card_key' => $cardKey]);
            
            $cardKeyObj = new CardKey();
            $card = $cardKeyObj->get($cardKey);
            
            if (!$card) {
                logApiRequest($action, '', $cardKey, $_REQUEST, ['success' => false], false);
                response(['success' => false, 'code' => 404, 'message' => '卡密不存在']);
            }
            
            $response = [
                'success' => true,
                'code' => 200,
                'data' => [
                    'card_key' => $card['card_key'],
                    'type' => $card['card_type'],
                    'type_name' => CardKey::getTypeName($card['card_type']),
                    'status' => $card['status'],
                    'status_name' => CardKey::getStatusName($card['status']),
                    'expire_days' => $card['expire_days'],
                    'create_time' => $card['create_time'],
                    'use_time' => $card['use_time'],
                    'bind_device' => $card['bind_device_id']
                ]
            ];
            
            logApiRequest($action, '', $cardKey, $_REQUEST, $response, true);
            response($response);
            break;
            
        default:
            // 未知操作
            response([
                'success' => false,
                'code' => 400,
                'message' => '未知的操作类型',
                'available_actions' => ['activate', 'validate', 'sources', 'heartbeat', 'count', 'query']
            ]);
    }
} catch (Exception $e) {
    // 异常处理
    $errorResponse = [
        'success' => false,
        'code' => 500,
        'message' => '服务器内部错误: ' . $e->getMessage()
    ];
    
    logApiRequest($action ?? 'unknown', $deviceId ?? '', $cardKey ?? '', $_REQUEST, $errorResponse, false);
    response($errorResponse);
}
