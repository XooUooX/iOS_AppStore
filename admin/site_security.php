<?php
/**
 * 后台管理 - 网站安全
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/common.php';

checkAdminLogin();

$db = Database::getInstance();

// 修改密码
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // 获取当前管理员信息
    $adminId = $_SESSION['admin_id'] ?? 0;
    $admin = $db->fetch("SELECT * FROM " . $db->getTable('admins') . " WHERE id = ?", [$adminId]);
    
    if (!$admin) {
        redirectAfterPost('site_security.php', '管理员信息获取失败', 'danger');
    } elseif (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        redirectAfterPost('site_security.php', '请填写所有密码字段', 'danger');
    } elseif (!password_verify($currentPassword, $admin['password'])) {
        redirectAfterPost('site_security.php', '当前密码不正确', 'danger');
    } elseif ($newPassword !== $confirmPassword) {
        redirectAfterPost('site_security.php', '新密码与确认密码不一致', 'danger');
    } elseif (strlen($newPassword) < 6) {
        redirectAfterPost('site_security.php', '新密码长度至少为6位', 'danger');
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->query("UPDATE " . $db->getTable('admins') . " SET password = ? WHERE id = ?", [$hashedPassword, $adminId]);
        logOperation('修改密码', '管理员修改登录密码');
        redirectAfterPost('site_security.php', '密码修改成功', 'success');
    }
}

renderHeader('网站安全', 'site_security');
?>

<?php echo getPrgMessage(); ?>

<div class="panel">
    <div class="panel-header">
        <h2>修改密码</h2>
    </div>
    <div class="panel-body">
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            
            <div class="form-group">
                <label>当前密码</label>
                <input type="password" name="current_password" class="form-control" placeholder="请输入当前密码" required>
            </div>
            
            <div class="form-group">
                <label>新密码</label>
                <input type="password" name="new_password" class="form-control" placeholder="请输入新密码（至少6位）" required minlength="6">
                <small style="color: var(--gray-500);">密码长度至少为6位</small>
            </div>
            
            <div class="form-group">
                <label>确认新密码</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="请再次输入新密码" required minlength="6">
            </div>
            
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary">修改密码</button>
            </div>
        </form>
    </div>
</div>

<?php renderFooter(); ?>
