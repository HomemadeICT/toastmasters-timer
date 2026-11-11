<?php
require_once __DIR__ . '/../src/bootstrap.php';

$uid = require_login();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$meeting = $id ? get_owned_meeting($id, $uid) : null;
if (!$meeting) {
    abort(404, 'Meeting not found.');
}

/** Reads and validates the speaker form fields shared by add/edit. */
function speaker_form_input(int $meetingId): array
{
    $name = clean_text($_POST['name'] ?? '');
    $role = clean_text($_POST['role'] ?? '');
    if ($name === '' || $role === '') {
        abort(400, 'Name and role are required.');
    }
    $limits = valid_limits(
        post_seconds('min_min', 'min_sec'),
        post_seconds('target_min', 'target_sec'),
        post_seconds('max_min', 'max_sec')
    );
    if ($limits === null) {
        abort(400, 'Times must be between 0 and 24h, and min <= target <= max.');
    }
    // Built-in presets ("preset_...") are not DB rows: treat as no event type.
    $raw = $_POST['event_type_id'] ?? '';
    $eventTypeId = (is_numeric($raw) && (int)$raw > 0) ? (int)$raw : null;
    if ($eventTypeId !== null) {
        $stmt = db()->prepare('SELECT 1 FROM event_types WHERE id = ? AND meeting_id = ?');
        $stmt->bind_param('ii', $eventTypeId, $meetingId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_row()) {
            $eventTypeId = null;
        }
    }
    return [$name, $role, $eventTypeId, ...$limits];
}

