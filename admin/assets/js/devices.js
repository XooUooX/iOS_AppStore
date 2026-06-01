/* 后台管理系统 - 设备管理页面脚本 */

// 设备管理功能
const DeviceManager = {
    // 显示设备详情
    showDetail: function(deviceId) {
        Admin.Modal.open('deviceDetailModal');
        // 这里可以通过 AJAX 加载设备详情
    },

    // 关闭设备详情
    closeDetail: function() {
        Admin.Modal.close('deviceDetailModal');
    },

    // 复制设备 ID
    copyDeviceId: function(deviceId) {
        Admin.Utils.copyToClipboard(deviceId);
    },

    // 禁用设备
    disableDevice: function(deviceId) {
        if (confirm('确定要禁用此设备吗？')) {
            window.location.href = `devices.php?disable=${encodeURIComponent(deviceId)}`;
        }
    },

    // 启用设备
    enableDevice: function(deviceId) {
        if (confirm('确定要启用此设备吗？')) {
            window.location.href = `devices.php?enable=${encodeURIComponent(deviceId)}`;
        }
    },

    // 删除设备
    deleteDevice: function(deviceId) {
        if (confirm('确定要删除此设备吗？此操作不可撤销。')) {
            window.location.href = `devices.php?delete=${encodeURIComponent(deviceId)}`;
        }
    },

    // 拉黑设备
    blacklistDevice: function(deviceId) {
        const duration = document.querySelector('input[name="duration"]:checked')?.value || '0';
        const reason = document.querySelector('textarea[name="reason"]')?.value || '从设备管理页面拉黑';

        if (confirm('确定要拉黑此设备吗？')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'devices.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'blacklist';
            form.appendChild(actionInput);

            const deviceIdInput = document.createElement('input');
            deviceIdInput.type = 'hidden';
            deviceIdInput.name = 'device_id';
            deviceIdInput.value = deviceId;
            form.appendChild(deviceIdInput);

            const durationInput = document.createElement('input');
            durationInput.type = 'hidden';
            durationInput.name = 'duration';
            durationInput.value = duration;
            form.appendChild(durationInput);

            const reasonInput = document.createElement('input');
            reasonInput.type = 'hidden';
            reasonInput.name = 'reason';
            reasonInput.value = reason;
            form.appendChild(reasonInput);

            document.body.appendChild(form);
            form.submit();
        }
    },

    // 获取选中的设备 ID
    getSelectedIds: function() {
        const checkboxes = document.querySelectorAll('input[name="device_ids[]"]:checked');
        const ids = [];
        checkboxes.forEach(cb => {
            ids.push(cb.value);
        });
        return ids;
    },

    // 全选/取消全选
    toggleSelectAll: function(checkboxId) {
        const checkbox = document.getElementById(checkboxId);
        const checkboxes = document.querySelectorAll('input[name="device_ids[]"]');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
    },

    // 批量拉黑
    batchBlacklist: function() {
        const ids = this.getSelectedIds();
        if (ids.length === 0) {
            Admin.Utils.showWarning('请先选择要拉黑的设备');
            return;
        }

        const duration = document.querySelector('input[name="duration"]:checked')?.value || '0';
        const reason = document.querySelector('textarea[name="reason"]')?.value || '批量拉黑';

        if (confirm(`确定要拉黑选中的 ${ids.length} 个设备吗？`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'devices.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'batch_blacklist';
            form.appendChild(actionInput);

            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'device_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            const durationInput = document.createElement('input');
            durationInput.type = 'hidden';
            durationInput.name = 'duration';
            durationInput.value = duration;
            form.appendChild(durationInput);

            const reasonInput = document.createElement('input');
            reasonInput.type = 'hidden';
            reasonInput.name = 'reason';
            reasonInput.value = reason;
            form.appendChild(reasonInput);

            document.body.appendChild(form);
            form.submit();
        }
    },

    // 搜索设备
    searchDevices: function() {
        const form = document.querySelector('.device-filter').closest('form');
        if (form) {
            form.submit();
        }
    },

    // 导出设备列表
    exportDevices: function() {
        const form = document.createElement('form');
        form.method = 'GET';
        form.action = 'devices.php';

        const exportInput = document.createElement('input');
        exportInput.type = 'hidden';
        exportInput.name = 'export';
        exportInput.value = '1';
        form.appendChild(exportInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
};

// 页面加载完成后的初始化
document.addEventListener('DOMContentLoaded', function() {
    // 绑定全选复选框
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            DeviceManager.toggleSelectAll('selectAll');
        });
    }

    // 绑定设备详情链接
    const detailLinks = document.querySelectorAll('[data-device-detail]');
    detailLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const deviceId = this.getAttribute('data-device-detail');
            DeviceManager.showDetail(deviceId);
        });
    });

    // 绑定复制按钮
    const copyButtons = document.querySelectorAll('[data-copy]');
    copyButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const text = this.getAttribute('data-copy');
            DeviceManager.copyDeviceId(text);
        });
    });

    // 绑定批量拉黑按钮
    const batchBlacklistBtn = document.querySelector('[data-batch-blacklist]');
    if (batchBlacklistBtn) {
        batchBlacklistBtn.addEventListener('click', function() {
            DeviceManager.batchBlacklist();
        });
    }

    // 绑定导出按钮
    const exportBtn = document.querySelector('[data-export-devices]');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            DeviceManager.exportDevices();
        });
    }
});

// 导出到全局对象
window.Device = DeviceManager;
