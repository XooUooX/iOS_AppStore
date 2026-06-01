<?php
/**
 * /appstore 目录入口文件
 * 转发到根目录的 appstore.php
 * 处理 /appstore/ 和 /appstore 两种路径，以及查询参数
 */

// 解析 URL 中的查询参数（兼容 Nginx 不传递参数的情况）
if (empty($_GET) && isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    $queryPos = strpos($uri, '?');
    if ($queryPos !== false) {
        $queryString = substr($uri, $queryPos + 1);
        parse_str($queryString, $_GET);
        // 同时设置到 $_REQUEST
        $_REQUEST = array_merge($_REQUEST, $_GET);
    }
}

// 确保以 /appstore 或 /appstore/ 访问时都能正确转发
require_once __DIR__ . '/../appstore.php';
