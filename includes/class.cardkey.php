<?php
/**
 * 卡密管理类
 */
class CardKey {
    private $db;
    
    // 卡密类型
    const TYPE_MONTH = 1;    // 月卡
    const TYPE_SEASON = 2; // 季卡
    const TYPE_YEAR = 3;   // 年卡
    const TYPE_FOREVER = 4; // 永久
    
    // 卡密状态
    const STATUS_UNUSED = 0;   // 未使用
    const STATUS_USED = 1;     // 已使用
    const STATUS_DISABLED = 2; // 已禁用
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 生成随机卡密
     */
    public function generateKey($length = null, $prefix = '') {
        $length = $length ?: (defined('CARD_KEY_LENGTH') ? CARD_KEY_LENGTH : 16);
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $key = '';
        for ($i = 0; $i < $length; $i++) {
            $key .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        // 优先使用传入的前缀，否则使用默认前缀
        $finalPrefix = !empty($prefix) ? $prefix : (defined('CARD_KEY_PREFIX') ? CARD_KEY_PREFIX : '');
        return $finalPrefix . $key;
    }
    
    /**
     * 批量生成卡密
     */
    public function batchGenerate($count, $type = 1, $expireDays = 30, $remark = '', $prefix = '') {
        $keys = [];
        $success = 0;
        $failed = 0;
        
        // 强制前缀为大写
        $prefix = strtoupper($prefix);
        
        // 处理永久卡密（expireDays为空或为0）
        if (empty($expireDays) || $expireDays === '') {
            $expireDays = 36500; // 永久卡密设为100年
        }
        
        $this->db->beginTransaction();
        
        try {
            for ($i = 0; $i < $count; $i++) {
                $cardKey = $this->generateKey(defined('CARD_KEY_LENGTH') ? CARD_KEY_LENGTH : 16, $prefix);
                
                // 确保卡密唯一
                while ($this->exists($cardKey)) {
                    $cardKey = $this->generateKey(defined('CARD_KEY_LENGTH') ? CARD_KEY_LENGTH : 16, $prefix);
                }
                
                $data = [
                    'card_key' => $cardKey,
                    'card_type' => $type,
                    'expire_days' => intval($expireDays),
                    'status' => self::STATUS_UNUSED,
                    'remark' => $remark,
                    'create_time' => date('Y-m-d H:i:s')
                ];
                
                try {
                    $this->db->insert('card_keys', $data);
                    $keys[] = $cardKey;
                    $success++;
                } catch (Exception $e) {
                    $failed++;
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'generated' => $success,
                'failed' => $failed,
                'keys' => $keys
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 检查卡密是否存在
     */
    public function exists($cardKey) {
        $result = $this->db->fetch(
            "SELECT id FROM " . $this->db->getTable('card_keys') . " WHERE card_key = ?",
            [$cardKey]
        );
        return !empty($result);
    }
    
    /**
     * 获取卡密信息
     */
    public function get($cardKey) {
        return $this->db->fetch(
            "SELECT * FROM " . $this->db->getTable('card_keys') . " WHERE card_key = ?",
            [$cardKey]
        );
    }
    
    /**
     * 获取卡密列表
     */
    public function getList($page = 1, $limit = 20, $status = null, $keyword = '') {
        $where = "WHERE 1=1";
        $params = [];
        
        if ($status !== null && $status !== '') {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        
        if (!empty($keyword)) {
            $where .= " AND (card_key LIKE ? OR remark LIKE ? OR bind_device_id LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        
        // 获取总数
        $countResult = $this->db->fetch(
            "SELECT COUNT(*) as total FROM " . $this->db->getTable('card_keys') . " {$where}",
            $params
        );
        $total = $countResult['total'];
        
        // 获取列表
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM " . $this->db->getTable('card_keys') . " {$where} ORDER BY create_time DESC LIMIT ?, ?";
        $params[] = (int)$offset;
        $params[] = (int)$limit;
        $list = $this->db->fetchAll($sql, $params);
        
        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
            'list' => $list
        ];
    }
    
    /**
     * 激活卡密
     */
    public function activate($cardKey, $deviceId) {
        $card = $this->get($cardKey);
        
        if (!$card) {
            return ['success' => false, 'code' => 404, 'message' => '卡密不存在'];
        }
        
        if ($card['status'] == self::STATUS_DISABLED) {
            return ['success' => false, 'code' => 403, 'message' => '卡密已被禁用'];
        }
        
        if ($card['status'] == self::STATUS_USED) {
            // 检查是否是同一设备
            if ($card['bind_device_id'] == $deviceId) {
                // 允许同一设备重新激活，更新设备信息
                return $this->reactivateDevice($card, $deviceId);
            }
            return ['success' => false, 'code' => 400, 'message' => '卡密已被其他设备使用'];
        }
        
        // 计算到期时间 - 永久卡设为2099-12-31
        if ($card['card_type'] == self::TYPE_FOREVER) {
            $expireTime = '2099-12-31 23:59:59';
        } else {
            $expireTime = date('Y-m-d H:i:s', strtotime("+{$card['expire_days']} days"));
        }
        
        $this->db->beginTransaction();
        
        try {
            // 更新卡密状态
            $this->db->update('card_keys', [
                'status' => self::STATUS_USED,
                'use_time' => date('Y-m-d H:i:s'),
                'bind_device_id' => $deviceId
            ], "card_key = ?", [$cardKey]);
            
            // 创建设备记录或更新现有设备
            $device = new Device();
            $deviceData = [
                'device_id' => $deviceId,
                'card_key' => $cardKey,
                'bind_time' => date('Y-m-d H:i:s'),
                'expire_time' => $expireTime,
                'status' => 1,
                'last_active_time' => date('Y-m-d H:i:s'),
                'active_count' => 1,
                'ip_address' => $this->getClientIp()
            ];
            
            if ($device->exists($deviceId)) {
                $device->update($deviceId, $deviceData);
            } else {
                $device->create($deviceData);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'code' => 200,
                'message' => '激活成功',
                'data' => [
                    'expire_time' => $expireTime,
                    'expire_days' => $card['expire_days'],
                    'device_id' => $deviceId
                ]
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'code' => 500, 'message' => '激活失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 重新激活同一设备
     */
    private function reactivateDevice($card, $deviceId) {
        $device = new Device();
        $deviceInfo = $device->get($deviceId);
        
        if (!$deviceInfo) {
            return ['success' => false, 'code' => 404, 'message' => '设备信息不存在'];
        }
        
        // 检查是否过期
        if (strtotime($deviceInfo['expire_time']) < time()) {
            return ['success' => false, 'code' => 403, 'message' => '卡密已过期，请重新购买'];
        }
        
        // 更新设备信息
        $device->update($deviceId, [
            'last_active_time' => date('Y-m-d H:i:s'),
            'active_count' => $deviceInfo['active_count'] + 1,
            'ip_address' => $this->getClientIp()
        ]);
        
        return [
            'success' => true,
            'code' => 200,
            'message' => '重新激活成功',
            'data' => [
                'expire_time' => $deviceInfo['expire_time'],
                'expire_days' => $card['expire_days'],
                'device_id' => $deviceId
            ]
        ];
    }
    
    /**
     * 禁用卡密
     */
    public function disable($cardKey) {
        return $this->db->update('card_keys', [
            'status' => self::STATUS_DISABLED
        ], "card_key = ?", [$cardKey]);
    }
    
    /**
     * 删除卡密
     */
    public function delete($cardKey) {
        return $this->db->delete('card_keys', "card_key = ?", [$cardKey]);
    }
    
    /**
     * 获取卡密类型名称
     */
    public static function getTypeName($type) {
        $types = [
            self::TYPE_MONTH => '月卡',
            self::TYPE_SEASON => '季卡',
            self::TYPE_YEAR => '年卡',
            self::TYPE_FOREVER => '永久'
        ];
        return $types[$type] ?? '未知';
    }
    
    /**
     * 获取卡密状态名称
     */
    public static function getStatusName($status) {
        $statuses = [
            self::STATUS_UNUSED => '未使用',
            self::STATUS_USED => '已使用',
            self::STATUS_DISABLED => '已禁用'
        ];
        return $statuses[$status] ?? '未知';
    }
    
    /**
     * 获取客户端IP
     */
    private function getClientIp() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
