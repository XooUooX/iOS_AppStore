// Ning.Si软件源管理系统 - kami.php 页面脚本

// 更新批次信息显示
function updateBatchInfo() {
    var select = document.getElementById('batchSelect');
    var option = select.options[select.selectedIndex];
    var batchInfo = document.getElementById('batchInfo');
    var passwordField = document.getElementById('passwordField');
    
    if (!option.value) {
        batchInfo.classList.add('hidden');
        passwordField.classList.add('hidden');
        var passwordInput = passwordField.querySelector('input');
        if (passwordInput) {
            passwordInput.required = false;
        }
        return;
    }
    
    var total = parseInt(option.dataset.total);
    var used = parseInt(option.dataset.used);
    var remaining = parseInt(option.dataset.remaining);
    var type = option.dataset.type;
    var days = option.dataset.days;
    var remark = option.dataset.remark;
    var password = option.dataset.password;
    
    var percent = total > 0 ? (used / total * 100) : 0;
    
    document.getElementById('batchName').textContent = option.text.split(' (')[0];
    document.getElementById('batchType').textContent = type;
    document.getElementById('batchDetails').textContent = '有效期' + days + '天' + (remark ? ' | ' + remark : '');
    document.getElementById('progressFill').style.width = percent + '%';
    document.getElementById('progressText').textContent = used + '/' + total;
    document.getElementById('remainingText').textContent = '剩余 ' + remaining + ' 张';
    
    if (password) {
        passwordField.classList.remove('hidden');
        var passwordInput = passwordField.querySelector('input');
        if (passwordInput) {
            passwordInput.required = true;
        }
    } else {
        passwordField.classList.add('hidden');
        var passwordInput = passwordField.querySelector('input');
        if (passwordInput) {
            passwordInput.required = false;
        }
    }
    
    batchInfo.classList.remove('hidden');
}

// 复制卡密到剪贴板
function copyCardKey() {
    var keyElement = document.getElementById('claimedCardKey');
    if (!keyElement) return;
    
    var key = keyElement.textContent.trim();
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(key).then(function() {
            alert('卡密已复制到剪贴板');
        }).catch(function() {
            fallbackCopy(key);
        });
    } else {
        fallbackCopy(key);
    }
}

// 降级复制方案
function fallbackCopy(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        alert('卡密已复制到剪贴板');
    } catch (err) {
        alert('复制失败，请手动复制: ' + text);
    }
    
    document.body.removeChild(textarea);
}
