-- Ning.Si软件源管理系统 - 数据库表结构
-- 创建日期: 2025
-- 字符集: utf8mb4

-- 1. 卡密表
CREATE TABLE IF NOT EXISTS ios_card_keys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    card_key VARCHAR(32) NOT NULL UNIQUE COMMENT '卡密密钥',
    card_type TINYINT UNSIGNED DEFAULT 1 COMMENT '卡密类型：1-月卡, 2-季卡, 3-年卡, 4-永久',
    status TINYINT UNSIGNED DEFAULT 0 COMMENT '状态：0-未使用, 1-已使用, 2-已禁用',
    expire_days INT UNSIGNED DEFAULT 30 COMMENT '有效期天数',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    use_time DATETIME NULL COMMENT '使用时间',
    expire_time DATETIME NULL COMMENT '到期时间',
    bind_device_id VARCHAR(64) NULL COMMENT '绑定的设备ID',
    remark VARCHAR(255) NULL COMMENT '备注',
    INDEX idx_card_key (card_key),
    INDEX idx_status (status),
    INDEX idx_bind_device (bind_device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='卡密表';

-- 2. 设备表
CREATE TABLE IF NOT EXISTS ios_devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL UNIQUE COMMENT '设备标识码',
    card_key VARCHAR(32) NULL COMMENT '绑定的卡密',
    bind_time DATETIME NULL COMMENT '绑定时间',
    expire_time DATETIME NULL COMMENT '到期时间',
    status TINYINT UNSIGNED DEFAULT 1 COMMENT '状态：0-禁用, 1-正常, 2-过期',
    last_active_time DATETIME NULL COMMENT '最后活跃时间',
    active_count INT UNSIGNED DEFAULT 0 COMMENT '激活次数',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    ip_address VARCHAR(45) NULL COMMENT 'IP地址',
    INDEX idx_device_id (device_id),
    INDEX idx_card_key (card_key),
    INDEX idx_status (status),
    INDEX idx_expire_time (expire_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备表';

-- 3. 系统配置表 (键值对存储，适配123项目结构)
CREATE TABLE IF NOT EXISTS ios_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL DEFAULT '' COMMENT '变量名',
    `group` VARCHAR(30) NOT NULL DEFAULT 'basic' COMMENT '分组',
    title VARCHAR(100) NOT NULL DEFAULT '' COMMENT '变量标题',
    tip VARCHAR(100) NOT NULL DEFAULT '' COMMENT '变量描述',
    type VARCHAR(30) NOT NULL DEFAULT 'string' COMMENT '类型:string,text,int,bool,array,datetime,date,file,switch',
    value TEXT NOT NULL COMMENT '变量值',
    content TEXT NOT NULL COMMENT '变量字典数据',
    rule VARCHAR(100) NOT NULL DEFAULT '' COMMENT '验证规则',
    extend VARCHAR(255) NOT NULL DEFAULT '' COMMENT '扩展属性',
    createtime INT UNSIGNED DEFAULT 0 COMMENT '创建时间',
    updatetime INT UNSIGNED DEFAULT 0 COMMENT '更新时间',
    UNIQUE KEY name (name),
    INDEX idx_group (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- 插入默认软件源配置
INSERT INTO ios_config (name, `group`, title, tip, type, value, content, rule, extend, createtime, updatetime) VALUES
('name', 'basic', '站点名称', '请填写站点名称', 'string', '', '', 'required', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('version', 'basic', '版本号', '如果静态资源有变动请重新配置该值', 'string', '', '', 'required', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('timezone', 'basic', '时区', '', 'string', 'Asia/Shanghai', '', 'required', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('forbiddenip', 'basic', '禁止IP', '一行一条记录', 'text', '', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('sourceURL', 'basic', '软件来源', '软件来源地址', 'string', '', '', 'url', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('sourceicon', 'basic', '源图标', '请输入源图标地址', 'string', '', '', 'url', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('payURL', 'basic', '解锁发卡地址', '此处填写发卡地址！', 'string', '', '', 'url', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('unlockURL', 'basic', '解锁接口地址', '如用本后台卡密验证请直接填写软件源地址', 'string', '', '', 'url', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('identifier', 'basic', '源识别标符', '源识别标符', 'string', '', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('message', 'basic', '软件源公告板', '此处填写软件源公告', 'text', '', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('opencry', 'basic', '软件源加密', '开启加密', 'switch', '0', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('openblack', 'basic', '自动拉黑添加者', '自动拉黑', 'switch', '0', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('openblack2', 'basic', '自动拉黑破解者', '自动拉黑', 'switch', '0', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('card_claim_enabled', 'basic', '启用卡密领取', '开启后用户可在前台领取卡密', 'switch', '1', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('card_claim_password_enabled', 'basic', '启用卡密领取口令', '开启后领取卡密需要输入口令', 'switch', '0', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('card_claim_password', 'basic', '卡密领取口令', '设置领取卡密时的验证口令', 'string', '', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('show_shortcut_after_activate', 'basic', '激活后显示快捷入口', '开启后卡密激活成功页面显示一键添加全能签/轻松签快捷入口', 'switch', '1', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('api_key', 'basic', 'API密钥', '用于API接口认证，留空则不验证（不推荐）', 'string', '', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE value=VALUES(value), updatetime=UNIX_TIMESTAMP();

-- 插入网站管理默认配置
INSERT INTO ios_config (name, `group`, title, tip, type, value, content, rule, extend, createtime, updatetime) VALUES
('site_title', 'system', '网站标题', '网站标题，显示在浏览器标签页', 'string', '', '', 'required', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('site_keywords', 'system', '网站关键词', '多个关键词用逗号分隔', 'string', '', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('site_description', 'system', '网站描述', '网站简介，用于搜索引擎展示', 'text', '', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('frontend_title', 'system', '前台标题', '显示在网站前台页面顶部', 'string', '', '', 'required', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('icp_number', 'system', '备案号', '如: 京ICP备12345678号', 'string', '', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
('copyright', 'system', '版权信息', '如: © 2026 公司名称', 'string', '', '', '', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE value=VALUES(value), updatetime=UNIX_TIMESTAMP();

-- 4. 管理员表
CREATE TABLE IF NOT EXISTS ios_admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
    password VARCHAR(255) NOT NULL COMMENT '密码',
    nickname VARCHAR(50) NULL COMMENT '昵称',
    email VARCHAR(100) NULL COMMENT '邮箱',
    last_login_time DATETIME NULL COMMENT '最后登录时间',
    last_login_ip VARCHAR(45) NULL COMMENT '最后登录IP',
    status TINYINT UNSIGNED DEFAULT 1 COMMENT '状态：0-禁用, 1-启用',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员表';

-- 5. 操作日志表
CREATE TABLE IF NOT EXISTS ios_operation_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL COMMENT '操作类型',
    content TEXT NULL COMMENT '操作内容',
    operator VARCHAR(50) NULL COMMENT '操作人',
    ip_address VARCHAR(45) NULL COMMENT 'IP地址',
    user_agent VARCHAR(255) NULL COMMENT '用户代理',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX idx_action (action),
    INDEX idx_create_time (create_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- 6. API请求日志表
CREATE TABLE IF NOT EXISTS ios_api_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    api_name VARCHAR(50) NOT NULL COMMENT 'API名称',
    device_id VARCHAR(64) NULL COMMENT '设备ID',
    card_key VARCHAR(32) NULL COMMENT '卡密',
    request_data TEXT NULL COMMENT '请求数据',
    response_data TEXT NULL COMMENT '响应数据',
    ip_address VARCHAR(45) NULL COMMENT 'IP地址',
    status TINYINT UNSIGNED DEFAULT 1 COMMENT '状态：0-失败, 1-成功',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX idx_api_name (api_name),
    INDEX idx_device_id (device_id),
    INDEX idx_create_time (create_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API请求日志表';

-- 7. 应用表 (category结构，适配456项目)
CREATE TABLE IF NOT EXISTS ios_category (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pid INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父ID',
    type VARCHAR(30) NOT NULL DEFAULT 'default' COMMENT '栏目类型：default, 1=应用, 2=游戏, 3=影音, 4=工具, 5=插件',
    name VARCHAR(100) NOT NULL DEFAULT '' COMMENT '应用名称',
    nickname VARCHAR(50) NOT NULL DEFAULT '' COMMENT '版本号',
    flag VARCHAR(255) NOT NULL DEFAULT '' COMMENT '是否蓝奏云：0-否, 1-是',
    image VARCHAR(255) NOT NULL DEFAULT '' COMMENT '应用图标URL',
    keywords TEXT NULL COMMENT '版本描述/关键字',
    description VARCHAR(255) NOT NULL DEFAULT '' COMMENT '描述',
    diyname VARCHAR(30) NOT NULL DEFAULT '' COMMENT '自定义名称',
    createtime INT UNSIGNED DEFAULT 0 COMMENT '创建时间',
    updatetime INT UNSIGNED DEFAULT 0 COMMENT '更新时间',
    weigh INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序权重',
    status VARCHAR(30) NOT NULL DEFAULT 'normal' COMMENT '状态：normal-启用, hidden-禁用',
    bt1a TEXT NULL COMMENT '下载地址',
    bt1b VARCHAR(255) DEFAULT '018084' COMMENT '主题颜色',
    bt2a VARCHAR(255) NULL COMMENT '文件大小(字节)',
    bt2b VARCHAR(255) NULL COMMENT '锁定状态：0-免费, 1-付费',
    beizhu VARCHAR(256) NULL COMMENT '备注',
    flag2 VARCHAR(255) NULL COMMENT '扩展标识',
    cs INT UNSIGNED DEFAULT 0 COMMENT '计数',
    cstime INT UNSIGNED NULL COMMENT '计数时间',
    INDEX idx_pid (pid),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_weigh (weigh, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='应用表';

-- 插入默认管理员 (密码: admin123)
INSERT INTO ios_admins (username, password, nickname) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '超级管理员')
ON DUPLICATE KEY UPDATE id=id;

-- 8. 监控记录表 (添加者/破解者监控)
CREATE TABLE IF NOT EXISTS ios_monitor (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    udid VARCHAR(64) NOT NULL COMMENT '设备UDID',
    identity VARCHAR(50) NOT NULL COMMENT '身份：添加者/破解者',
    count INT UNSIGNED DEFAULT 1 COMMENT '次数',
    add_time INT UNSIGNED DEFAULT 0 COMMENT '添加时间',
    INDEX idx_udid (udid),
    INDEX idx_identity (identity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='监控记录表';

-- 9. 卡密领取批次表
CREATE TABLE IF NOT EXISTS ios_card_key_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_name VARCHAR(100) NOT NULL COMMENT '批次名称',
    batch_type TINYINT UNSIGNED DEFAULT 1 COMMENT '卡密类型：1-月卡, 2-季卡, 3-年卡, 4-永久',
    expire_days INT UNSIGNED DEFAULT 30 COMMENT '有效期天数',
    total_count INT UNSIGNED DEFAULT 0 COMMENT '总卡密数量',
    used_count INT UNSIGNED DEFAULT 0 COMMENT '已领取数量',
    status TINYINT UNSIGNED DEFAULT 1 COMMENT '状态：0-禁用, 1-启用',
    password VARCHAR(64) NULL COMMENT '领取口令，为空表示不需要口令',
    remark VARCHAR(255) NULL COMMENT '备注',
    -- 领取限制配置
    id_limit_type TINYINT UNSIGNED DEFAULT 0 COMMENT 'ID限制类型：0-无限制, 1-设备ID, 2-IP地址',
    id_limit_count INT UNSIGNED DEFAULT 1 COMMENT '同一ID可领取次数',
    ip_limit_type TINYINT UNSIGNED DEFAULT 0 COMMENT 'IP限制类型：0-无限制, 1-单IP, 2-IP段',
    ip_limit_count INT UNSIGNED DEFAULT 1 COMMENT '同一IP可领取次数',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    update_time DATETIME NULL COMMENT '更新时间',
    INDEX idx_status (status),
    INDEX idx_create_time (create_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='卡密领取批次表';

-- 10. 卡密批次关联表（记录哪些卡密属于哪个批次）
CREATE TABLE IF NOT EXISTS ios_card_key_batch_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL COMMENT '批次ID',
    card_key VARCHAR(32) NOT NULL COMMENT '卡密密钥',
    status TINYINT UNSIGNED DEFAULT 0 COMMENT '状态：0-未领取, 1-已领取',
    device_id VARCHAR(64) NULL COMMENT '领取设备ID',
    device_uuid VARCHAR(64) NULL COMMENT '领取设备UUID',
    device_name VARCHAR(100) NULL COMMENT '设备名称',
    ip_address VARCHAR(45) NULL COMMENT '领取IP',
    claim_time DATETIME NULL COMMENT '领取时间',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX idx_batch_id (batch_id),
    INDEX idx_card_key (card_key),
    INDEX idx_status (status),
    INDEX idx_device_id (device_id),
    UNIQUE KEY uk_batch_card (batch_id, card_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='卡密批次明细表';

-- 11. 黑名单表（用于领取限制拉黑）
CREATE TABLE IF NOT EXISTS ios_blacklist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NULL COMMENT '批次ID，NULL表示全局黑名单',
    type TINYINT UNSIGNED NOT NULL COMMENT '类型：1-设备ID, 2-IP地址',
    value VARCHAR(64) NOT NULL COMMENT '黑名单值（设备ID或IP）',
    reason VARCHAR(255) NULL COMMENT '拉黑原因',
    expire_time DATETIME NULL COMMENT '过期时间，NULL表示永久',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX idx_batch_id (batch_id),
    INDEX idx_type_value (type, value),
    INDEX idx_expire_time (expire_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='黑名单表';

-- 12. 文档表
CREATE TABLE IF NOT EXISTS ios_docs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL COMMENT '文档标题',
    slug VARCHAR(100) NOT NULL UNIQUE COMMENT '文档URL别名（用于生成链接）',
    content LONGTEXT NOT NULL COMMENT '文档内容',
    description VARCHAR(255) NULL COMMENT '文档描述',
    category VARCHAR(50) DEFAULT 'help' COMMENT '分类：help-帮助, docs-文档, guide-指南',
    sort INT UNSIGNED DEFAULT 0 COMMENT '排序权重',
    status TINYINT UNSIGNED DEFAULT 1 COMMENT '状态：0-草稿, 1-发布',
    views INT UNSIGNED DEFAULT 0 COMMENT '浏览次数',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    update_time DATETIME NULL COMMENT '更新时间',
    INDEX idx_slug (slug),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_sort (sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文档表';

-- 13. 管理员设置表（用于保存管理员个人设置）
CREATE TABLE IF NOT EXISTS ios_admin_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL COMMENT '管理员ID',
    setting_key VARCHAR(100) NOT NULL COMMENT '设置键名',
    setting_value LONGTEXT NULL COMMENT '设置值（JSON格式）',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    update_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    UNIQUE KEY uk_admin_key (admin_id, setting_key),
    INDEX idx_admin_id (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员设置表';
