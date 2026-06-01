# Ning.Si 软件源管理系统

## 📋 项目概述

Ning.Si 软件源管理系统是一个完整的 iOS 应用软件源管理平台，支持卡密授权、设备管理、应用管理、黑名单管理等功能。系统采用 PHP + MySQL 架构，提供强大的后台管理界面和完整的 API 接口。

### 🎯 核心功能

- **卡密管理** - 批量生成、使用、禁用、导出卡密
- **设备管理** - 设备激活、授权、禁用、拉黑、统计
- **应用管理** - 应用上传、编辑、删除、搬运防护
- **黑名单管理** - 设备黑名单、IP 黑名单、过期管理
- **API 接口** - 应用列表、卡密解锁、日志记录
- **后台管理** - 用户认证、权限控制、操作日志、数据统计

---

## 🚀 快速开始

### 系统要求

- PHP 7.4+
- MySQL 5.7+
- Web 服务器（Apache/Nginx）
- cURL 扩展
- PDO MySQL 驱动

### 安装步骤

1. **上传文件**
   ```bash
   将项目文件上传到 Web 服务器根目录
   ```

2. **访问安装向导**
   ```
   http://your-domain.com/install.php
   ```

3. **按照向导完成安装**
   - 检查环境要求
   - 配置数据库连接
   - 创建数据表
   - 设置管理员账户

4. **登录后台管理**
   ```
   http://your-domain.com/admin/login.php
   ```

### 默认账户

- **用户名：** admin
- **密码：** admin123（安装后请立即修改）

---

## 📁 项目结构

```
IOS/
├── admin/                      # 后台管理系统
│   ├── login.php              # 登录页面
│   ├── index.php              # 仪表盘
│   ├── card_keys.php          # 卡密管理
│   ├── card_claim.php         # 卡密领取
│   ├── devices.php            # 设备管理
│   ├── apps.php               # 应用管理
│   ├── black_list.php         # 黑名单管理
│   ├── api_request.php        # API 请求日志
│   ├── sources.php            # 软件源配置
│   ├── site_settings.php      # 网站设置
│   ├── site_security.php      # 安全设置
│   ├── monitor.php            # 监控记录
│   ├── copy_source.php        # 软件源复制
│   ├── common.php             # 公共函数
│   ├── header.php             # 菜单头部
│   ├── assets/                # 静态资源
│   │   ├── css/               # 样式文件
│   │   ├── js/                # 脚本文件
│   │   └── images/            # 图片文件
│   └── ...
├── includes/                   # 核心类库
│   ├── class.database.php     # 数据库操作类
│   ├── class.cardkey.php      # 卡密类
│   ├── class.device.php       # 设备类
│   ├── class.app.php          # 应用类
│   ├── class.cardkeyclaim.php # 卡密领取类
│   ├── class.docs.php         # 文档类
│   ├── class.updater.php      # 更新类
│   └── ...
├── appstore.php               # 主 API 接口
├── api.php                    # 通用 API 接口
├── config.php                 # 配置文件
├── install.php                # 安装向导
├── install.sql                # 数据库初始化脚本
├── install.lock               # 安装锁定文件
└── README.md                  # 本文件
```

---

## 🎨 后台资源文件

### CSS 文件
- `admin/assets/admin.css` - 主样式表
- `admin/assets/css/header.css` - 菜单和头部样式
- `admin/assets/css/forms.css` - 表单组件样式
- `admin/assets/css/apps.css` - 应用管理样式
- `admin/assets/css/card_keys.css` - 卡密管理样式
- `admin/assets/css/devices.css` - 设备管理样式
- `admin/assets/css/blacklist.css` - 黑名单管理样式

### JavaScript 文件
- `admin/assets/js/admin.js` - 通用脚本
- `admin/assets/js/apps.js` - 应用管理脚本
- `admin/assets/js/card_keys.js` - 卡密管理脚本
- `admin/assets/js/devices.js` - 设备管理脚本
- `admin/assets/js/blacklist.js` - 黑名单管理脚本
- `admin/assets/js/index.js` - 首页脚本

