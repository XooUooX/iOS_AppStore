<?php
/**
 * 设备管理类
 */
class Device {
    private $db;
    
    // 设备状态
    const STATUS_DISABLED = 0; // 禁用
    const STATUS_ACTIVE = 1;   // 正常
    const STATUS_EXPIRED = 2;  // 过期
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 创建设备记录
     */
    public function create($data) {
        return $this->db->insert('devices', $data);
    }
    
    /**
     * 检查设备是否存在
     */
    public function exists($deviceId) {
        $result = $this->db->fetch(
            "SELECT id FROM " . $this->db->getTable('devices') . " WHERE device_id = ?",
            [$deviceId]
        );
        return !empty($result);
    }
    
    /**
     * 获取设备信息
     */
    public function get($deviceId) {
        return $this->db->fetch(
            "SELECT * FROM " . $this->db->getTable('devices') . " WHERE device_id = ?",
            [$deviceId]
        );
    }
    
    /**
     * 通过卡密获取设备
     */
    public function getByCardKey($cardKey) {
        return $this->db->fetch(
            "SELECT * FROM " . $this->db->getTable('devices') . " WHERE card_key = ?",
            [$cardKey]
        );
    }
    
    /**
     * 更新设备信息
     */
    public function update($deviceId, $data) {
        return $this->db->update('devices', $data, "device_id = ?", [$deviceId]);
    }
    
    /**
     * 获取设备列表
     */
    public function getList($page = 1, $limit = 20, $status = null, $keyword = '') {
        $where = "WHERE 1=1";
        $params = [];
        
        if ($status !== null && $status !== '') {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        
        if (!empty($keyword)) {
            $where .= " AND (device_id LIKE ? OR card_key LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        
        // 获取总数
        $countResult = $this->db->fetch(
            "SELECT COUNT(*) as total FROM " . $this->db->getTable('devices') . " {$where}",
            $params
        );
        $total = $countResult['total'];
        
        // 获取列表
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM " . $this->db->getTable('devices') . " {$where} ORDER BY create_time DESC LIMIT ?, ?";
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
     * 验证设备是否有效
     */
    public function validate($deviceId) {
        $device = $this->get($deviceId);
        
        if (!$device) {
            return ['valid' => false, 'message' => '设备未注册'];
        }
        
        if ($device['status'] == self::STATUS_DISABLED) {
            return ['valid' => false, 'message' => '设备已被禁用'];
        }
        
        if ($device['status'] == self::STATUS_EXPIRED || strtotime($device['expire_time']) < time()) {
            // 更新状态为过期
            $this->update($deviceId, ['status' => self::STATUS_EXPIRED]);
            return ['valid' => false, 'message' => '设备授权已过期'];
        }
        
        // 更新最后活跃时间
        $this->update($deviceId, [
            'last_active_time' => date('Y-m-d H:i:s'),
            'ip_address' => $this->getClientIp()
        ]);
        
        return [
            'valid' => true,
            'device' => $device,
            'expire_time' => $device['expire_time'],
            'days_left' => ceil((strtotime($device['expire_time']) - time()) / 86400)
        ];
    }
    
    /**
     * 禁用设备
     */
    public function disable($deviceId) {
        return $this->db->update('devices', [
            'status' => self::STATUS_DISABLED
        ], "device_id = ?", [$deviceId]);
    }
    
    /**
     * 启用设备
     */
    public function enable($deviceId) {
        $device = $this->get($deviceId);
        if (!$device) return false;
        
        $status = strtotime($device['expire_time']) < time() ? self::STATUS_EXPIRED : self::STATUS_ACTIVE;
        
        return $this->db->update('devices', [
            'status' => $status
        ], "device_id = ?", [$deviceId]);
    }
    
    /**
     * 删除设备
     */
    public function delete($deviceId) {
        return $this->db->delete('devices', "device_id = ?", [$deviceId]);
    }
    
    /**
     * 获取设备统计
     */
    public function getStatistics() {
        $stats = [];
        
        // 总设备数
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM " . $this->db->getTable('devices') . "");
        $stats['total'] = $result['total'];
        
        // 正常设备
        $result = $this->db->fetch("SELECT COUNT(*) as active FROM " . $this->db->getTable('devices') . " WHERE status = 1");
        $stats['active'] = $result['active'];
        
        // 禁用设备
        $result = $this->db->fetch("SELECT COUNT(*) as disabled FROM " . $this->db->getTable('devices') . " WHERE status = 0");
        $stats['disabled'] = $result['disabled'];
        
        // 过期设备
        $result = $this->db->fetch("SELECT COUNT(*) as expired FROM " . $this->db->getTable('devices') . " WHERE status = 2 OR expire_time < NOW()");
        $stats['expired'] = $result['expired'];
        
        // 今日新增
        $result = $this->db->fetch("SELECT COUNT(*) as today FROM " . $this->db->getTable('devices') . " WHERE DATE(create_time) = CURDATE()");
        $stats['today'] = $result['today'];
        
        return $stats;
    }
    
    /**
     * 获取设备状态名称
     */
    public static function getStatusName($status) {
        $statuses = [
            self::STATUS_DISABLED => '已禁用',
            self::STATUS_ACTIVE => '正常',
            self::STATUS_EXPIRED => '已过期'
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
