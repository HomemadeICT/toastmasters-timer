<?php
require_once __DIR__ . '/../src/bootstrap.php';

$uid = require_login();

// Quick edit from the timer screen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'fetch';
    require_csrf();
    $existing = get_owned_speaker((int)($_POST['id'] ?? 0), $uid);
    if (!$existing) {
        abort(404, 'Speaker not found.');
    }
    $name = clean_text($_POST['name'] ?? '');
    $role = clean_text($_POST['role'] ?? '');
    if ($name === '' || $role === '') {
        abort(400, 'Name and role are required.');
    }
    $stmt = db()->prepare("UPDATE speakers SET name = ?, role = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $role, $existing['id']);
    $stmt->execute();
    json_response(['success' => true]);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$speaker = $id ? get_owned_speaker($id, $uid) : null;
if (!$speaker) {
    abort(404, 'Speaker not found.');
}

// Find next speaker
$stmt = db()->prepare("SELECT id FROM speakers WHERE meeting_id = ? AND sort_order > ? ORDER BY sort_order ASC, id ASC LIMIT 1");
$stmt->bind_param("ii", $speaker['meeting_id'], $speaker['sort_order']);
$stmt->execute();
$next_speaker_id = $stmt->get_result()->fetch_column() ?: null;

$timerConfig = [
    'speakerId' => (int)$speaker['id'],
    'role'      => $speaker['role'],
    'min'       => (int)($speaker['et_min'] ?? $speaker['min_time']),
    'target'    => (int)($speaker['et_target'] ?? $speaker['target_time']),
    'max'       => (int)($speaker['et_max'] ?? $speaker['max_time']),
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timer:
        <?php echo h($speaker['name']); ?>
    </title>
    <?php echo favicon_links(); ?>
    <link rel="stylesheet" href="<?php echo h(asset('css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/styles.css')); ?>">
    <meta name="csrf-token" content="<?php echo h(csrf_token()); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/timer.css')); ?>">
</head>

<body id="timer-body" data-config="<?php echo h(js_value($timerConfig)); ?>">
    
    <div class="tm-frame">
        <div class="tm-logo-container zoom-logo">
            <img src="Toastmasters%20icon.png" class="tm-logo" alt="Toastmasters">
            <div class="tm-card-label" id="tm-card-label-text"></div>
        </div>
    </div>
    <style>
        .bg-green #tm-card-label-text::after, body.green #tm-card-label-text::after { content: "GREEN"; }
        .bg-yellow #tm-card-label-text::after, body.yellow #tm-card-label-text::after { content: "YELLOW"; }
        .bg-red #tm-card-label-text::after, body.red #tm-card-label-text::after { content: "RED"; }
    </style>
    <div class="speaker-meta zoom-meta">
        <h1 id="speaker-name-display">
            <?php echo h($speaker['name']); ?>
        </h1>
        <p id="speaker-role-display">
            <?php echo h($speaker['role']); ?>
        </p>
    </div>

    <div id="timer-display" class="zoom-timer">00:00</div>

    <div class="controls">
        <a href="meeting.php?id=<?php echo (int)$speaker['meeting_id']; ?>" class="btn-nav">← EXIT</a>
        
        <button id="startBtn" class="btn btn-timer btn-primary">START</button>
        <button id="stopBtn" class="btn btn-timer"
            style="background:var(--secondary); color:white; display:none;">STOP</button>
        <button id="resetBtn" class="btn btn-timer" style="background:var(--gray); color:white;">RESET</button>

        <?php if ($next_speaker_id): ?>
            <a href="timer.php?id=<?php echo (int)$next_speaker_id; ?>" class="btn-nav">NEXT →</a>
        <?php else: ?>
            <div style="width: 60px;"></div>
        <?php endif; ?>
    </div>

    <script src="<?php echo h(asset('js/app.js')); ?>"></script>
    <script src="<?php echo h(asset('js/timer-page.js')); ?>"></script>
    <script src="<?php echo h(asset('js/timer.js')); ?>"></script>
    <script src="<?php echo h(asset('js/obs-toggle.js')); ?>"></script>
    <script src="<?php echo h(asset('js/zoom-controls.js')); ?>"></script>
    
    <!-- Quick Edit Floating UI -->
    <div id="quickEditBtn" onclick="toggleQuickEdit()" style="position:fixed; bottom:130px; right:20px; background:var(--primary); color:white; width:50px; height:50px; border-radius:25px; display:flex; justify-content:center; align-items:center; cursor:pointer; box-shadow:0 4px 10px rgba(0,0,0,0.2); z-index:100; transition:all 0.3s; font-size: 24px; pointer-events: auto;">
        <svg fill="currentColor" viewBox="0 0 20 20" style="width:20px; height:20px;">
            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
        </svg>
    </div>
    
    <div id="quickEditPanel" style="position:fixed; bottom:190px; right:20px; background:white; padding:20px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2); width:300px; display:none; z-index:100; font-family:'Outfit',sans-serif; text-align: left;">
        <h3 style="margin-top:0; color:var(--primary); margin-bottom:15px; font-size:1.2rem; font-weight:700;">Quick Edit</h3>
        <div style="margin-bottom:10px;">
            <label style="display:block; font-size:0.8rem; margin-bottom:5px; color:#64748b; font-weight:600;">Speaker Name</label>
            <input type="text" id="editSpeakerName" value="<?php echo h($speaker['name']); ?>" style="width:calc(100% - 16px); border:1px solid #cbd5e1; border-radius:6px; padding:8px; font-family:'Outfit',sans-serif; color: #333;">
        </div>
        <div style="margin-bottom:15px;">
            <label style="display:block; font-size:0.8rem; margin-bottom:5px; color:#64748b; font-weight:600;">Timing Name / Role</label>
            <input type="text" id="editSpeakerRole" value="<?php echo h($speaker['role']); ?>" style="width:calc(100% - 16px); border:1px solid #cbd5e1; border-radius:6px; padding:8px; font-family:'Outfit',sans-serif; color: #333;">
        </div>
        <div style="display:flex; gap:10px;">
            <button id="quickEditSaveBtn" onclick="saveQuickEdit()" class="btn btn-primary" style="flex:1; padding:8px; font-size:0.9rem; border:none; border-radius:6px;">Save</button>
            <button onclick="toggleQuickEdit()" class="btn" style="flex:1; background:#e2e8f0; color:#334155; padding:8px; font-size:0.9rem; border:none; border-radius:6px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</body>
</html>