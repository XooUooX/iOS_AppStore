后台管理系统 - 资源文件清单

## CSS 文件

### 1. admin.css
位置: admin/assets/admin.css
用途: 主要样式表，包含所有页面的通用样式
内容:
- CSS 变量定义（颜色、间距、阴影等）
- 按钮样式（.btn-primary, .btn-secondary 等）
- 表单样式
- 表格样式
- 统计卡片样式
- 模态框样式
- 其他通用组件样式

### 2. header.css
位置: admin/assets/css/header.css
用途: 后台页面头部和侧边栏样式
内容:
- CSS 变量定义（颜色、宽度等）
- 全局样式（body, *, 等）
- 侧边栏样式 (.admin-sidebar, .sidebar-header, .sidebar-nav 等)
- 导航链接样式 (.nav-link, .nav-section 等)
- 主内容区域样式 (.admin-main, .admin-header, .admin-content 等)
- 用户卡片样式 (.user-card, .user-avatar, .user-name 等)
- 登出按钮样式 (.logout-btn)
- 响应式设计 (@media 查询)

## JavaScript 文件

### 1. index.js
位置: admin/assets/js/index.js
用途: 首页交互功能
函数列表:
- closeUpdateModal() - 关闭更新弹窗
- checkAndShowUpdateModal() - 检查并显示更新弹窗
- doUpdate() - 执行在线更新
- openStatSettingsModal() - 打开统计卡片设置模态框
- closeStatSettingsModal() - 关闭统计卡片设置模态框
- saveStatSettings() - 保存统计卡片设置
- window.onclick() - 模态框外部点击关闭

## 文件引用关系

### header.php
- 引用: assets/admin.css
- 引用: assets/css/header.css

### index.php
- 引用: assets/js/index.js

### 其他页面
- 引用: assets/admin.css

## 资源加载顺序

1. HTML 文档加载
2. CSS 文件加载 (header.css, admin.css)
3. HTML 内容渲染
4. JavaScript 文件加载 (index.js)
5. DOM 交互事件绑定

## 优化建议

1. 可以考虑合并 header.css 和 admin.css 为一个文件以减少 HTTP 请求
2. 可以为 CSS 和 JS 文件添加版本号以便缓存管理
3. 生产环境可以考虑压缩 CSS 和 JS 文件
4. 可以使用 CDN 加速资源加载

## 更新日期

2025-05-31

## 维护说明

- 所有 CSS 样式已从 HTML 文件中分离到独立的 CSS 文件
- 所有 JavaScript 代码已从 HTML 文件中分离到独立的 JS 文件
- 保持代码的模块化和可维护性
- 便于后续的样式和功能扩展
