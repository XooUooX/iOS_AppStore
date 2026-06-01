/* 后台管理系统 - 黑名单管理页面脚本 */

// 黑名单管理功能
const BlacklistManager = {
    // 添加黑名单
    addBlacklist: function() {
        const form = document.getElementById('addBlacklistForm');
        if (!form) return;

        const type = form.querySelector('[name="type"]').value;
        const value = form.querySelector('[name="value"]').value.trim();
        const reason = form.querySelector('[name="reason"]').value.trim();

        if (!value) {
            Admin.Utils.showError('请输入拉黑值');
            return;
        }

        if (confirm('确定要添加此黑名单吗？')) {
            form.submit();
        }
    },

    // 删除黑名单
    removeBlacklist: function(id) {
        if (confirm('确定要移除此黑名单吗？')) {
            window.location.href = `black_list.php?remove=${id}`;
        }
    },

    // 编辑黑名单
    editBlacklist: function(id) {
        Admin.Modal.open('editBlacklistModal');
        // 这里可以通过 AJAX 加载黑名单详情
    },

    // 关闭编辑弹窗
    closeEditModal: function() {
        Admin.Modal.close('editBlacklistModal');
    },

    // 复制黑名单值
    copyValue: function(value) {
        Admin.Utils.copyToClipboard(value);
    },

    // 获取选中的黑名单 ID
    getSelectedIds: function() {
        const checkboxes = document.querySelectorAll('input[name="blacklist_ids[]"]:checked');
        const ids = [];
        checkboxes.forEach(cb => {
            ids.push(cb.value);
        });
        return ids;
    },

    // 全选/取消全选
    toggleSelectAll: function(checkboxId) {
        const checkbox = document.getElementById(checkboxId);
        const checkboxes = document.querySelectorAll('input[name="blacklist_ids[]"]');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
    },

    // 批量删除
    batchDelete: function() {
        const ids = this.getSelectedIds();
        if (ids.length === 0) {
            Admin.Utils.showWarning('请先选择要删除的黑名单');
            return;
        }

        if (confirm(`确定要删除选中的 ${ids.length} 条黑名单吗？`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'black_list.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'batch_delete';
            form.appendChild(actionInput);

            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'blacklist_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        }
    },

    // 搜索黑名单
    searchBlacklist: function() {
        const form = document.querySelector('.blacklist-filter').closest('form');
        if (form) {
            form.submit();
        }
    },

    // 导出黑名单
    exportBlacklist: function() {
        const form = document.createElement('form');
        form.method = 'GET';
        form.action = 'black_list.php';

        const exportInput = document.createElement('input');
        exportInput.type = 'hidden';
        exportInput.name = 'export';
        exportInput.value = '1';
        form.appendChild(exportInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    },

    // 清理过期黑名单
    cleanExpired: function() {
        if (confirm('确定要清理所有过期的黑名单吗？')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'black_list.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'clean_expired';
            form.appendChild(actionInput);

            document.body.appendChild(form);
            form.submit();
        }
    }
};

// 页面加载完成后的初始化
document.addEventListener('DOMContentLoaded', function() {
    // 绑定全选复选框
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            BlacklistManager.toggleSelectAll('selectAll');
        });
    }

    // 绑定添加黑名单按钮
    const addBtn = document.querySelector('[data-add-blacklist]');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            Admin.Modal.open('addBlacklistModal');
        });
    }

    // 绑定复制按钮
    const copyButtons = document.querySelectorAll('[data-copy]');
    copyButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const value = this.getAttribute('data-copy');
            BlacklistManager.copyValue(value);
        });
    });

    // 绑定批量删除按钮
    const batchDeleteBtn = document.querySelector('[data-batch-delete]');
    if (batchDeleteBtn) {
        batchDeleteBtn.addEventListener('click', function() {
            BlacklistManager.batchDelete();
        });
    }

    // 绑定导出按钮
    const exportBtn = document.querySelector('[data-export-blacklist]');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            BlacklistManager.exportBlacklist();
        });
    }

    // 绑定清理过期按钮
    const cleanBtn = document.querySelector('[data-clean-expired]');
    if (cleanBtn) {
        cleanBtn.addEventListener('click', function() {
            BlacklistManager.cleanExpired();
        });
    }
});

// 导出到全局对象
window.Blacklist = BlacklistManager;
