<?php
/**
 * 应用管理类 (适配456项目 category 表结构)
 */
class App {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 创建应用
     */
    public function create($data) {
        // 将标准字段映射到 category 字段
        $categoryData = $this->mapToCategoryFields($data);
        $categoryData['createtime'] = time();
        $categoryData['updatetime'] = time();
        return $this->db->insert('category', $categoryData);
    }
    
    /**
     * 获取应用
     */
    public function get($id) {
        return $this->db->fetch(
            "SELECT * FROM " . $this->db->getTable('category') . " WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * 更新应用
     */
    public function update($id, $data) {
        $categoryData = $this->mapToCategoryFields($data);
        // 如果 $data 中已经包含 updatetime，则使用它；否则使用当前时间戳
        if (!isset($categoryData['updatetime'])) {
            $categoryData['updatetime'] = time();
        }
        return $this->db->update('category', $categoryData, "id = ?", [$id]);
    }
    
    /**
     * 删除应用
     */
    public function delete($id) {
        return $this->db->delete('category', "id = ?", [$id]);
    }
    
    /**
     * 获取应用列表
     */
    public function getList($page = 1, $limit = 20, $type = null, $status = null, $keyword = '') {
        $where = "WHERE 1=1";
        $params = [];
        
        if ($type !== null && $type !== '') {
            $where .= " AND type = ?";
            $params[] = $type;
        }
        
        if ($status !== null && $status !== '') {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        
        if (!empty($keyword)) {
            $where .= " AND (name LIKE ? OR nickname LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        
        // 获取总数
        $countResult = $this->db->fetch(
            "SELECT COUNT(*) as total FROM " . $this->db->getTable('category') . " {$where}",
            $params
        );
        $total = $countResult['total'];
        
        // 获取列表
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM " . $this->db->getTable('category') . " {$where} ORDER BY weigh DESC, id DESC LIMIT ?, ?";
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
     * 获取所有启用的应用（用于AppStore API）
     */
    public function getAllActive() {
        // 首先尝试获取状态为 normal 的应用
        $result = $this->db->fetchAll(
            "SELECT * FROM " . $this->db->getTable('category') . " WHERE status = 'normal' ORDER BY weigh DESC, id DESC"
        );
        
        // 如果没有 normal 状态的应用，获取所有应用
        if (empty($result)) {
            $result = $this->db->fetchAll(
                "SELECT * FROM " . $this->db->getTable('category') . " ORDER BY weigh DESC, id DESC LIMIT 100"
            );
        }
        
        return $result;
    }
    
    /**
     * 获取应用统计
     */
    public function getStatistics() {
        $stats = [];
        
        // 总应用数
        $result = $this->db->fetch("SELECT COUNT(*) as total FROM " . $this->db->getTable('category') . "");
        $stats['total'] = $result['total'];
        
        // 启用应用
        $result = $this->db->fetch("SELECT COUNT(*) as active FROM " . $this->db->getTable('category') . " WHERE status = 'normal'");
        $stats['active'] = $result['active'];
        
        // 禁用应用
        $result = $this->db->fetch("SELECT COUNT(*) as disabled FROM " . $this->db->getTable('category') . " WHERE status = 'hidden'");
        $stats['disabled'] = $result['disabled'];
        
        return $stats;
    }
    
    /**
     * 启用应用
     */
    public function enable($id) {
        return $this->db->update('category', ['status' => 'normal'], "id = ?", [$id]);
    }
    
    /**
     * 禁用应用
     */
    public function disable($id) {
        return $this->db->update('category', ['status' => 'hidden'], "id = ?", [$id]);
    }
    
    /**
     * 将标准字段映射到 category 字段
     */
    private function mapToCategoryFields($data) {
        $mapping = [
            'name' => 'name',
            'nickname' => 'nickname',
            'type' => 'type',
            'image' => 'image',
            'keywords' => 'keywords',
            'description' => 'description',
            'bt1a' => 'bt1a',
            'bt1b' => 'bt1b',
            'bt2a' => 'bt2a',
            'bt2b' => 'bt2b',
            'flag' => 'flag',
            'weigh' => 'weigh',
            'status' => 'status',
            'pid' => 'pid',
            'beizhu' => 'beizhu',
            'updatetime' => 'updatetime'
        ];
        
        $result = [];
        foreach ($data as $key => $value) {
            if (isset($mapping[$key])) {
                // bt1a 字段为 TEXT 类型，无需截断
                $result[$mapping[$key]] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * 将应用转换为API格式
     */
    public static function formatForApi($app) {
        // 处理文件大小
        $size = 0;
        if (!empty($app['bt2a'])) {
            $size = is_numeric($app['bt2a']) ? intval($app['bt2a']) : 0;
        }
        
        // 处理版本日期
        $versionDate = '';
        if (!empty($app['updatetime'])) {
            $versionDate = date('Y-m-d\TH:i:sP', $app['updatetime']);
        }
        
        return [
            'name' => $app['name'] ?? '',
            'type' => $app['type'] ?? 'default',
            'version' => $app['nickname'] ?? '',
            'versionDate' => $versionDate,
            'versionDescription' => $app['keywords'] ?? '',
            'lock' => strval($app['bt2b'] ?? '0'),
            'downloadURL' => $app['bt1a'] ?? '',
            'isLanZouCloud' => strval($app['flag'] ?? '0'),
            'iconURL' => $app['image'] ?? '',
            'tintColor' => $app['bt1b'] ?? '018084',
            'size' => strval($size)
        ];
    }
}
