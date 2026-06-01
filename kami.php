<?php
/**
 * 卡密领取页面 - 用户前台自助领取卡密
 * 
 * @author Ning.Si
 * @version 1.0.4
 * @date 2026-02-13
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class.database.php';
require_once __DIR__ . '/includes/class.cardkeyclaim.php';

$db = Database::getInstance();
$cardKeyClaim = new CardKeyClaim();

$error = '';
$success = '';
$claimedCardKey = '';

// 处理领取请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batchId = intval($_POST['batch_id'] ?? 0);
    $deviceUuid = $_POST['device_uuid'] ?? '';
    $deviceName = $_POST['device_name'] ?? '';
    $inputPassword = $_POST['claim_password'] ?? '';
    
    $batchInfo = $cardKeyClaim->getBatch($batchId);
    
    // 验证UDID格式 (8位十六进制-16位十六进制)
    $udidPattern = '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{16}$/';
    
    if (empty($batchId)) {
        $error = '请选择领取活动';
    } elseif (empty($deviceUuid)) {
        $error = '请输入设备UDID';
    } elseif (!preg_match($udidPattern, $deviceUuid)) {
        $error = 'UDID格式不正确，正确格式如：00007130-000318863C03C03D';
    } elseif (!empty($batchInfo['password']) && $inputPassword !== $batchInfo['password']) {
        $error = '口令错误，请重新输入';
    } else {
        $result = $cardKeyClaim->claimCard($batchId, $deviceUuid, $deviceName);
        
        if ($result['success']) {
            $success = '领取成功！';
            $claimedCardKey = $result['card_key'];
        } else {
            $error = $result['message'];
        }
    }
}

$activeBatches = $cardKeyClaim->getActiveBatches();

$siteSettings = [];
$settingNames = ['site_title', 'frontend_title', 'icp_number', 'copyright', 'card_claim_enabled'];
$placeholders = implode(',', array_fill(0, count($settingNames), '?'));
$rows = $db->fetchAll("SELECT name, value FROM " . $db->getTable('config') . " WHERE name IN ($placeholders)", $settingNames);
foreach ($rows as $row) {
    $siteSettings[$row['name']] = $row['value'];
}
$siteTitle = $siteSettings['site_title'] ?? '';
$frontendTitle = $siteSettings['frontend_title'] ?? '';
$icpNumber = $siteSettings['icp_number'] ?? '';
$copyright = $siteSettings['copyright'] ?? '';
$claimEnabled = $siteSettings['card_claim_enabled'] ?? '1';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>卡密领取 - <?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="el-header">
        <div class="el-header__content">
            <a href="/" class="el-header__logo">
                <i class="fa fa-mobile-alt"></i>
                <?php echo htmlspecialchars($frontendTitle); ?>
            </a>
            <nav class="el-header__nav">
                <a href="/"><i class="fa fa-key"></i> 卡密激活</a>
                <a href="app.php"><i class="fa fa-th-large"></i> 应用中心</a>
                <a href="docs.php"><i class="fa fa-book"></i> 文档</a>
            </nav>
        </div>
    </header>

    <div class="el-container">

        <?php if ($error): ?>
        <div class="el-alert el-alert--error">
            <i class="fa fa-exclamation-circle"></i>
            <div><?php echo $error; ?></div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="el-alert el-alert--success">
            <i class="fa fa-check-circle"></i>
            <div><?php echo $success; ?></div>
        </div>

        <div class="el-result">
            <div class="el-result__title">您的卡密</div>
            <div class="el-result__content" id="claimedCardKey"><?php echo htmlspecialchars($claimedCardKey); ?></div>
        </div>

        <div style="text-align: center; margin-bottom: 20px;">
            <button onclick="copyCardKey()" class="el-button el-button--success" style="margin-right: 10px;">
                <i class="fa fa-copy"></i> 复制卡密
            </button>
            <a href="kami.php" class="el-button el-button--primary" style="width: auto; text-decoration: none;">
                <i class="fa fa-refresh"></i> 再领一张
            </a>
        </div>

        <?php elseif ($claimEnabled !== '1'): ?>
        <div class="el-empty">
            <div class="el-empty__icon"><i class="fa fa-pause-circle"></i></div>
            <h3>卡密领取功能已暂停</h3>
            <p style="color: #909399; margin-top: 10px;">请稍后再来或联系管理员</p>
        </div>

        <?php elseif (empty($activeBatches)): ?>
        <div class="el-empty">
            <div class="el-empty__icon"><i class="fa fa-inbox"></i></div>
            <h3>暂无可用领取活动</h3>
            <p style="color: #909399; margin-top: 10px;">请稍后再来或联系管理员</p>
        </div>

        <?php else: ?>
        <div class="el-card">
            <div class="el-card__header">
                <i class="fa fa-ticket" style="color: var(--el-color-primary);"></i>
                <span class="el-card__title">领取卡密</span>
            </div>
            <div class="el-card__body">
                <form method="POST" action="">
                    <div class="el-form-item">
                        <label class="el-form-item__label is-required">选择领取活动</label>
                        <div class="el-input">
                            <i class="fa fa-ticket el-input__icon"></i>
                            <select name="batch_id" id="batchSelect" required class="el-select" style="padding-left: 35px;" onchange="updateBatchInfo()">
                                <option value="">请选择活动</option>
                                <?php foreach ($activeBatches as $batch): 
                                    $remaining = $batch['total_count'] - $batch['used_count'];
                                ?>
                                <option value="<?php echo $batch['id']; ?>" 
                                        data-total="<?php echo $batch['total_count']; ?>"
                                        data-used="<?php echo $batch['used_count']; ?>"
                                        data-remaining="<?php echo $remaining; ?>"
                                        data-type="<?php echo CardKeyClaim::getTypeName($batch['batch_type']); ?>"
                                        data-days="<?php echo $batch['expire_days']; ?>"
                                        data-remark="<?php echo htmlspecialchars($batch['remark']); ?>"
                                        data-password="<?php echo htmlspecialchars($batch['password'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($batch['batch_name']); ?> (剩余<?php echo $remaining; ?>张)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="batchInfo" class="hidden" style="background: #f5f7fa; border-radius: 4px; padding: 15px; margin-bottom: 18px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-weight: 500;" id="batchName">-</span>
                            <span class="el-tag" id="batchType">-</span>
                        </div>
                        <div style="font-size: 13px; color: #909399; margin-bottom: 10px;" id="batchDetails">-</div>
                        <div class="el-progress">
                            <div class="el-progress-bar" id="progressFill" style="width: 0%"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: #909399;">
                            <span id="progressText">0/0</span>
                            <span id="remainingText">剩余 0 张</span>
                        </div>
                    </div>

                    <div id="passwordField" class="el-form-item hidden">
                        <label class="el-form-item__label is-required">领取口令</label>
                        <div class="el-input">
                            <i class="fa fa-lock el-input__icon"></i>
                            <input type="text" name="claim_password" class="el-input__inner" placeholder="请输入领取口令">
                        </div>
                    </div>

                    <div class="el-form-item">
                        <label class="el-form-item__label is-required">设备UDID</label>
                        <div class="el-input">
                            <i class="fa fa-mobile el-input__icon"></i>
                            <input type="text" name="device_uuid" class="el-input__inner" 
                                   placeholder="00007130-000318863C03C03D"
                                   pattern="[a-fA-F0-9]{8}-[a-fA-F0-9]{16}"
                                   required>
                        </div>
                        <small style="color: #909399; margin-top: 5px; display: block;">格式: 00007130-000318863C03C03D</small>
                    </div>

                    <div class="el-form-item">
                        <label class="el-form-item__label">设备名称 <small style="color: #909399; font-weight: normal;">(可选)</small></label>
                        <div class="el-input">
                            <i class="fa fa-tag el-input__icon"></i>
                            <input type="text" name="device_name" class="el-input__inner" placeholder="如: iPhone 14 Pro">
                        </div>
                    </div>

                    <button type="submit" class="el-button el-button--primary">
                        <i class="fa fa-gift"></i> 立即领取
                    </button>
                </form>
            </div>
        </div>

        <div class="el-card" style="background: #ecf5ff; border-color: #d9ecff;">
            <div class="el-card__header">
                <i class="fa fa-info-circle" style="color: var(--el-color-primary);"></i>
                <span class="el-card__title">领取说明</span>
            </div>
            <div class="el-card__body">
                <ul style="margin-left: 20px; color: #606266; font-size: 14px; line-height: 1.8;">
                    <li>请选择可用的领取活动</li>
                    <li>输入正确的设备UDID（格式: 8位-16位十六进制）</li>
                    <li>领取成功后请立即复制保存卡密</li>
                    <li>卡密一经领取无法重复获取</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer class="el-footer">
        <?php if ($icpNumber || $copyright): ?>
        <div style="margin-top: 15px; line-height: 1.8;">
            <?php if ($icpNumber): ?><div><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none; transition: color 0.3s ease;" onmouseover="this.style.color='var(--el-color-primary)'" onmouseout="this.style.color='inherit'"><?php echo htmlspecialchars($icpNumber); ?></a></div><?php endif; ?>
            <?php if ($copyright): ?><div><?php echo htmlspecialchars($copyright); ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
    </footer>

    <script src="assets/js/kami.js"></script>
    <script>console.log("\n %c Ning.Si软件源管理系统 %c by Ning.Si | https://github.com/XooUooX/iOS_AppStore ", "color:#fff;background:#409EFF;padding:5px 0;", "color:#eee;background:#444;padding:5px 10px;");</script>
</body>
</html>
