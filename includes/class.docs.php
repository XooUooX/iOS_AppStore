<?php
/**
 * 文档管理类
 */
class Docs {
    private $db;
    private $table = 'docs';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取所有文档
     */
    public function getAllDocs($category = null, $status = null) {
        $sql = "SELECT * FROM " . $this->db->getTable($this->table);
        $params = [];
        $conditions = [];
        
        if ($status !== null) {
            $conditions[] = "status = ?";
            $params[] = $status;
        }
        
        if ($category !== null) {
            $conditions[] = "category = ?";
            $params[] = $category;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY sort DESC, id DESC";
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 获取文档详情
     */
    public function getDocById($id) {
        $sql = "SELECT * FROM " . $this->db->getTable($this->table) . " WHERE id = ?";
        $doc = $this->db->fetchOne($sql, [$id]);
        
        if ($doc) {
            // 增加浏览次数
            $this->db->execute(
                "UPDATE " . $this->db->getTable($this->table) . " SET views = views + 1 WHERE id = ?",
                [$id]
            );
        }
        
        return $doc;
    }
    
    /**
     * 通过slug获取文档
     */
    public function getDocBySlug($slug) {
        $sql = "SELECT * FROM " . $this->db->getTable($this->table) . " WHERE slug = ? AND status = 1";
        $doc = $this->db->fetchOne($sql, [$slug]);
        
        if ($doc) {
            // 增加浏览次数
            $this->db->execute(
                "UPDATE " . $this->db->getTable($this->table) . " SET views = views + 1 WHERE id = ?",
                [$doc['id']]
            );
        }
        
        return $doc;
    }
    
    /**
     * 创建文档
     */
    public function createDoc($data) {
        // 检查slug是否已存在
        $existing = $this->db->fetchOne(
            "SELECT id FROM " . $this->db->getTable($this->table) . " WHERE slug = ?",
            [$data['slug']]
        );
        
        if ($existing) {
            return false;
        }
        
        $sql = "INSERT INTO " . $this->db->getTable($this->table) . " 
                (title, slug, content, description, category, sort, status, create_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        return $this->db->execute($sql, [
            $data['title'],
            $data['slug'],
            $data['content'],
            $data['description'] ?? '',
            $data['category'] ?? 'help',
            $data['sort'] ?? 0,
            $data['status'] ?? 1
        ]);
    }
    
    /**
     * 更新文档
     */
    public function updateDoc($id, $data) {
        // 检查slug是否被其他文档使用
        if (isset($data['slug'])) {
            $existing = $this->db->fetchOne(
                "SELECT id FROM " . $this->db->getTable($this->table) . " WHERE slug = ? AND id != ?",
                [$data['slug'], $id]
            );
            
            if ($existing) {
                return false;
            }
        }
        
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $updates[] = "$key = ?";
            $params[] = $value;
        }
        
        $updates[] = "update_time = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE " . $this->db->getTable($this->table) . " SET " . implode(', ', $updates) . " WHERE id = ?";
        return $this->db->execute($sql, $params);
    }
    
    /**
     * 删除文档
     */
    public function deleteDoc($id) {
        $sql = "DELETE FROM " . $this->db->getTable($this->table) . " WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * 获取分类统计
     */
    public function getCategoryStats() {
        $sql = "SELECT category, COUNT(*) as count FROM " . $this->db->getTable($this->table) . " WHERE status = 1 GROUP BY category";
        $results = $this->db->fetchAll($sql);
        
        $stats = [];
        foreach ($results as $row) {
            $stats[$row['category']] = $row['count'];
        }
        
        return $stats;
    }
}
?>
