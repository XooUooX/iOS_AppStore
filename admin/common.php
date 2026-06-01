<?php

/**
 * 检查管理员登录状态
 */
function checkAdminLogin() {
    if (empty($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        header('Location: login.php');
        exit;
    }
}

/**
 * 获取仪表板统计数据
 */
function getDashboardStats() {
    try {
        $db = Database::getInstance();
        
        $stats = [
            'device_total' => $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('devices'))['count'] ?? 0,
            'device_active' => $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('devices') . " WHERE status = 1")['count'] ?? 0,
            'today_activation' => $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('devices') . " WHERE DATE(create_time) = CURDATE()")['count'] ?? 0,
            'expiring_soon' => $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('devices') . " WHERE expire_time IS NOT NULL AND expire_time <= DATE_ADD(NOW(), INTERVAL 7 DAY) AND expire_time > NOW()")['count'] ?? 0,
            'card_total' => $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('card_keys'))['count'] ?? 0,
            'app_total' => $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('category'))['count'] ?? 0,
            'monitor_total' => $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('monitor'))['count'] ?? 0,
            'api_today' => $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('api_logs') . " WHERE DATE(create_time) = CURDATE()")['count'] ?? 0,
            'ip_blacklist_count' => $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('blacklist') . " WHERE type = 2 AND (expire_time IS NULL OR expire_time > NOW())")['count'] ?? 0,
            'blacklist_total' => $db->fetch("SELECT COUNT(*) as count FROM " . $db->getTable('blacklist') . " WHERE type = 1 AND (expire_time IS NULL OR expire_time > NOW())")['count'] ?? 0,
        ];
        
        return $stats;
    } catch (Exception $e) {
        return [
            'device_total' => 0,
            'device_active' => 0,
            'today_activation' => 0,
            'expiring_soon' => 0,
            'card_total' => 0,
            'app_total' => 0,
            'monitor_total' => 0,
            'api_today' => 0,
            'ip_blacklist_count' => 0,
            'blacklist_total' => 0,
        ];
    }
}

/**
 * 生成CSRF令牌
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证CSRF令牌
 */
function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 获取CSRF令牌HTML输入框
 */
function csrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * 生成分页HTML
 */
function pagination($currentPage, $totalPages, $baseUrl = '') {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px; flex-wrap: wrap;">';
    
    $currentPage = max(1, intval($currentPage));
    $totalPages = max(1, intval($totalPages));
    
    $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';
    
    if ($currentPage > 1) {
        $html .= '<a href="' . htmlspecialchars($baseUrl . $separator . 'page=1') . '" class="btn btn-sm btn-secondary">首页</a>';
        $html .= '<a href="' . htmlspecialchars($baseUrl . $separator . 'page=' . ($currentPage - 1)) . '" class="btn btn-sm btn-secondary">上一页</a>';
    }
    
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $html .= '<span style="color: var(--gray-400);">...</span>';
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<button class="btn btn-sm btn-primary" disabled style="cursor: default;">' . $i . '</button>';
        } else {
            $html .= '<a href="' . htmlspecialchars($baseUrl . $separator . 'page=' . $i) . '" class="btn btn-sm btn-secondary">' . $i . '</a>';
        }
    }
    
    if ($endPage < $totalPages) {
        $html .= '<span style="color: var(--gray-400);">...</span>';
    }
    
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . htmlspecialchars($baseUrl . $separator . 'page=' . ($currentPage + 1)) . '" class="btn btn-sm btn-secondary">下一页</a>';
        $html .= '<a href="' . htmlspecialchars($baseUrl . $separator . 'page=' . $totalPages) . '" class="btn btn-sm btn-secondary">末页</a>';
    }
    
    $html .= '<span style="color: var(--gray-500); margin-left: 10px;">第 ' . $currentPage . ' / ' . $totalPages . ' 页</span>';
    $html .= '</div>';
    
    return $html;
}

/**
 * 显示消息框
 */
function showMessage($message, $type = 'success') {
    $typeClass = 'alert-' . $type;
    $icon = 'fa-check-circle';
    
    if ($type === 'danger') {
        $icon = 'fa-exclamation-circle';
    } elseif ($type === 'warning') {
        $icon = 'fa-exclamation-triangle';
    } elseif ($type === 'info') {
        $icon = 'fa-info-circle';
    }
    
    return '<div class="alert ' . htmlspecialchars($typeClass) . '" style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; margin-bottom: 16px; border-radius: 6px; border-left: 4px solid;">
        <i class="fa ' . htmlspecialchars($icon) . '" style="font-size: 18px;"></i>
        <span>' . htmlspecialchars($message) . '</span>
    </div>';
}

/**
 * 记录操作日志
 */
function logOperation($action, $details = '') {
    try {
        $db = Database::getInstance();
        $adminUsername = $_SESSION['admin_username'] ?? 'Unknown';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $db->insert('operation_logs', [
            'action' => $action,
            'content' => $details,
            'operator' => $adminUsername,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'create_time' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
    }
}

/**
 * PRG 模式：表单提交后重定向
 * 防止用户刷新页面导致表单重复提交
 */
function redirectAfterPost($url, $message = '', $messageType = 'success') {
    if (!empty($message)) {
        $_SESSION['prg_message'] = $message;
        $_SESSION['prg_message_type'] = $messageType;
    }
    header('Location: ' . $url);
    exit;
}

/**
 * 获取 PRG 消息并清除
 */
function getPrgMessage() {
    $message = $_SESSION['prg_message'] ?? '';
    $type = $_SESSION['prg_message_type'] ?? 'success';
    
    unset($_SESSION['prg_message']);
    unset($_SESSION['prg_message_type']);
    
    if (!empty($message)) {
        return showMessage($message, $type);
    }
    
    return '';
}

/**
 * 保存管理员设置
 */
function saveAdminSetting($settingKey, $settingValue) {
    $db = Database::getInstance();
    $adminId = $_SESSION['admin_id'] ?? 0;
    
    if (!$adminId) {
        return false;
    }
    
    $jsonValue = json_encode($settingValue, JSON_UNESCAPED_UNICODE);
    
    $exists = $db->fetch(
        "SELECT id FROM " . $db->getTable('admin_settings') . " WHERE admin_id = ? AND setting_key = ?",
        [$adminId, $settingKey]
    );
    
    if ($exists) {
        return $db->update(
            'admin_settings',
            ['setting_value' => $jsonValue],
            "admin_id = ? AND setting_key = ?",
            [$adminId, $settingKey]
        );
    } else {
        return $db->insert('admin_settings', [
            'admin_id' => $adminId,
            'setting_key' => $settingKey,
            'setting_value' => $jsonValue
        ]);
    }
}

/**
 * 获取管理员设置
 */
function getAdminSetting($settingKey, $defaultValue = null) {
    $db = Database::getInstance();
    $adminId = $_SESSION['admin_id'] ?? 0;
    
    if (!$adminId) {
        return $defaultValue;
    }
    
    $result = $db->fetch(
        "SELECT setting_value FROM " . $db->getTable('admin_settings') . " WHERE admin_id = ? AND setting_key = ?",
        [$adminId, $settingKey]
    );
    
    if ($result && $result['setting_value']) {
        return json_decode($result['setting_value'], true);
    }
    
    return $defaultValue;
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/footer.php';
