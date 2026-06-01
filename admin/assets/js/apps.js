/* 后台管理系统 - 应用管理页面脚本 */

function openEditModal(appData) {
    document.getElementById('editAppModal').style.display = 'flex';
    document.getElementById('editId').value = appData.id;
    document.querySelector('#edit-form [name="row[type]"]').value = appData.type || 'default';
    document.querySelector('#edit-form [name="row[name]"]').value = appData.name || '';
    document.querySelector('#edit-form [name="row[nickname]"]').value = appData.nickname || '';
    document.querySelector('#edit-form [name="row[weigh]"]').value = appData.weigh || 0;
    document.querySelector('#edit-form [name="row[image]"]').value = appData.image || '';
    document.querySelector('#edit-form [name="row[bt1b]"]').value = appData.bt1b || '';
    document.querySelector('#edit-form [name="row[bt2a]"]').value = appData.bt2a > 0 ? (appData.bt2a / 1024 / 1024).toFixed(2) : '';
    document.querySelector('#edit-form [name="row[keywords]"]').value = appData.keywords || '';
    document.querySelector('#edit-form [name="row[bt1a]"]').value = appData.bt1a || '';
    document.querySelector('#edit-form [name="row[flag]"]').value = appData.flag || 0;
    document.querySelector('#edit-form [name="row[bt2b]"]').value = appData.bt2b || 0;
    document.querySelector('#edit-form [name="row[status]"]').value = appData.status || 'normal';
    document.querySelector('#edit-form [name="row[beizhu]"]').value = appData.beizhu || '';
    
    // 显示图标预览
    var editPreview = document.querySelector('#edit-form .icon-preview');
    if (editPreview && appData.image) {
        editPreview.innerHTML = '<img src="' + appData.image + '" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display=\'none\'">';
        editPreview.style.border = '2px solid #667eea';
    } else if (editPreview) {
        editPreview.innerHTML = '<span style="font-size: 32px;">📷</span>';
        editPreview.style.border = '2px dashed #c0c4cc';
    }
    
    // 清空文件输入
    var editFileInput = document.querySelector('#edit-form .icon-file-input');
    if (editFileInput) {
        editFileInput.value = '';
    }
}

function closeEditModal() {
    document.getElementById('editAppModal').style.display = 'none';
}

// 点击弹窗外部关闭
window.onclick = function(event) {
    var addModal = document.getElementById('addAppModal');
    var editModal = document.getElementById('editAppModal');
    var protectModal = document.getElementById('protectModal');
    
    if (event.target == addModal) {
        addModal.style.display = 'none';
    }
    if (event.target == editModal) {
        editModal.style.display = 'none';
    }
    if (event.target == protectModal) {
        protectModal.style.display = 'none';
    }
}

// 关闭搬运防护弹窗
function closeProtectModal() {
    document.getElementById('protectModal').style.display = 'none';
}

// 文件预览功能
document.addEventListener('DOMContentLoaded', function() {
    // 优化图标上传区域布局 - 将输入框和按钮水平排列
    function optimizeIconUploadLayout() {
        var iconInputs = document.querySelectorAll('.icon-inputs');
        iconInputs.forEach(function(iconInput) {
            var urlInput = iconInput.querySelector('.image-url-input');
            var fileBtn = iconInput.querySelector('.file-select-btn');
            var tip = iconInput.querySelector('.icon-tip');
            
            if (urlInput && fileBtn && !iconInput.querySelector('.icon-input-row')) {
                // 创建水平排列的容器
                var row = document.createElement('div');
                row.className = 'icon-input-row';
                
                // 将输入框和按钮移到容器中
                row.appendChild(urlInput);
                row.appendChild(fileBtn);
                
                // 插入到icon-inputs的开头
                iconInput.insertBefore(row, iconInput.firstChild);
            }
        });
    }
    
    optimizeIconUploadLayout();
    
    // 图标预览功能
    function setupIconPreview() {
        var previews = document.querySelectorAll('.icon-preview');
        previews.forEach(function(preview) {
            preview.addEventListener('click', function() {
                var fileInput = this.parentElement.querySelector('.icon-file-input');
                if (fileInput) {
                    fileInput.click();
                }
            });
        });
        
        // 文件输入变化时更新预览
        var fileInputs = document.querySelectorAll('.icon-file-input');
        fileInputs.forEach(function(input) {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var preview = input.parentElement.querySelector('.icon-preview');
                        if (preview) {
                            preview.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
                            preview.style.border = '2px solid #667eea';
                        }
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    }
    
    setupIconPreview();
});

// 搬运防护设置相关函数
function openProtectModal() {
    document.getElementById('protectModal').style.display = 'flex';
}

function saveProtectSettings() {
    var form = document.getElementById('protectForm');
    if (form) {
        form.submit();
    }
}

// 应用搜索和筛选
function filterApps() {
    var form = document.querySelector('.search-box form');
    if (form) {
        form.submit();
    }
}

// 批量操作
function batchAction(action) {
    var checkboxes = document.querySelectorAll('input[name="app_ids[]"]:checked');
    if (checkboxes.length === 0) {
        alert('请先选择要操作的应用');
        return;
    }
    
    var ids = [];
    checkboxes.forEach(function(checkbox) {
        ids.push(checkbox.value);
    });
    
    if (confirm('确定要执行此操作吗？')) {
        // 提交批量操作
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'apps.php';
        
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.appendChild(actionInput);
        
        ids.forEach(function(id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'app_ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}

// 删除应用确认
function confirmDelete(appId, appName) {
    if (confirm('确定要删除应用 "' + appName + '" 吗？此操作不可撤销。')) {
        window.location.href = 'apps.php?delete=' + appId;
    }
}

// 启用/禁用应用
function toggleAppStatus(appId, currentStatus) {
    var action = currentStatus === 'normal' ? 'disable' : 'enable';
    window.location.href = 'apps.php?' + action + '=' + appId;
}
