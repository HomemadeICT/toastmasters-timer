function updateDefaults(role) {
    const minM = document.getElementById('add_min_min');
    const tarM = document.getElementById('add_target_min');
    const maxM = document.getElementById('add_max_min');
    const minS = document.getElementById('add_min_sec');
    const tarS = document.getElementById('add_target_sec');
    const maxS = document.getElementById('add_max_sec');

    minS.value = 0; tarS.value = 0; maxS.value = 0;

    if (role === 'Prepared Speech') { minM.value = 5; tarM.value = 6; maxM.value = 7; }
    else if (role === 'Ice Breaker') { minM.value = 4; tarM.value = 5; maxM.value = 6; }
    else if (role === 'Table Topic') { minM.value = 1; tarM.value = 1; tarS.value = 30; maxM.value = 2; }
    else if (role === 'Evaluator') { minM.value = 2; tarM.value = 2; tarS.value = 30; maxM.value = 3; }
    else if (role === 'Round Robin') { minM.value = 0; minS.value = 10; tarM.value = 0; tarS.value = 15; maxM.value = 0; maxS.value = 20; }
}

function loadEventTypeDefaults(selectEl) {
    if (!selectEl.value) return;
    const option = selectEl.options[selectEl.selectedIndex];

    // Use data attributes for reliable time parsing (no regex issues)
    const minTime = parseInt(option.dataset.min);
    const targetTime = parseInt(option.dataset.target);
    const maxTime = parseInt(option.dataset.max);

    if (isNaN(minTime) || isNaN(targetTime) || isNaN(maxTime)) return;

    const form = selectEl.closest('form');
    if (!form) return;

    form.querySelector('input[name="min_min"]').value = Math.floor(minTime / 60);
    form.querySelector('input[name="min_sec"]').value = minTime % 60;
    form.querySelector('input[name="target_min"]').value = Math.floor(targetTime / 60);
    form.querySelector('input[name="target_sec"]').value = targetTime % 60;
    form.querySelector('input[name="max_min"]').value = Math.floor(maxTime / 60);
    form.querySelector('input[name="max_sec"]').value = maxTime % 60;

    // Auto-populate the role input field with the preset name to avoid typos
    let rawText = option.innerText || option.textContent;
    let roleName = rawText.replace(/\s*\([^)]*\)$/, '').trim();
    const roleInput = form.querySelector('input[name="role"]');
    if (roleInput) {
        roleInput.value = roleName;
    }
}

let eventTypesList = [];
const meetingId = Number(document.body.dataset.meetingId);

function loadEventTypes() {
    apiPost('api/event_types.php', { action: 'get_types', meeting_id: meetingId })
        .then(data => {
            eventTypesList = data;
            renderEventTypes();
        })
        .catch(err => alert(err.message));
}

function renderEventTypes() {
    const container = document.getElementById('event-types-list');
    if (!container) return;

    container.innerHTML = eventTypesList.map(et => `
        <div class="event-type-item" style="background:#f8f8f8; padding:1rem; border-radius:8px; margin-bottom:0.5rem;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <strong>${escapeHtml(et.name)}</strong><br>
                    <small style="color:#666;">
                        ${Math.floor(et.min_time/60)}:${String(et.min_time%60).padStart(2,'0')} - 
                        ${Math.floor(et.target_time/60)}:${String(et.target_time%60).padStart(2,'0')} - 
                        ${Math.floor(et.max_time/60)}:${String(et.max_time%60).padStart(2,'0')}
                    </small>
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <button type="button" onclick="editEventType(${et.id})" class="btn" style="background:#e2e8f0; padding:0.5rem 1rem; font-size:0.8rem;">Edit</button>
                    <button type="button" onclick="deleteEventType(${et.id})" class="btn" style="background:#ef4444; color:white; padding:0.5rem 1rem; font-size:0.8rem;">Delete</button>
                </div>
            </div>
        </div>
    `).join('');
}

function deleteEventType(eventTypeId) {
    if (!confirm('Delete this event type?')) return;
    apiPost('api/event_types.php', { action: 'delete_type', event_type_id: eventTypeId })
        .then(() => loadEventTypes())
        .catch(err => alert(err.message));
}

function editEventType(id) {
    const et = eventTypesList.find(e => e.id === id);
    if (!et) return;

    document.getElementById('edit_event_id').value = et.id;
    document.getElementById('new_event_name').value = et.name;
    document.getElementById('new_event_min_min').value = Math.floor(et.min_time / 60);
    document.getElementById('new_event_min_sec').value = et.min_time % 60;
    document.getElementById('new_event_target_min').value = Math.floor(et.target_time / 60);
    document.getElementById('new_event_target_sec').value = et.target_time % 60;
    document.getElementById('new_event_max_min').value = Math.floor(et.max_time / 60);
    document.getElementById('new_event_max_sec').value = et.max_time % 60;

    document.getElementById('event_form_title').innerText = 'Edit Event Type';
    document.getElementById('btn_save_event').innerText = 'Update Event Type';
    document.getElementById('btn_cancel_event').style.display = 'block';
}

function cancelEditEventType() {
    document.getElementById('edit_event_id').value = '';
    document.getElementById('new_event_name').value = '';
    document.getElementById('new_event_min_min').value = '0';
    document.getElementById('new_event_min_sec').value = '0';
    document.getElementById('new_event_target_min').value = '8';
    document.getElementById('new_event_target_sec').value = '0';
    document.getElementById('new_event_max_min').value = '10';
    document.getElementById('new_event_max_sec').value = '0';

    document.getElementById('event_form_title').innerText = 'Create New Event Type';
    document.getElementById('btn_save_event').innerText = 'Add Event Type';
    document.getElementById('btn_cancel_event').style.display = 'none';
}

function saveEventType() {
    const editId = document.getElementById('edit_event_id').value;
    const name = document.getElementById('new_event_name').value;
    const minMin = parseInt(document.getElementById('new_event_min_min').value) || 0;
    const minSec = parseInt(document.getElementById('new_event_min_sec').value) || 0;
    const targetMin = parseInt(document.getElementById('new_event_target_min').value) || 0;
    const targetSec = parseInt(document.getElementById('new_event_target_sec').value) || 0;
    const maxMin = parseInt(document.getElementById('new_event_max_min').value) || 0;
    const maxSec = parseInt(document.getElementById('new_event_max_sec').value) || 0;

    if (!name) {
        alert('Event name is required');
        return;
    }

    const minTime = minMin * 60 + minSec;
    const targetTime = targetMin * 60 + targetSec;
    const maxTime = maxMin * 60 + maxSec;

    const times = { name: name, min_time: minTime, target_time: targetTime, max_time: maxTime };
    const params = editId
        ? { action: 'update_type', event_type_id: editId, ...times }
        : { action: 'create_type', meeting_id: meetingId, ...times };

    apiPost('api/event_types.php', params)
        .then(() => {
            cancelEditEventType();
            location.reload(); // Reload to update speaker form dropdowns
        })
        .catch(err => alert(err.message || 'Failed to save event type.'));
}

loadEventTypes();