function move_speaker(int $meetingId, int $speakerId, int $newPosition): void
{
    $conn = db();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT id FROM speakers WHERE meeting_id = ? ORDER BY sort_order ASC, id ASC FOR UPDATE');
        $stmt->bind_param('i', $meetingId);
        $stmt->execute();
        $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
        $ids = array_values(array_diff($ids, [$speakerId]));
        $index = max(0, min(count($ids), $newPosition - 1));
        array_splice($ids, $index, 0, [$speakerId]);

        $upd = $conn->prepare('UPDATE speakers SET sort_order = ? WHERE id = ?');
        foreach ($ids as $i => $sid) {
            $pos = $i + 1;
            $upd->bind_param('ii', $pos, $sid);
            $upd->execute();
        }
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/** Speaker id from the form, only if it belongs to this meeting. */
function posted_speaker_id(int $meetingId): int
{
    $sid = (int)($_POST['speaker_id'] ?? 0);
    $stmt = db()->prepare('SELECT id FROM speakers WHERE id = ? AND meeting_id = ?');
    $stmt->bind_param('ii', $sid, $meetingId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_row()) {
        abort(404, 'Speaker not found.');
    }
    return $sid;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $conn = db();
    if (isset($_POST['add_speaker'])) {
        [$name, $role, $eventTypeId, $min, $target, $max] = speaker_form_input($id);
        $stmt = $conn->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM speakers WHERE meeting_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sort = (int)$stmt->get_result()->fetch_column();

        $stmt = $conn->prepare("INSERT INTO speakers (meeting_id, name, role, event_type_id, min_time, target_time, max_time, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiiiii", $id, $name, $role, $eventTypeId, $min, $target, $max, $sort);
        $stmt->execute();
    } elseif (isset($_POST['edit_speaker'])) {
        $sid = posted_speaker_id($id);
        [$name, $role, $eventTypeId, $min, $target, $max] = speaker_form_input($id);
        $stmt = $conn->prepare("UPDATE speakers SET name = ?, role = ?, event_type_id = ?, min_time = ?, target_time = ?, max_time = ? WHERE id = ?");
        $stmt->bind_param("ssiiiii", $name, $role, $eventTypeId, $min, $target, $max, $sid);
        $stmt->execute();
    } elseif (isset($_POST['update_order'])) {
        move_speaker($id, posted_speaker_id($id), (int)($_POST['sort_order'] ?? 1));
    } elseif (isset($_POST['delete_speaker'])) {
        $sid = posted_speaker_id($id);
        $stmt = $conn->prepare("DELETE FROM speakers WHERE id = ?");
        $stmt->bind_param("i", $sid);
        $stmt->execute();
    } elseif (isset($_POST['edit_meeting'])) {
        $m_name = clean_text($_POST['m_name'] ?? '');
        $m_date = valid_date($_POST['m_date'] ?? '');
        if ($m_name === '' || $m_date === null) {
            abort(400, 'A meeting name and a valid date are required.');
        }
        $m_location = clean_text($_POST['m_location'] ?? '');
        $m_theme = clean_text($_POST['m_theme'] ?? '');
        $m_tmod = clean_text($_POST['m_tmod'] ?? '');
        $m_timer = clean_text($_POST['m_timer'] ?? '');

        $stmt = $conn->prepare("UPDATE meetings SET name = ?, date = ?, location = ?, theme = ?, tmod = ?, timer_name = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ssssssii", $m_name, $m_date, $m_location, $m_theme, $m_tmod, $m_timer, $id, $uid);
        $stmt->execute();
    }
    redirect("meeting.php?id=$id");
}

// Fetch speakers
$stmt = db()->prepare("SELECT * FROM speakers WHERE meeting_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->bind_param("i", $id);
$stmt->execute();
$speakers = $stmt->get_result();

// Fetch event types
$stmt = db()->prepare("SELECT * FROM event_types WHERE meeting_id = ? ORDER BY name ASC");
$stmt->bind_param("i", $id);
$stmt->execute();
$event_types = $stmt->get_result();

// Built-in presets for common Toastmasters meeting roles
$builtin_presets = [
    // General Meeting Roles
    ['name' => 'Prepared Speech', 'min' => 300, 'target' => 360, 'max' => 420, 'group' => 'General Meeting'],
    ['name' => 'Ice Breaker', 'min' => 240, 'target' => 300, 'max' => 360, 'group' => 'General Meeting'],
    ['name' => 'Table Topic', 'min' => 60, 'target' => 90, 'max' => 120, 'group' => 'General Meeting'],
    ['name' => 'Evaluator', 'min' => 120, 'target' => 150, 'max' => 180, 'group' => 'General Meeting'],
    ['name' => 'Prepared Speech Evaluator', 'min' => 120, 'target' => 150, 'max' => 180, 'group' => 'General Meeting'],
    ['name' => 'Table Topic Evaluator (Odd)', 'min' => 120, 'target' => 150, 'max' => 180, 'group' => 'General Meeting'],
    ['name' => 'Table Topic Evaluator (Even)', 'min' => 120, 'target' => 150, 'max' => 180, 'group' => 'General Meeting'],
    ['name' => 'General Evaluator', 'min' => 180, 'target' => 240, 'max' => 300, 'group' => 'General Meeting'],
    ['name' => 'Grammarian Report', 'min' => 120, 'target' => 150, 'max' => 180, 'group' => 'General Meeting'],
    ['name' => 'Ah Counter Report', 'min' => 60, 'target' => 90, 'max' => 120, 'group' => 'General Meeting'],
    ['name' => 'Timer Report', 'min' => 60, 'target' => 90, 'max' => 120, 'group' => 'General Meeting'],
    ['name' => 'Round Robin', 'min' => 10, 'target' => 15, 'max' => 20, 'group' => 'General Meeting'],
    // Pathways - Common Speech Projects
    ['name' => 'L1P1 - Ice Breaker (4-6 min)', 'min' => 240, 'target' => 300, 'max' => 360, 'group' => 'Pathways Projects'],
    ['name' => 'L1P2 - Evaluation & Feedback (5-7 min)', 'min' => 300, 'target' => 360, 'max' => 420, 'group' => 'Pathways Projects'],
    ['name' => 'L1P3 - Researching & Presenting (5-7 min)', 'min' => 300, 'target' => 360, 'max' => 420, 'group' => 'Pathways Projects'],
    ['name' => 'L2P1 - Understanding Your Leadership Style (5-7 min)', 'min' => 300, 'target' => 360, 'max' => 420, 'group' => 'Pathways Projects'],
    ['name' => 'L2P2 - Active Listening (5-7 min)', 'min' => 300, 'target' => 360, 'max' => 420, 'group' => 'Pathways Projects'],
    ['name' => 'L2P3 - Introduction to Toastmasters Mentoring (5-7 min)', 'min' => 300, 'target' => 360, 'max' => 420, 'group' => 'Pathways Projects'],
    ['name' => 'L3P1 - Persuasive Speaking (5-7 min)', 'min' => 300, 'target' => 360, 'max' => 420, 'group' => 'Pathways Projects'],
    ['name' => 'L3P2 - Visionary Leadership (5-7 min)', 'min' => 300, 'target' => 360, 'max' => 420, 'group' => 'Pathways Projects'],
    ['name' => 'L4P1 - Presentation Mastery (10-15 min)', 'min' => 600, 'target' => 750, 'max' => 900, 'group' => 'Pathways Projects'],
    ['name' => 'L4P2 - Managing a Difficult Audience (12-15 min)', 'min' => 720, 'target' => 810, 'max' => 900, 'group' => 'Pathways Projects'],
    ['name' => 'L5P1 - Reflect on Your Path (10-12 min)', 'min' => 600, 'target' => 660, 'max' => 720, 'group' => 'Pathways Projects'],
    ['name' => 'L5P2 - High Performance Leadership (5-7 min)', 'min' => 300, 'target' => 360, 'max' => 420, 'group' => 'Pathways Projects'],
    // Other
    ['name' => 'Q&A Session', 'min' => 300, 'target' => 480, 'max' => 600, 'group' => 'Other'],
];

// Helper to render the built-in presets as optgroup options
function renderBuiltinPresets($presets, $selected_name = '') {
    $groups = [];
    foreach ($presets as $p) {
        $groups[$p['group']][] = $p;
    }
    $html = '';
    foreach ($groups as $group_name => $items) {
        $html .= '<optgroup label="' . htmlspecialchars($group_name) . '">';
        foreach ($items as $p) {
            $label = htmlspecialchars($p['name']);
            $time_display = floor($p['min']/60) . ':' . str_pad($p['min']%60, 2, '0', STR_PAD_LEFT) . ' - '
                          . floor($p['target']/60) . ':' . str_pad($p['target']%60, 2, '0', STR_PAD_LEFT) . ' - '
                          . floor($p['max']/60) . ':' . str_pad($p['max']%60, 2, '0', STR_PAD_LEFT);
            $selected = ($selected_name === $p['name']) ? ' selected' : '';
            $html .= '<option value="preset_' . htmlspecialchars($p['name']) . '"'
                   . ' data-min="' . $p['min'] . '"'
                   . ' data-target="' . $p['target'] . '"'
                   . ' data-max="' . $p['max'] . '"'
                   . $selected . '>'
                   . $label . ' (' . $time_display . ')</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($meeting['name']); ?> - Toastmasters Timer
    </title>
    <?php echo favicon_links(); ?>
    <link rel="stylesheet" href="<?php echo h(asset('css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/styles.css')); ?>">
    <meta name="csrf-token" content="<?php echo h(csrf_token()); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/meeting.css')); ?>">
</head>

<body class="zoom-global" data-meeting-id="<?php echo (int)$id; ?>">
    <div class="container">
        <header>
            <a href="index.php" class="btn-back"
                style="text-decoration:none; color: var(--text-muted); font-weight:600;">← Back to Meetings</a>
            <h1 style="margin-top:1rem;">
                <?php echo htmlspecialchars($meeting['name']); ?>
            </h1>
            <p>
                <?php echo date('l, j F Y', strtotime($meeting['date'])); ?> |
                <?php echo htmlspecialchars($meeting['location']); ?>
            </p>
            <?php if ($meeting['theme']): ?>
                <p style="margin-top:0.5rem; font-style:italic;">Theme: "
                    <?php echo htmlspecialchars($meeting['theme']); ?>"
                </p>
            <?php endif; ?>

            <div style="margin-top: 2rem; display:flex; gap:10px; flex-wrap:wrap;">
                <a href="report.php?id=<?php echo $id; ?>" class="btn btn-primary"
                    style="background:var(--secondary); flex:1; min-width:150px; text-align:center;">Generate Report</a>
                <a href="#editMeeting" class="btn" style="background:#e2e8f0; flex:1; min-width:150px; text-align:center;">Edit Meeting</a>
                <a href="cards.php?id=<?php echo $id; ?>" class="btn" style="background:var(--accent); color:var(--primary); flex:1; min-width:150px; text-align:center;">Manual Cards</a>
                <a href="#addSpeaker" class="btn btn-primary" style="flex:1; min-width:150px; text-align:center;">Add Speaker</a>
                <a href="#manageEvents" class="btn" style="background:#a78bfa; color:white; flex:1; min-width:150px; text-align:center;">Manage Events</a>
            </div>
        </header>

        <!-- Edit Meeting Modal -->
        <div id="editMeeting" class="modal">
            <div class="modal-content">
                <h3>Edit Meeting Details</h3>
                <form method="POST">
<?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label>Meeting Name</label>
                        <input type="text" name="m_name" value="<?php echo htmlspecialchars($meeting['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="m_date" value="<?php echo h($meeting['date']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="m_location" value="<?php echo htmlspecialchars($meeting['location']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Theme</label>
                        <input type="text" name="m_theme" value="<?php echo htmlspecialchars($meeting['theme']); ?>">
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>TMOD</label>
                            <input type="text" name="m_tmod" value="<?php echo htmlspecialchars($meeting['tmod']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Timer</label>
                            <input type="text" name="m_timer" value="<?php echo htmlspecialchars($meeting['timer_name']); ?>">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="edit_meeting" class="btn btn-primary">Update Meeting</button>
                        <a href="#" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <section class="speakers">
            <h2>Speakers</h2>
            <div id="speaker-list">
                <?php while ($s = $speakers->fetch_assoc()): ?>
                    <div class="speaker-item">
                        <div class="speaker-info">
                            <h4>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </h4>
                            <div class="role">
                                <?php echo htmlspecialchars($s['role']); ?>
                            </div>
                            <div class="time-limits">
                                Limits:
                                <?php echo floor($s['min_time'] / 60) . ":" . str_pad($s['min_time'] % 60, 2, '0', STR_PAD_LEFT); ?>
                                |
                                <?php echo floor($s['target_time'] / 60) . ":" . str_pad($s['target_time'] % 60, 2, '0', STR_PAD_LEFT); ?>
                                |
                                <?php echo floor($s['max_time'] / 60) . ":" . str_pad($s['max_time'] % 60, 2, '0', STR_PAD_LEFT); ?>
                            </div>
                            <?php if ($s['final_time']): ?>
                                <div style="margin-top:0.5rem; font-weight:bold; color:var(--primary);">Final:
                                    <?php echo h($s['final_time']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="speaker-actions"
                            style="display:flex; flex-direction:column; gap:0.25rem; align-items:flex-end;">
                            <a href="timer.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-primary">Start Timer</a>
                            <div style="display:flex; gap:0.5rem; align-items:center; margin-top:0.25rem;">
                                <form method="POST" style="display:flex; gap:0.25rem; align-items:center;">
<?php echo csrf_field(); ?>
                                    <input type="hidden" name="speaker_id" value="<?php echo (int)$s['id']; ?>">
                                    <label style="margin:0; font-size:0.7rem;">Order:</label>
                                    <input type="number" name="sort_order" value="<?php echo $s['sort_order']; ?>"
                                        style="width:50px; padding:0.25rem; font-size:0.8rem; text-align:center;">
                                    <button type="submit" name="update_order" class="btn"
                                        style="padding:0.25rem 0.5rem; font-size:0.7rem; background:#e2e8f0;">SET</button>
                                </form>
                            </div>
                            <div style="display:flex; gap:0.5rem; width:100%;">
                                <a href="#editSpeaker<?php echo (int)$s['id']; ?>" class="btn"
                                    style="flex:1; padding:0.25rem; font-size:0.8rem; background:#e2e8f0; text-align:center;">Edit
                                    Details</a>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Modal -->
                    <div id="editSpeaker<?php echo (int)$s['id']; ?>" class="modal">
                        <div class="modal-content">
                            <h3>Edit Speaker</h3>
                            <form method="POST">
<?php echo csrf_field(); ?>
                                <input type="hidden" name="speaker_id" value="<?php echo (int)$s['id']; ?>">
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($s['name']); ?>"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label>Role</label>
                                    <input type="text" name="role" value="<?php echo htmlspecialchars($s['role']); ?>"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label>Event Type (Optional)</label>
                                    <select name="event_type_id" class="input" onchange="loadEventTypeDefaults(this)">
                                        <option value="">-- Custom Timing --</option>
                                        <?php 
                                            // Reset the event types result for each speaker
                                            $event_types->data_seek(0);
                                            while ($et = $event_types->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $et['id']; ?>" 
                                                data-min="<?php echo $et['min_time']; ?>"
                                                data-target="<?php echo $et['target_time']; ?>"
                                                data-max="<?php echo $et['max_time']; ?>"
                                                <?php echo ($s['event_type_id'] == $et['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($et['name']); ?> 
                                                (<?php echo floor($et['min_time']/60); ?>:<?php echo str_pad($et['min_time']%60, 2, '0', STR_PAD_LEFT); ?> - 
                                                <?php echo floor($et['target_time']/60); ?>:<?php echo str_pad($et['target_time']%60, 2, '0', STR_PAD_LEFT); ?> - 
                                                <?php echo floor($et['max_time']/60); ?>:<?php echo str_pad($et['max_time']%60, 2, '0', STR_PAD_LEFT); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                        <?php echo renderBuiltinPresets($builtin_presets); ?>
                                    </select>
                                </div>

                                <div class="form-grid-3">
                                    <div class="form-group">
                                        <label>Min (m/s)</label>
                                        <div style="display:flex; gap:5px;">
                                            <input type="number" name="min_min"
                                                value="<?php echo floor($s['min_time'] / 60); ?>" style="flex: 1;">
                                            <input type="number" name="min_sec" value="<?php echo $s['min_time'] % 60; ?>"
                                                style="flex: 1;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Target (m/s)</label>
                                        <div style="display:flex; gap:5px;">
                                            <input type="number" name="target_min"
                                                value="<?php echo floor($s['target_time'] / 60); ?>" style="flex: 1;">
                                            <input type="number" name="target_sec"
                                                value="<?php echo $s['target_time'] % 60; ?>" style="flex: 1;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Max (m/s)</label>
                                        <div style="display:flex; gap:5px;">
                                            <input type="number" name="max_min"
                                                value="<?php echo floor($s['max_time'] / 60); ?>" style="flex: 1;">
                                            <input type="number" name="max_sec" value="<?php echo $s['max_time'] % 60; ?>"
                                                style="flex: 1;">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="edit_speaker" class="btn btn-primary">Update</button>
                                    <button type="submit" name="delete_speaker" class="btn btn-danger"
                                        onclick="return confirm('Are you sure?')">Delete</button>
                                    <a href="#" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>

        <!-- Manage Events Modal -->
        <div id="manageEvents" class="modal">
            <div class="modal-content" style="max-width:600px;">
                <h3>Manage Event Types</h3>
                
                <div style="margin-bottom:2rem; padding:1rem; background:#f0f4ff; border-radius:8px;">
                    <h4 style="margin-top:0;" id="event_form_title">Create New Event Type</h4>
                    <input type="hidden" id="edit_event_id" value="">
                    <div class="form-group">
                        <label>Event Name</label>
                        <input type="text" id="new_event_name" placeholder="e.g., Q&A Session">
                    </div>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Green (min)</label>
                            <div style="display:flex; gap:3px;">
                                <input type="number" id="new_event_min_min" value="0" style="flex: 1;">
                                <input type="number" id="new_event_min_sec" value="0" style="flex: 1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Yellow (target)</label>
                            <div style="display:flex; gap:3px;">
                                <input type="number" id="new_event_target_min" value="8" style="flex: 1;">
                                <input type="number" id="new_event_target_sec" value="0" style="flex: 1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Red (max)</label>
                            <div style="display:flex; gap:3px;">
                                <input type="number" id="new_event_max_min" value="10" style="flex: 1;">
                                <input type="number" id="new_event_max_sec" value="0" style="flex: 1;">
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" id="btn_save_event" onclick="saveEventType()" class="btn btn-primary">Add Event Type</button>
                        <button type="button" id="btn_cancel_event" onclick="cancelEditEventType()" class="btn btn-secondary" style="display:none;">Cancel</button>
                    </div>
                </div>

                <h4>Current Event Types</h4>
                <div id="event-types-list" style="max-height: 300px; overflow-y: auto; padding-right: 0.5rem; margin-bottom: 1rem;"></div>

                <div class="form-actions">
                    <a href="#" class="btn btn-secondary" style="width:100%; text-align:center;">Close</a>
                </div>
            </div>
        </div>

        <!-- Add Speaker Modal -->
        <div id="addSpeaker" class="modal">
            <div class="modal-content">
                <h3>Add New Speaker</h3>
                <form method="POST">
<?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" name="role" placeholder="e.g., Prepared Speech" required>
                    </div>
                    <div class="form-group">
                        <label>Event Type (Optional)</label>
                        <select name="event_type_id" class="input" onchange="loadEventTypeDefaults(this)">
                            <option value="">-- Custom Timing --</option>
                            <?php 
                                // Reset the event types result
                                $event_types->data_seek(0);
                                while ($et = $event_types->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $et['id']; ?>"
                                    data-min="<?php echo $et['min_time']; ?>"
                                    data-target="<?php echo $et['target_time']; ?>"
                                    data-max="<?php echo $et['max_time']; ?>">
                                    <?php echo htmlspecialchars($et['name']); ?> 
                                    (<?php echo floor($et['min_time']/60); ?>:<?php echo str_pad($et['min_time']%60, 2, '0', STR_PAD_LEFT); ?> - 
                                    <?php echo floor($et['target_time']/60); ?>:<?php echo str_pad($et['target_time']%60, 2, '0', STR_PAD_LEFT); ?> - 
                                    <?php echo floor($et['max_time']/60); ?>:<?php echo str_pad($et['max_time']%60, 2, '0', STR_PAD_LEFT); ?>)
                                </option>
                            <?php endwhile; ?>
                            <?php echo renderBuiltinPresets($builtin_presets); ?>
                        </select>
                    </div>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Green Card (min)</label>
                            <div style="display:flex; gap:5px;">
                                <input type="number" name="min_min" id="add_min_min" value="5" style="flex: 1;">
                                <input type="number" name="min_sec" id="add_min_sec" value="0" style="flex: 1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Yellow Card (target)</label>
                            <div style="display:flex; gap:5px;">
                                <input type="number" name="target_min" id="add_target_min" value="6"
                                    style="flex: 1;">
                                <input type="number" name="target_sec" id="add_target_sec" value="0"
                                    style="flex: 1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Red Card (max/disqualify)</label>
                            <div style="display:flex; gap:5px;">
                                <input type="number" name="max_min" id="add_max_min" value="7" style="flex: 1;">
                                <input type="number" name="max_sec" id="add_max_sec" value="0" style="flex: 1;">
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_speaker" class="btn btn-primary">Save</button>
                        <a href="#" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo h(asset('js/app.js')); ?>"></script>
    <script src="<?php echo h(asset('js/meeting.js')); ?>"></script>
    <script src="<?php echo h(asset('js/obs-toggle.js')); ?>"></script>
    <script src="<?php echo h(asset('js/zoom-controls.js')); ?>"></script>
</body>

</html>