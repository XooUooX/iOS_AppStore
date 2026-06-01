/**
 * 应用中心页面脚本 - app.js
 */

// 切换分类标签
function switchTab(typeId) {
    document.querySelectorAll('.el-tabs__item').forEach(item => {
        item.classList.remove('is-active');
    });
    document.querySelector('.el-tabs__item[data-type="' + typeId + '"]').classList.add('is-active');
    
    document.querySelectorAll('.app-panel').forEach(panel => {
        panel.classList.add('hidden');
    });
    document.getElementById('panel-' + typeId).classList.remove('hidden');
    
    // 滚动到应用列表
    setTimeout(() => {
        document.getElementById('appContent').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
}

// 应用详情弹窗
let currentApp = null;

function showAppModal(app) {
    currentApp = app;
    document.getElementById('sheet-app-icon').src = app.image;
    document.getElementById('sheet-app-name').textContent = app.name;
    document.getElementById('sheet-app-version').textContent = app.nickname || '1.0.0';
    document.getElementById('sheet-app-size').textContent = app.size || '未知大小';
    document.getElementById('sheet-app-tag').innerHTML = app.isPaid ? 
        '<span class="el-tag el-tag--primary" style="margin-left: 8px;"><i class="fa fa-lock"></i> 付费</span>' : 
        '<span class="el-tag el-tag--success" style="margin-left: 8px;"><i class="fa fa-check-circle"></i> 免费</span>';
    document.getElementById('sheet-app-description').innerHTML = (app.keywords || '暂无应用描述').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    
    const modal = document.getElementById('app-sheet-modal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function hideAppModal() {
    const modal = document.getElementById('app-sheet-modal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// 签名工具选择弹窗
function showSignToolsModal() {
    hideAppModal();
    setTimeout(() => {
        const modal = document.getElementById('sign-tools-modal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }, 200);
}

function hideSignToolsModal() {
    const modal = document.getElementById('sign-tools-modal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// 返回顶部
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// 监听滚动事件，显示/隐藏返回顶部按钮
window.addEventListener('scroll', function() {
    const backToTopBtn = document.getElementById('backToTop');
    if (window.pageYOffset > 300) {
        backToTopBtn.classList.add('show');
    } else {
        backToTopBtn.classList.remove('show');
    }
});

// ESC键关闭弹窗
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideAppModal();
        hideSignToolsModal();
    }
});
