<?php
/**
 * 客户端在线更新类
 * 用于被授权的程序对接 auth_ZLG3ep 授权系统的在线更新功能
 */

class NingSiUpdater {
    private $apiUrl;
    private $domain;
    private $currentVersion;
    private $backupDir;
    private $tempDir;
    private $baseDir;
    
    /**
     * 构造函数
     * @param string $apiUrl 授权系统API地址
     * @param string $currentVersion 当前版本号
     */
    public function __construct($apiUrl, $currentVersion = '1.0.0') {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->domain = $this->getDomain();
        $this->currentVersion = $currentVersion;
        
        // 设置目录（基于项目根目录下的 update/ 文件夹）
        $this->baseDir = dirname(__DIR__);
        $this->backupDir = $this->baseDir . '/update/backup/';
        $this->tempDir = $this->baseDir . '/update/temp/';
        
        // 创建目录
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }
    
    /**
     * 获取当前域名
     */
    private function getDomain() {
        $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        return preg_replace('/:\d+$/', '', $domain);
    }
    
    /**
     * 检查更新
     * @param bool $acceptBeta 是否接受测试版
     * @return array 更新信息
     */
    public function checkUpdate($acceptBeta = false) {
        $build = $this->getCurrentBuild();
        
        $params = [
            'action' => 'check_update',
            'domain' => $this->domain,
            'version' => $this->currentVersion,
            'build' => $build,
            'beta' => $acceptBeta ? 1 : 0
        ];
        
        $response = $this->httpPost($this->apiUrl, $params);
        
        if (!$response) {
            return [
                'has_update' => false,
                'error' => '连接授权服务器失败'
            ];
        }
        
        $result = json_decode($response, true);
        
        if (!$result || $result['status'] !== 'success') {
            return [
                'has_update' => false,
                'error' => $result['message'] ?? '检查更新失败'
            ];
        }
        
        return $result['data'] ?? ['has_update' => false];
    }
    
    /**
     * 执行更新
     * @param array $updateInfo 更新信息（来自 checkUpdate）
     * @return array 更新结果
     */
    public function doUpdate($updateInfo) {
        $versionTo = $updateInfo['version'] ?? '';
        $downloadUrl = $updateInfo['download_url'] ?? '';
        $fileHash = $updateInfo['file_hash'] ?? '';
        
        if (empty($versionTo) || empty($downloadUrl)) {
            return [
                'success' => false,
                'message' => '更新信息不完整'
            ];
        }
        
        try {
            // 1. 创建备份
            $backupFile = $this->createBackup();
            
            // 2. 下载更新包
            $zipFile = $this->downloadUpdate($downloadUrl, $fileHash);
            if (!$zipFile) {
                throw new Exception('下载更新包失败');
            }
            
            // 3. 解压更新包
            $extractDir = $this->extractUpdate($zipFile);
            if (!$extractDir) {
                throw new Exception('解压更新包失败');
            }
            
            // 4. 执行数据库升级（如果有 upgrade.sql）
            $this->applyDbChanges($extractDir);
            
            // 5. 替换文件
            $this->replaceFiles($extractDir);
            
            // 6. 清理临时文件
            $this->cleanup($zipFile, $extractDir);
            
            // 7. 报告更新成功
            $this->reportUpdate($this->currentVersion, $versionTo, 1);
            
            return [
                'success' => true,
                'message' => "已成功更新到版本 {$versionTo}",
                'backup_file' => $backupFile
            ];
            
        } catch (Exception $e) {
            // 报告更新失败
            $this->reportUpdate($this->currentVersion, $versionTo, 0, $e->getMessage());
            
            return [
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 创建当前系统备份
     * @return string 备份文件路径
     */
    private function createBackup() {
        $backupFile = $this->backupDir . 'backup_' . date('Ymd_His') . '.zip';
        $sourceDir = dirname(__DIR__); // 项目根目录
        
        if (!class_exists('ZipArchive')) {
            throw new Exception('服务器未安装 ZipArchive 扩展，无法创建备份');
        }
        
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('无法创建备份文件');
        }
        
        $this->addDirToZip($zip, $sourceDir, basename($sourceDir));
        $zip->close();
        
        // 只保留最近5个备份
        $this->cleanupOldBackups(5);
        
        return $backupFile;
    }
    
    /**
     * 下载更新包
     */
    private function downloadUpdate($downloadUrl, $expectedHash) {
        $localFile = $this->tempDir . 'update_' . time() . '.zip';
        
        // 使用 cURL 下载
        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($data)) {
            return false;
        }
        
        file_put_contents($localFile, $data);
        
        // 验证哈希
        if (!empty($expectedHash)) {
            $actualHash = md5_file($localFile);
            if ($actualHash !== $expectedHash) {
                unlink($localFile);
                throw new Exception('文件校验失败，下载的文件可能已损坏');
            }
        }
        
        return $localFile;
    }
    
    /**
     * 解压更新包
     */
    private function extractUpdate($zipFile) {
        $extractDir = $this->tempDir . 'extract_' . time() . '/';
        
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return false;
        }
        
        $zip->extractTo($extractDir);
        $zip->close();
        
        return $extractDir;
    }
    
