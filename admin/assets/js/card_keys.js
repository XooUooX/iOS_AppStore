/* 后台管理系统 - 卡密管理页面脚本 */

// 卡密管理功能
const CardKeyManager = {
    // 生成卡密
    generateKeys: function() {
        const form = document.getElementById('generateForm');
        if (!form) return;

        const count = parseInt(form.querySelector('[name="count"]').value) || 10;
        const type = parseInt(form.querySelector('[name="type"]').value) || 1;
        const expireDays = parseInt(form.querySelector('[name="expire_days"]').value) || 30;

        if (count > 1000) {
            Admin.Utils.showError('生成数量不能超过 1000');
            return;
        }

        if (confirm(`确定要生成 ${count} 张卡密吗？`)) {
            form.submit();
        }
    },

    // 删除卡密
    deleteKey: function(key) {
        if (confirm(`确定要删除卡密 ${key} 吗？`)) {
            window.location.href = `card_keys.php?delete=${encodeURIComponent(key)}`;
        }
    },

    // 禁用卡密
    disableKey: function(key) {
        if (confirm(`确定要禁用卡密 ${key} 吗？`)) {
            window.location.href = `card_keys.php?disable=${encodeURIComponent(key)}`;
        }
    },

    // 复制卡密
    copyKey: function(key) {
        Admin.Utils.copyToClipboard(key);
    },

    // 导出卡密
    exportKeys: function(type) {
        const selectedIds = this.getSelectedIds();
        
        if (type === 'selected' && selectedIds.length === 0) {
            Admin.Utils.showWarning('请先选择要导出的卡密');
            return;
        }

        const form = document.createElement('form');
        form.method = 'GET';
        form.action = 'card_keys.php';

        const exportTypeInput = document.createElement('input');
        exportTypeInput.type = 'hidden';
        exportTypeInput.name = 'export';
        exportTypeInput.value = type;
        form.appendChild(exportTypeInput);

        if (type === 'selected' && selectedIds.length > 0) {
            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'export_ids';
            idsInput.value = selectedIds.join(',');
            form.appendChild(idsInput);
        }

        const formatInput = document.createElement('input');
        formatInput.type = 'hidden';
        formatInput.name = 'format';
        formatInput.value = 'csv';
        form.appendChild(formatInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    },

    // 获取选中的卡密 ID
    getSelectedIds: function() {
        const checkboxes = document.querySelectorAll('input[name="card_ids[]"]:checked');
        const ids = [];
        checkboxes.forEach(cb => {
            ids.push(cb.value);
        });
        return ids;
    },

    // 全选/取消全选
    toggleSelectAll: function(checkboxId) {
        const checkbox = document.getElementById(checkboxId);
        const checkboxes = document.querySelectorAll('input[name="card_ids[]"]');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
    },

    // 批量删除
    batchDelete: function() {
        const ids = this.getSelectedIds();
        if (ids.length === 0) {
            Admin.Utils.showWarning('请先选择要删除的卡密');
            return;
        }

        if (confirm(`确定要删除选中的 ${ids.length} 张卡密吗？`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'card_keys.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'batch_delete';
            form.appendChild(actionInput);

            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'card_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        }
    },

    // 搜索卡密
    searchKeys: function() {
        const form = document.querySelector('.search-box form');
        if (form) {
            form.submit();
        }
    },

    // 复制所有生成的卡密
    copyAllGeneratedKeys: function() {
        const keysList = document.querySelector('.generated-keys-list');
        if (!keysList) return;

        const keys = [];
        keysList.querySelectorAll('.key').forEach(el => {
            keys.push(el.textContent.trim());
        });

        if (keys.length > 0) {
            Admin.Utils.copyToClipboard(keys.join('\n'));
        }
    },

    // 下载生成的卡密
    downloadGeneratedKeys: function() {
        const keysList = document.querySelector('.generated-keys-list');
        if (!keysList) return;

        const keys = [];
        keysList.querySelectorAll('.key').forEach(el => {
            keys.push(el.textContent.trim());
        });

        if (keys.length > 0) {
            const content = keys.join('\n');
            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `card_keys_${new Date().getTime()}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    }
};

// 页面加载完成后的初始化
document.addEventListener('DOMContentLoaded', function() {
    // 绑定全选复选框
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            CardKeyManager.toggleSelectAll('selectAll');
        });
    }

    // 绑定搜索表单
    const searchForm = document.querySelector('.search-box form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            // 表单默认提交
        });
    }

    // 绑定导出按钮
    const exportButtons = document.querySelectorAll('[data-export]');
    exportButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.getAttribute('data-export');
            CardKeyManager.exportKeys(type);
        });
    });

    // 绑定批量删除按钮
    const batchDeleteBtn = document.querySelector('[data-batch-delete]');
    if (batchDeleteBtn) {
        batchDeleteBtn.addEventListener('click', function() {
            CardKeyManager.batchDelete();
        });
    }
});

// 导出到全局对象
window.CardKey = CardKeyManager;