详见 [RESOURCES_GUIDE.md](file:///E:\WWW\IOS\RESOURCES_GUIDE.md)

---

### 1. 获取应用列表

**请求方式：** GET/POST

**请求地址：** `/appstore`

**请求参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| udid | string | 是 | 设备 ID（已授权设备） |
| code | string | 否 | 卡密（用于解锁） |

**响应示例（已授权）：**

```json
{
  "name": "Ning.Si软件源",
  "message": "授权时常：2099-12-31 23:59:59\r\n设备ID：...",
  "identifier": "com.niing.si.source",
  "sourceURL": "http://example.com/appstore",
  "apps": [
    {
      "name": "应用名称",
      "type": 1,
      "version": "1.0.0",
      "versionDate": "2026-01-01T00:00:00+08:00",
      "versionDescription": "版本描述",
      "lock": 0,
      "downloadURL": "http://example.com/app.ipa",
      "iconURL": "http://example.com/icon.png",
      "size": 1048576
    }
  ]
}
```

**响应示例（未授权）：**

```json
{
  "name": "Ning.Si软件源",
  "message": "授权时常：未解锁\r\n设备ID：...",
  "apps": [
    {
      "name": "未授权访问",
      "lock": 1,
      "downloadURL": "",
      "iconURL": "/uploads/Ban.png"
    }
  ]
}
```

### 2. 卡密解锁

**请求方式：** GET/POST

**请求地址：** `/appstore`

**请求参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| udid | string | 是 | 设备 ID |
| code | string | 是 | 卡密 |

**响应示例（成功）：**

```json
{
  "code": 0,
  "msg": "ok，解锁成功"
}
```

**响应示例（失败）：**

```json
{
  "code": 0,
  "msg": "解锁码已使用"
}
```

### 3. 通用 API 接口

**请求方式：** GET/POST

**请求地址：** `/api.php`

**请求参数：**

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| action | string | 是 | 操作类型 |
| api_key | string | 否 | API 密钥 |

**支持的操作：**

- `get_devices` - 获取设备列表
- `get_card_keys` - 获取卡密列表
- `get_apps` - 获取应用列表
- `get_stats` - 获取统计数据

---

## 🔐 安全特性

### 已实现的安全措施

- ✅ **参数化查询** - 使用 PDO 预处理语句防止 SQL 注入
- ✅ **CSRF 防护** - 所有表单使用 CSRF 令牌
- ✅ **密码加密** - 使用 `password_hash()` 加密存储
- ✅ **会话管理** - 完整的登录检查和会话控制
- ✅ **输出转义** - 使用 `htmlspecialchars()` 防止 XSS
- ✅ **API 认证** - 支持 API 密钥验证
- ✅ **IP 黑名单** - 支持 IP 拉黑和禁止访问
- ✅ **设备黑名单** - 支持设备拉黑和过期管理

### 安全建议

1. **安装后立即修改默认密码**
   ```
   后台 → 网站安全 → 修改密码
   ```

2. **配置 API 密钥**
   ```
   后台 → 软件源配置 → API 密钥
   ```

3. **删除安装文件**
   ```bash
   rm install.php
   ```

4. **设置文件权限**
   ```bash
   chmod 644 config.php
   chmod 755 uploads/
   ```

5. **定期备份数据库**
   ```bash
   mysqldump -u user -p database > backup.sql
   ```

---

## 📊 数据库表结构

### 主要表

| 表名 | 说明 |
|------|------|
| ios_admins | 管理员表 |
| ios_card_keys | 卡密表 |
| ios_devices | 设备表 |
| ios_category | 应用表 |
| ios_blacklist | 黑名单表 |
| ios_api_logs | API 日志表 |
| ios_operation_logs | 操作日志表 |
| ios_config | 系统配置表 |
| ios_monitor | 监控记录表 |

### 卡密表结构

```sql
CREATE TABLE ios_card_keys (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  card_key VARCHAR(32) NOT NULL UNIQUE,
  card_type TINYINT UNSIGNED DEFAULT 1,
  expire_days INT UNSIGNED DEFAULT 30,
  status TINYINT UNSIGNED DEFAULT 0,
  bind_device_id VARCHAR(64) NULL,
  create_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_card_key (card_key),
  INDEX idx_status (status)
);
```

### 设备表结构

```sql
CREATE TABLE ios_devices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id VARCHAR(64) NOT NULL UNIQUE,
  card_key VARCHAR(32) NULL,
  expire_time DATETIME NULL,
  status TINYINT UNSIGNED DEFAULT 1,
  create_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_device_id (device_id),
  INDEX idx_expire_time (expire_time)
);
```

---

## 🛠️ 常见问题

### Q1: 如何生成卡密？

**A:** 
1. 登录后台管理
2. 进入 "卡密管理"
3. 点击 "批量生成卡密"
4. 设置数量、类型、有效期
5. 点击 "生成" 按钮

### Q2: 如何激活设备？

**A:**
设备通过卡密自动激活，流程如下：
1. 客户端发送请求：`/appstore?udid=设备ID&code=卡密`
2. 系统验证卡密有效性
3. 卡密有效则激活设备，返回应用列表
4. 设备信息保存到数据库

### Q3: 如何拉黑设备或 IP？

**A:**
1. **拉黑设备：** 设备管理 → 选择设备 → 拉黑
2. **拉黑 IP：** API 请求日志 → 选择 IP → 拉黑
3. **管理黑名单：** 黑名单管理 → 查看/编辑/删除

### Q4: 如何启用搬运防护？

**A:**
1. 进入 "应用管理"
2. 点击 "🛡️ 搬运防护" 按钮
3. 启用搬运防护开关
4. 配置占位应用参数
5. 保存设置

### Q5: 如何导出卡密？

**A:**
1. 进入 "卡密管理"
2. 选择要导出的卡密
3. 点击 "导出" 按钮
4. 选择导出格式（CSV/TXT）
5. 下载文件

---

## 📈 性能优化

### 数据库优化

- 已为常用查询字段添加索引
- 使用参数化查询减少 SQL 解析时间
- 建议定期执行 `ANALYZE TABLE` 优化表结构

### 缓存策略

- 系统配置在首次加载时缓存到内存
- 建议使用 Redis 缓存热点数据
- 定期清理过期的 API 日志

### 代码优化

- 使用单例模式管理数据库连接
- 避免 N+1 查询问题
- 使用预处理语句提高性能

---

## 🔄 更新日志

### v1.0.0 (2026-01-01)

- ✅ 完成核心功能开发
- ✅ 实现后台管理系统
- ✅ 完成 API 接口
- ✅ 添加安全防护
- ✅ 生成项目文档

---

## 📄 许可证

本项目采用 MIT 许可证。详见 LICENSE 文件。

---

## 🙏 致谢

感谢所有贡献者和用户的支持！

---

**最后更新：** 2026-06-01

**版本：** 1.0.0
