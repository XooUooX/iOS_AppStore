/* 后台管理系统 - 通用脚本 */

// 工具函数
const AdminUtils = {
    // 显示成功消息
    showSuccess: function(message) {
        this.showMessage(message, 'success');
    },

    // 显示错误消息
    showError: function(message) {
        this.showMessage(message, 'error');
    },

    // 显示警告消息
    showWarning: function(message) {
        this.showMessage(message, 'warning');
    },

    // 显示消息
    showMessage: function(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert alert-${type}`;
        messageDiv.textContent = message;
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 20px;
            border-radius: 4px;
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        `;

        const colors = {
            success: { bg: '#f6ffed', border: '#b7eb8f', text: '#274e2b' },
            error: { bg: '#fff1f0', border: '#ffccc7', text: '#5c0a0a' },
            warning: { bg: '#fffbe6', border: '#ffe58f', text: '#663c00' },
            info: { bg: '#e6f7ff', border: '#91d5ff', text: '#0c3386' }
        };

        const color = colors[type] || colors.info;
        messageDiv.style.backgroundColor = color.bg;
        messageDiv.style.borderLeft = `4px solid ${color.border}`;
        messageDiv.style.color = color.text;

        document.body.appendChild(messageDiv);

        setTimeout(() => {
            messageDiv.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => messageDiv.remove(), 300);
        }, 3000);
    },

    // 确认对话框
    confirm: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },

    // 格式化文件大小
    formatFileSize: function(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    },

    // 格式化日期
    formatDate: function(date, format = 'YYYY-MM-DD HH:mm:ss') {
        if (typeof date === 'string') {
            date = new Date(date);
        }
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');

        return format
            .replace('YYYY', year)
            .replace('MM', month)
            .replace('DD', day)
            .replace('HH', hours)
            .replace('mm', minutes)
            .replace('ss', seconds);
    },

    // 复制到剪贴板
    copyToClipboard: function(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                this.showSuccess('已复制到剪贴板');
            }).catch(() => {
                this.fallbackCopy(text);
            });
        } else {
            this.fallbackCopy(text);
        }
    },

    // 备用复制方法
    fallbackCopy: function(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        this.showSuccess('已复制到剪贴板');
    },

    // 验证邮箱
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    // 验证URL
    validateUrl: function(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    },

    // 验证手机号
    validatePhone: function(phone) {
        const re = /^1[3-9]\d{9}$/;
        return re.test(phone);
    },

    // 获取URL参数
    getUrlParam: function(name) {
        const url = new URL(window.location);
        return url.searchParams.get(name);
    },

    // 设置URL参数
    setUrlParam: function(name, value) {
        const url = new URL(window.location);
        url.searchParams.set(name, value);
        window.history.pushState({}, '', url);
    },

    // 删除URL参数
    removeUrlParam: function(name) {
        const url = new URL(window.location);
        url.searchParams.delete(name);
        window.history.pushState({}, '', url);
    }
};

// 模态框管理
const ModalManager = {
    // 打开模态框
    open: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
        }
    },

    // 关闭模态框
    close: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    },

    // 切换模态框
    toggle: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = modal.style.display === 'none' ? 'flex' : 'none';
        }
    },

    // 关闭所有模态框
    closeAll: function() {
        const modals = document.querySelectorAll('.modal-overlay');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
    }
};

// 表单验证
const FormValidator = {
    // 验证表单
    validate: function(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;

        const inputs = form.querySelectorAll('[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                this.markError(input, '此字段为必填项');
                isValid = false;
            } else {
                this.clearError(input);
            }
        });

        return isValid;
    },

    // 标记错误
    markError: function(input, message) {
        const group = input.closest('.form-group');
        if (group) {
            group.classList.add('has-error');
            let errorMsg = group.querySelector('.form-error');
            if (!errorMsg) {
                errorMsg = document.createElement('div');
                errorMsg.className = 'form-error';
                group.appendChild(errorMsg);
            }
            errorMsg.textContent = message;
        }
    },

    // 清除错误
    clearError: function(input) {
        const group = input.closest('.form-group');
        if (group) {
            group.classList.remove('has-error');
            const errorMsg = group.querySelector('.form-error');
            if (errorMsg) {
                errorMsg.remove();
            }
        }
    }
};

// 表格操作
const TableManager = {
    // 全选/取消全选
    toggleSelectAll: function(checkboxId) {
        const checkbox = document.getElementById(checkboxId);
        const checkboxes = document.querySelectorAll('input[name="ids[]"]');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
    },

    // 获取选中的行
    getSelectedRows: function() {
        const checkboxes = document.querySelectorAll('input[name="ids[]"]:checked');
        const ids = [];
        checkboxes.forEach(cb => {
            ids.push(cb.value);
        });
        return ids;
    },

    // 批量删除
    batchDelete: function(action) {
        const ids = this.getSelectedRows();
        if (ids.length === 0) {
            AdminUtils.showWarning('请先选择要删除的项目');
            return;
        }

        AdminUtils.confirm('确定要删除选中的项目吗？', () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.pathname;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = action;
            form.appendChild(actionInput);

            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        });
    }
};

// 页面加载完成后的初始化
document.addEventListener('DOMContentLoaded', function() {
    // 添加动画样式
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .modal-overlay {
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);

    // 模态框外部点击关闭
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    });

    // 表单自动验证
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!FormValidator.validate(this.id)) {
                e.preventDefault();
                AdminUtils.showError('请填写所有必填项');
            }
        });
    });
});

// 导出为全局对象
window.Admin = {
    Utils: AdminUtils,
    Modal: ModalManager,
    Form: FormValidator,
    Table: TableManager
};
