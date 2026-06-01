<?php
/**
 * 卡密领取管理类
 */
class CardKeyClaim {
    private $db;
    
    // 状态常量
    const STATUS_UNUSED = 0;   // 未领取
    const STATUS_USED = 1;     // 已领取
    const STATUS_DISABLED = 2; // 已禁用
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 创建领取批次
     */
    public function createBatch($data) {
        $batchData = [
            'batch_name' => $data['batch_name'],
            'batch_type' => intval($data['batch_type']),
            'expire_days' => intval($data['expire_days']),
            'total_count' => 0,
            'used_count' => 0,
            'status' => intval($data['status'] ?? 1),
            'password' => $data['password'] ?? '',
            'remark' => $data['remark'] ?? '',
            // 领取限制配置
            'id_limit_type' => intval($data['id_limit_type'] ?? 0),
            'id_limit_count' => intval($data['id_limit_count'] ?? 1),
            'ip_limit_type' => intval($data['ip_limit_type'] ?? 0),
            'ip_limit_count' => intval($data['ip_limit_count'] ?? 1),
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('card_key_batches', $batchData);
    }
    
    /**
     * 更新批次
     */
    public function updateBatch($batchId, $data) {
        $updateData = [
            'batch_name' => $data['batch_name'],
            'batch_type' => intval($data['batch_type']),
            'expire_days' => intval($data['expire_days']),
            'status' => intval($data['status']),
            'password' => $data['password'] ?? '',
            'remark' => $data['remark'] ?? '',
            // 领取限制配置
            'id_limit_type' => intval($data['id_limit_type'] ?? 0),
            'id_limit_count' => intval($data['id_limit_count'] ?? 1),
            'ip_limit_type' => intval($data['ip_limit_type'] ?? 0),
            'ip_limit_count' => intval($data['ip_limit_count'] ?? 1),
            'update_time' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->update('card_key_batches', $updateData, "id = ?", [$batchId]);
    }
    
    /**
     * 删除批次
     */
    public function deleteBatch($batchId) {
        $this->db->beginTransaction();
        
        try {
            // 先删除关联的卡密
            $this->db->delete('card_key_batch_items', "batch_id = ?", [$batchId]);
            // 再删除批次
            $this->db->delete('card_key_batches', "id = ?", [$batchId]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * 获取批次信息
     */
    public function getBatch($batchId) {
        return $this->db->fetch(
            "SELECT * FROM " . $this->db->getTable('card_key_batches') . " WHERE id = ?",
            [$batchId]
        );
    }
    
    /**
     * 获取批次列表
     */
    public function getBatchList($page = 1, $limit = 20, $status = null) {
        $where = "WHERE 1=1";
        $params = [];
        
        if ($status !== null && $status !== '') {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        
        // 获取总数
        $countResult = $this->db->fetch(
            "SELECT COUNT(*) as total FROM " . $this->db->getTable('card_key_batches') . " {$where}",
            $params
        );
        $total = $countResult['total'];
        
        // 获取列表
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM " . $this->db->getTable('card_key_batches') . " {$where} ORDER BY create_time DESC LIMIT ?, ?";
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
     * 添加卡密到批次
     */
    public function addCardsToBatch($batchId, $cardKeys) {
        $this->db->beginTransaction();
        
        try {
            $added = 0;
            foreach ($cardKeys as $cardKey) {
                // 检查卡密是否已在该批次中
                $exists = $this->db->fetch(
                    "SELECT id FROM " . $this->db->getTable('card_key_batch_items') . " WHERE batch_id = ? AND card_key = ?",
                    [$batchId, $cardKey]
                );
                
                if (!$exists) {
                    $this->db->insert('card_key_batch_items', [
                        'batch_id' => $batchId,
                        'card_key' => $cardKey,
                        'status' => self::STATUS_UNUSED,
                        'create_time' => date('Y-m-d H:i:s')
                    ]);
                    $added++;
                }
            }
            
            // 更新批次数量
            $this->updateBatchCount($batchId);
            
            $this->db->commit();
            return ['success' => true, 'added' => $added];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 从批次移除卡密
     */
    public function removeCardFromBatch($itemId) {
        $item = $this->db->fetch(
            "SELECT * FROM " . $this->db->getTable('card_key_batch_items') . " WHERE id = ?",
            [$itemId]
        );
        
        if (!$item || $item['status'] == self::STATUS_USED) {
            return false; // 已领取的卡密不能移除
        }
        
        $result = $this->db->delete('card_key_batch_items', "id = ?", [$itemId]);
        
        if ($result) {
            $this->updateBatchCount($item['batch_id']);
        }
        
        return $result;
    }
    
    /**
     * 更新批次数量统计
     */
    private function updateBatchCount($batchId) {
        $result = $this->db->fetch(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as used
            FROM " . $this->db->getTable('card_key_batch_items') . " 
            WHERE batch_id = ?",
            [$batchId]
        );
        
        $this->db->update('card_key_batches', [
            'total_count' => intval($result['total']),
            'used_count' => intval($result['used']),
            'update_time' => date('Y-m-d H:i:s')
        ], "id = ?", [$batchId]);
    }
    
    /**
     * 获取批次中的卡密列表
     */
    public function getBatchItems($batchId, $page = 1, $limit = 20, $status = null) {
        $where = "WHERE batch_id = ?";
        $params = [$batchId];
        
        if ($status !== null && $status !== '') {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM " . $this->db->getTable('card_key_batch_items') . " {$where} ORDER BY create_time DESC LIMIT ?, ?";
        $params[] = (int)$offset;
        $params[] = (int)$limit;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 获取批次中卡密总数
     */
    public function getBatchItemCount($batchId, $status = null) {
        $where = "WHERE batch_id = ?";
        $params = [$batchId];
        
        if ($status !== null && $status !== '') {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM " . $this->db->getTable('card_key_batch_items') . " {$where}", $params);
        return intval($result['total']);
    }
    
    /**
     * 领取卡密（支持领取限制配置）
     * 
     * @param int $batchId 批次ID
     * @param string $deviceId 设备ID（用户填写的UDID）
     * @param string $deviceName 设备名称（可选）
     * @return array ['success' => bool, 'message' => string, 'card_key' => string]
     */
    public function claimCard($batchId, $deviceId, $deviceName = '') {
        $this->db->beginTransaction();
        
        try {
            // 检查批次状态
            $batch = $this->getBatch($batchId);
            if (!$batch || $batch['status'] != 1) {
                $this->db->rollback();
                return ['success' => false, 'message' => '领取活动已结束或不存在'];
            }
            
            // 获取当前IP
            $ipAddress = $this->getClientIp();
            
            // 检查黑名单
            if ($this->isBlacklisted($batchId, $deviceId, $ipAddress)) {
                $this->db->rollback();
                return ['success' => false, 'message' => '您的设备或IP已被限制领取'];
            }
            
            // 检查领取限制
            $limitCheck = $this->checkClaimLimit($batchId, $deviceId, $ipAddress, $batch);
            if (!$limitCheck['success']) {
                $this->db->rollback();
                return ['success' => false, 'message' => $limitCheck['message']];
            }
            
            // 获取一个未领取的卡密
            $card = $this->db->fetch(
                "SELECT * FROM " . $this->db->getTable('card_key_batch_items') . " 
                WHERE batch_id = ? AND status = 0 
                ORDER BY id ASC LIMIT 1",
                [$batchId]
            );
            
            if (!$card) {
                $this->db->rollback();
                return ['success' => false, 'message' => '卡密已被领取完毕'];
            }
            
            // 更新卡密状态（只记录领取日志，领取时不绑定设备，激活时才绑定）
            $this->db->update('card_key_batch_items', [
                'status' => self::STATUS_USED,
                'device_id' => $deviceId,  // 记录领取时使用的设备ID
                'device_uuid' => $deviceId, // 使用相同的deviceId作为device_uuid
                'device_name' => $deviceName,
                'ip_address' => $ipAddress,
                'claim_time' => date('Y-m-d H:i:s')
            ], "id = ?", [$card['id']]);
            
            // 更新批次统计
            $this->updateBatchCount($batchId);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'card_key' => $card['card_key'],
                'message' => '领取成功'
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => '领取失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 检查是否在黑名单中
     */
    private function isBlacklisted($batchId, $deviceId, $ipAddress) {
        // 先检查设备ID黑名单
        $sql1 = "SELECT id FROM " . $this->db->getTable('blacklist') . " 
                WHERE (batch_id = ? OR batch_id IS NULL) 
                AND (expire_time IS NULL OR expire_time > NOW())
                AND type = 1 AND value = ?";
        
        $result1 = $this->db->fetch($sql1, [$batchId, $deviceId]);
        if (!empty($result1)) {
            return true;
        }
        
        // 再检查IP黑名单
        $sql2 = "SELECT id FROM " . $this->db->getTable('blacklist') . " 
                WHERE (batch_id = ? OR batch_id IS NULL) 
                AND (expire_time IS NULL OR expire_time > NOW())
                AND type = 2 AND value = ?";
        
        $result2 = $this->db->fetch($sql2, [$batchId, $ipAddress]);
        if (!empty($result2)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 检查领取限制
     */
    private function checkClaimLimit($batchId, $deviceId, $ipAddress, $batch) {
        // ID限制检查（设备ID限制）
        if (!empty($batch['id_limit_type']) && $batch['id_limit_type'] == 1) {
            $count = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM " . $this->db->getTable('card_key_batch_items') . " 
                WHERE batch_id = ? AND device_id = ? AND status = 1",
                [$batchId, $deviceId]
            );
            if ($count['cnt'] >= $batch['id_limit_count']) {
                return ['success' => false, 'message' => '该设备已达到领取次数限制'];
            }
        }
        
        // IP限制检查
        if (!empty($batch['ip_limit_type']) && $batch['ip_limit_type'] >= 1) {
            $ipWhere = "batch_id = ? AND ip_address = ? AND status = 1";
            $params = [$batchId, $ipAddress];
            
            // IP段限制（简化实现：匹配前3段）
            if ($batch['ip_limit_type'] == 2) {
                $ipParts = explode('.', $ipAddress);
                if (count($ipParts) == 4) {
                    $ipPrefix = $ipParts[0] . '.' . $ipParts[1] . '.' . $ipParts[2] . '.%';
                    $ipWhere = "batch_id = ? AND ip_address LIKE ? AND status = 1";
                    $params = [$batchId, $ipPrefix];
                }
            }
            
            $count = $this->db->fetch(
                "SELECT COUNT(*) as cnt FROM " . $this->db->getTable('card_key_batch_items') . " WHERE {$ipWhere}",
                $params
            );
            if ($count['cnt'] >= $batch['ip_limit_count']) {
                return ['success' => false, 'message' => '该IP已达到领取次数限制'];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * 添加到黑名单
     */
    public function addToBlacklist($batchId, $type, $value, $reason = '', $expireTime = null) {
        $data = [
            'batch_id' => $batchId,
            'type' => $type,
            'value' => $value,
            'reason' => $reason,
            'expire_time' => $expireTime,
            'create_time' => date('Y-m-d H:i:s')
        ];
        return $this->db->insert('blacklist', $data);
    }
    
    /**
     * 从黑名单移除
     */
    public function removeFromBlacklist($id) {
        return $this->db->delete('blacklist', "id = ?", [$id]);
    }
    
    /**
     * 获取黑名单列表
     */
    public function getBlacklist($batchId = null, $page = 1, $limit = 20) {
        $where = "WHERE 1=1";
        $params = [];
        
        if ($batchId !== null) {
            $where .= " AND (batch_id = ? OR batch_id IS NULL)";
            $params[] = $batchId;
        }
        
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM " . $this->db->getTable('blacklist') . " {$where} ORDER BY create_time DESC LIMIT ?, ?";
        $params[] = (int)$offset;
        $params[] = (int)$limit;
        
        $list = $this->db->fetchAll($sql, $params);
        
        // 获取总数
        $countResult = $this->db->fetch("SELECT COUNT(*) as total FROM " . $this->db->getTable('blacklist') . " {$where}", array_slice($params, 0, -2));
        
        return [
            'list' => $list,
            'total' => $countResult['total'],
            'page' => $page,
            'pages' => ceil($countResult['total'] / $limit)
        ];
    }
    
    /**
     * 获取可用的领取批次（前台）
     */
    public function getActiveBatches() {
        return $this->db->fetchAll(
            "SELECT * FROM " . $this->db->getTable('card_key_batches') . " 
            WHERE status = 1 AND total_count > used_count 
            ORDER BY create_time DESC"
        );
    }
    
    /**
     * UDID脱敏显示
     */
    public static function maskUdid($udid) {
        if (empty($udid) || strlen($udid) < 8) {
            return '-';
        }
        
        // 保留前4位和后5位，中间用****替换
        $len = strlen($udid);
        $prefix = substr($udid, 0, 4);
        $suffix = substr($udid, -5);
        
        // 中间部分分段显示****
        $middleLen = $len - 9; // 减去前缀4位和后缀5位
        $middle = '';
        for ($i = 0; $i < $middleLen; $i++) {
            if ($i > 0 && $i % 4 == 0) {
                $middle .= '-';
            }
            $middle .= '*';
        }
        
        return $prefix . '****' . ($middle ? '-' . $middle : '') . '-' . $suffix;
    }
    
    /**
     * 获取卡密类型名称
     */
    public static function getTypeName($type) {
        $types = [
            1 => '月卡',
            2 => '季卡',
            3 => '年卡',
            4 => '永久'
        ];
        return $types[$type] ?? '未知';
    }
    
    /**
     * 获取状态名称
     */
    public static function getStatusName($status) {
        $statuses = [
            0 => '未领取',
            1 => '已领取',
            2 => '已禁用'
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
