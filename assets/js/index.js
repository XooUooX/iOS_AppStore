// Ning.Si软件源管理系统 - index.php 页面脚本

// 关闭公告弹窗
function closeAnnouncement() {
    var modal = document.getElementById('announcementModal');
    if (modal) {
        modal.style.display = 'none';
    }
}
