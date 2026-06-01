<?php
/**
 * 后台管理 - 退出登录
 */
require_once __DIR__ . '/../config.php';

session_start();
session_destroy();

header('Location: login.php');
exit;
