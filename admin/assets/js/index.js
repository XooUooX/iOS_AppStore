function openStatSettingsModal() {
    document.getElementById('statSettingsModal').style.display = 'flex';
}

function closeStatSettingsModal() {
    document.getElementById('statSettingsModal').style.display = 'none';
}

function saveStatSettings() {
    document.getElementById('stat-settings-form').submit();
}

window.onclick = function(event) {
    var modal = document.getElementById('statSettingsModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
