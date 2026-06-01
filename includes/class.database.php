<?php
/**
 * 数据库操作类
 */
class Database {
    private static $instance = null;
    private $pdo;
    private $stmt;
    private $prefix;
    
    /**
     * 获取单例实例
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数 - 建立数据库连接
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
        } catch (PDOException $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取带前缀的表名
     */
    public function getTable($table) {
        return $this->prefix . $table;
    }
    
    /**
     * 防止克隆
     */
    private function __clone() {}
    
    /**
     * 执行SQL查询
     */
    public function query($sql, $params = []) {
        try {
            $this->stmt = $this->pdo->prepare($sql);
            $this->stmt->execute($params);
            return $this->stmt;
        } catch (PDOException $e) {
            throw new Exception("查询执行失败: " . $e->getMessage());
        }
    }
    
    /**
     * 执行SQL语句（INSERT、UPDATE、DELETE）
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 获取单行记录
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 获取单行记录（别名）
     */
    public function fetchOne($sql, $params = []) {
        return $this->fetch($sql, $params);
    }
    
    /**
     * 获取所有记录
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * 获取记录数
     */
    public function count($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 插入数据
     */
    public function insert($table, $data) {
        $table = $this->getTable($this->validateTableName($table));
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 更新数据
     */
    public function update($table, $data, $where, $whereParams = []) {
        $table = $this->getTable($this->validateTableName($table));
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE {$where}";
        $params = array_merge($values, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 删除数据
     */
    public function delete($table, $where, $params = []) {
        $table = $this->getTable($this->validateTableName($table));
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * 验证表名是否合法（防止SQL注入）
     */
    private function validateTableName($table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new Exception("非法的表名: " . $table);
        }
        return $table;
    }
    
    /**
     * 验证字段名是否合法
     */
    private function validateColumnName($column) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new Exception("非法的字段名: " . $column);
        }
        return $column;
    }
    
    /**
     * 获取PDO实例
     */
    public function getPdo() {
        return $this->pdo;
    }
}