    /**
     * 执行数据库升级
     */
    private function applyDbChanges($extractDir) {
        $upgradeFile = $extractDir . 'upgrade.sql';
        
        if (!file_exists($upgradeFile)) {
            return;
        }
        
        $sql = file_get_contents($upgradeFile);
        if (empty($sql)) {
            return;
        }
        
        // 使用项目的数据库连接执行SQL
        try {
            $db = Database::getInstance();
            
            // 分割多个SQL语句并执行
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $db->query($statement);
                }
            }
        } catch (Exception $e) {
            throw new Exception('数据库升级失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 替换文件
     */
    private function replaceFiles($extractDir) {
        $sourceDir = realpath($extractDir);
        $targetDir = realpath($this->baseDir);
        
        if (!$sourceDir || !$targetDir) {
            throw new Exception('目录路径无效');
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = substr($sourcePath, strlen($sourceDir) + 1);
            $targetPath = $targetDir . '/' . $relativePath;
            
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                // 跳过配置文件
                if ($this->shouldSkipFile($relativePath)) {
                    continue;
                }
                
                // 复制文件
                if (!copy($sourcePath, $targetPath)) {
                    throw new Exception("无法复制文件: {$relativePath}");
                }
            }
        }
    }
    
    /**
     * 判断是否跳过某些文件
     */
    private function shouldSkipFile($relativePath) {
        $skipPatterns = [
            'config.php',
            '.env',
            'install.lock',
            '.auth_cache',
            'update/backup/',
            'update/temp/',
            '.git/',
            '.gitignore'
        ];
        
        foreach ($skipPatterns as $pattern) {
            if (strpos($relativePath, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 清理临时文件
     */
    private function cleanup($zipFile, $extractDir) {
        if (file_exists($zipFile)) unlink($zipFile);
        if (is_dir($extractDir)) $this->removeDir($extractDir);
    }
    
    /**
     * 报告更新结果
     */
    private function reportUpdate($versionFrom, $versionTo, $status, $errorMsg = '') {
        $params = [
            'action' => 'report_update',
            'domain' => $this->domain,
            'version_from' => $versionFrom,
            'version_to' => $versionTo,
            'status' => $status,
            'error_msg' => $errorMsg
        ];
        
        // 异步发送，不阻塞
        $this->httpPostAsync($this->apiUrl, $params);
    }
    
    /**
     * HTTP POST 请求（同步）
     */
    private function httpPost($url, $params) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        return $error ? false : $response;
    }
    
    /**
     * HTTP POST 请求（异步）
     */
    private function httpPostAsync($url, $params) {
        // 使用 fsockopen 实现异步请求
        $parts = parse_url($url);
        $host = $parts['host'] ?? 'localhost';
        $port = $parts['port'] ?? 80;
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        
        $data = http_build_query($params);
        $fp = @fsockopen($host, $port, $errno, $errstr, 1);
        
        if ($fp) {
            $out = "POST {$path}{$query} HTTP/1.1\r\n";
            $out .= "Host: {$host}\r\n";
            $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out .= "Content-Length: " . strlen($data) . "\r\n";
            $out .= "Connection: Close\r\n\r\n";
            $out .= $data;
            
            fwrite($fp, $out);
            fclose($fp);
        }
    }
    
    /**
     * 获取当前构建号
     */
    private function getCurrentBuild() {
        $buildFile = dirname(__DIR__) . '/build.txt';
        if (file_exists($buildFile)) {
            return intval(file_get_contents($buildFile));
        }
        return 0;
    }
    
    /**
     * 递归添加目录到ZIP
     */
    private function addDirToZip($zip, $dir, $basePath) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $basePath . '/' . substr($filePath, strlen($dir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    
    /**
     * 删除目录
     */
    private function removeDir($dir) {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        return rmdir($dir);
    }
    
    /**
     * 清理旧备份
     */
    private function cleanupOldBackups($keepCount) {
        $backups = glob($this->backupDir . 'backup_*.zip');
        if (count($backups) > $keepCount) {
            sort($backups);
            $toDelete = array_slice($backups, 0, count($backups) - $keepCount);
            foreach ($toDelete as $file) {
                @unlink($file);
            }
        }
    }
    
    /**
     * 执行SQL语句（占位符，需要根据实际数据库配置实现）
     */
    private function executeSql($sql) {
        // 这里需要根据你的数据库连接方式来实现
        // 示例：使用 PDO
        // $pdo = new PDO("mysql:host=localhost;dbname=yourdb", "user", "pass");
        // $pdo->exec($sql);
        
        // 实际使用时请根据你的数据库配置来修改
        throw new Exception('请根据实际数据库配置实现 executeSql 方法');
    }
}
