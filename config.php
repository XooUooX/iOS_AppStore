<?php
/** 配置文件 - 生成于 2026-06-01 19:07:04 */

if (!defined('IN_SYSTEM')) { define('IN_SYSTEM', true); }

define('DB_HOST', 'localhost');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_NAME', '');
define('DB_CHARSET', 'utf8mb4');
define('DB_PREFIX', 'ios_');

define('SYSTEM_URL', 'http://' . $_SERVER['HTTP_HOST']);

define('CARD_KEY_LENGTH', 16);
define('DEFAULT_EXPIRE_DAYS', 30);

define('API_SECRET', '');
define('API_RATE_LIMIT', 100);

date_default_timezone_set('Asia/Shanghai');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
