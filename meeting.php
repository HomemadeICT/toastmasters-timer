<?php
require_once 'src/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch meeting details
$stmt = $conn->prepare("SELECT * FROM meetings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();

if (!$meeting) {
    die("Meeting not found.");
}

// Handle speaker management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_speaker'])) {
        $name = $_POST['name'];
        $role = $_POST['role'];
        // Built-in presets have values starting with "preset_" - treat as NULL (no DB record)
        $raw_event_id = $_POST['event_type_id'] ?? '';
        $event_type_id = (!empty($raw_event_id) && is_numeric($raw_event_id)) ? intval($raw_event_id) : null;
        $min = intval($_POST['min_min']) * 60 + intval($_POST['min_sec']);
        $target = intval($_POST['target_min']) * 60 + intval($_POST['target_sec']);
        $max = intval($_POST['max_min']) * 60 + intval($_POST['max_sec']);

        // Get max sort_order
        $sort_res = $conn->query("SELECT MAX(sort_order) as max_sort FROM speakers WHERE meeting_id = $id");
        $sort_val = $sort_res->fetch_assoc()['max_sort'] + 1;

        $stmt = $conn->prepare("INSERT INTO speakers (meeting_id, name, role, event_type_id, min_time, target_time, max_time, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiiiii", $id, $name, $role, $event_type_id, $min, $target, $max, $sort_val);
        $stmt->execute();
    } elseif (isset($_POST['edit_speaker'])) {
        $sid = intval($_POST['speaker_id']);
        $name = $_POST['name'];
        $role = $_POST['role'];
        // Built-in presets have values starting with "preset_" - treat as NULL (no DB record)
        $raw_event_id = $_POST['event_type_id'] ?? '';
        $event_type_id = (!empty($raw_event_id) && is_numeric($raw_event_id)) ? intval($raw_event_id) : null;
        $min = intval($_POST['min_min']) * 60 + intval($_POST['min_sec']);
        $target = intval($_POST['target_min']) * 60 + intval($_POST['target_sec']);
        $max = intval($_POST['max_min']) * 60 + intval($_POST['max_sec']);

        $stmt = $conn->prepare("UPDATE speakers SET name = ?, role = ?, event_type_id = ?, min_time = ?, target_time = ?, max_time = ? WHERE id = ?");
        $stmt->bind_param("ssiiiii", $name, $role, $event_type_id, $min, $target, $max, $sid);
        $stmt->execute();
    } elseif (isset($_POST['update_order'])) {
        $sid = intval($_POST['speaker_id']);
        $new_order = intval($_POST['sort_order']);

        // Get old order
        $old_res = $conn->query("SELECT sort_order FROM speakers WHERE id = $sid");
        $old_order = $old_res->fetch_assoc()['sort_order'];

        if ($new_order != $old_order) {
            if ($new_order < $old_order) {
                // Moving up: Increment those in between
                $conn->query("UPDATE speakers SET sort_order = sort_order + 1 WHERE meeting_id = $id AND sort_order >= $new_order AND sort_order < $old_order");
            } else {
                // Moving down: Decrement those in between
                $conn->query("UPDATE speakers SET sort_order = sort_order - 1 WHERE meeting_id = $id AND sort_order > $old_order AND sort_order <= $new_order");
            }
            $stmt = $conn->prepare("UPDATE speakers SET sort_order = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_order, $sid);
            $stmt->execute();

            // Normalize sequence to 1, 2, 3...
            $res = $conn->query("SELECT id FROM speakers WHERE meeting_id = $id ORDER BY sort_order ASC, id ASC");
            $i = 1;
            while ($row = $res->fetch_assoc()) {
                $conn->query("UPDATE speakers SET sort_order = $i WHERE id = " . $row['id']);
                $i++;
            }
        }
    } elseif (isset($_POST['delete_speaker'])) {
        $sid = intval($_POST['speaker_id']);
        $stmt = $conn->prepare("DELETE FROM speakers WHERE id = ?");
        $stmt->bind_param("i", $sid);
        $stmt->execute();
    } elseif (isset($_POST['edit_meeting'])) {
        $m_name = $_POST['m_name'];
        $m_date = $_POST['m_date'];
        $m_location = $_POST['m_location'];
        $m_theme = $_POST['m_theme'];
        $m_tmod = $_POST['m_tmod'];
        $m_timer = $_POST['m_timer'];
        
        $stmt = $conn->prepare("UPDATE meetings SET name = ?, date = ?, location = ?, theme = ?, tmod = ?, timer_name = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $m_name, $m_date, $m_location, $m_theme, $m_tmod, $m_timer, $id);
        $stmt->execute();
    }
    header("Location: meeting.php?id=$id");
    exit;
}

// Fetch speakers
$stmt = $conn->prepare("SELECT * FROM speakers WHERE meeting_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->bind_param("i", $id);
$stmt->execute();
$speakers = $stmt->get_result();

// Fetch event types
$stmt = $conn->prepare("SELECT * FROM event_types WHERE meeting_id = ? ORDER BY name ASC");
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
    <link rel="icon" type="image/png" href="Toastmasters%20icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=4">
    <style>
        .speaker-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
        }

        .speaker-info h4 {
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .speaker-info .role {
            font-size: 0.875rem;
            color: var(--secondary);
            font-weight: 600;
        }

        .speaker-actions {
            display: flex;
            gap: 0.5rem;
        }
        .time-limits {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: flex-start;
            padding: 5vh 1rem;
            overflow-y: auto;
            z-index: 1000;
        }

        .modal:target {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            width: 100%;
            max-width: 500px;
            margin-bottom: 2rem; /* ensures space at bottom when scrolling */
        }
    </style>
</head>

<body class="zoom-global">
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
                    <div class="form-group">
                        <label>Meeting Name</label>
                        <input type="text" name="m_name" value="<?php echo htmlspecialchars($meeting['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="m_date" value="<?php echo $meeting['date']; ?>" required>
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
                                    <?php echo $s['final_time']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="speaker-actions"
                            style="display:flex; flex-direction:column; gap:0.25rem; align-items:flex-end;">
                            <a href="timer.php?id=<?php echo $s['id']; ?>" class="btn btn-primary">Start Timer</a>
                            <div style="display:flex; gap:0.5rem; align-items:center; margin-top:0.25rem;">
                                <form method="POST" style="display:flex; gap:0.25rem; align-items:center;">
                                    <input type="hidden" name="speaker_id" value="<?php echo $s['id']; ?>">
                                    <label style="margin:0; font-size:0.7rem;">Order:</label>
                                    <input type="number" name="sort_order" value="<?php echo $s['sort_order']; ?>"
                                        style="width:50px; padding:0.25rem; font-size:0.8rem; text-align:center;">
                                    <button type="submit" name="update_order" class="btn"
                                        style="padding:0.25rem 0.5rem; font-size:0.7rem; background:#e2e8f0;">SET</button>
                                </form>
                            </div>
                            <div style="display:flex; gap:0.5rem; width:100%;">
                                <a href="#editSpeaker<?php echo $s['id']; ?>" class="btn"
                                    style="flex:1; padding:0.25rem; font-size:0.8rem; background:#e2e8f0; text-align:center;">Edit
                                    Details</a>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Modal -->
                    <div id="editSpeaker<?php echo $s['id']; ?>" class="modal">
                        <div class="modal-content">
                            <h3>Edit Speaker</h3>
                            <form method="POST">
                                <input type="hidden" name="speaker_id" value="<?php echo $s['id']; ?>">
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

    <script>
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
        const meetingId = <?php echo $id; ?>;

        function loadEventTypes() {
            fetch('src/event_types.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_types&meeting_id=${meetingId}`
            })
            .then(r => r.json())
            .then(data => {
                eventTypesList = data;
                renderEventTypes();
            });
        }

        function renderEventTypes() {
            const container = document.getElementById('event-types-list');
            if (!container) return;
            
            container.innerHTML = eventTypesList.map(et => `
                <div class="event-type-item" style="background:#f8f8f8; padding:1rem; border-radius:8px; margin-bottom:0.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong>${et.name}</strong><br>
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
            fetch('src/event_types.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_type&event_type_id=${eventTypeId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    loadEventTypes();
                }
            });
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

            let action = 'create_type';
            let params = `action=${action}&meeting_id=${meetingId}&name=${encodeURIComponent(name)}&min_time=${minTime}&target_time=${targetTime}&max_time=${maxTime}`;
            
            if (editId) {
                action = 'update_type';
                params = `action=${action}&event_type_id=${editId}&name=${encodeURIComponent(name)}&min_time=${minTime}&target_time=${targetTime}&max_time=${maxTime}`;
            }

            fetch('src/event_types.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cancelEditEventType();
                    loadEventTypes();
                    location.reload(); // Reload to update speaker form dropdowns
                } else {
                    alert(data.error || 'Failed to save event type.');
                }
            });
        }

        loadEventTypes();
    </script>
    <script src="js/obs-toggle.js"></script>
    <script src="js/zoom-controls.js"></script>
</body>

</html>