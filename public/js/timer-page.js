const config = JSON.parse(document.body.dataset.config);

function toggleQuickEdit() {
    const panel = document.getElementById('quickEditPanel');
    panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
}

function saveQuickEdit() {
    const newName = document.getElementById('editSpeakerName').value;
    const newRole = document.getElementById('editSpeakerRole').value;
    const updateBtn = document.getElementById('quickEditSaveBtn');
    updateBtn.innerText = 'Saving...';

    apiPost('timer.php', { ajax_update: 1, id: config.speakerId, name: newName, role: newRole })
        .then(() => {
            config.role = newRole;
            document.getElementById('speaker-name-display').innerText = newName;
            document.getElementById('speaker-role-display').innerText = newRole;
            toggleQuickEdit();
        })
        .catch(err => alert(err.message || 'Failed to save'))
        .finally(() => { updateBtn.innerText = 'Save'; });
}
