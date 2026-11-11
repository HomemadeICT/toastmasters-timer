<?php
require_once 'src/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : null;

// Handle AJAX Quick Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    $sid = intval($_POST['id']);
    $name = $_POST['name'] ?? '';
    $role = $_POST['role'] ?? '';
    
    $stmt = $conn->prepare("UPDATE speakers SET name = ?, role = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $role, $sid);
    
    header('Content-Type: application/json');
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = $conn->prepare("SELECT s.*, m.name as meeting_name, et.min_time as et_min, et.target_time as et_target, et.max_time as et_max 
    FROM speakers s 
    JOIN meetings m ON s.meeting_id = m.id 
    LEFT JOIN event_types et ON s.event_type_id = et.id 
    WHERE s.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$speaker = $stmt->get_result()->fetch_assoc();

if (!$speaker) {
    die("Speaker not found.");
}

// Find next speaker
$stmt = $conn->prepare("SELECT id FROM speakers WHERE meeting_id = ? AND sort_order > ? ORDER BY sort_order ASC, id ASC LIMIT 1");
$stmt->bind_param("ii", $speaker['meeting_id'], $speaker['sort_order']);
$stmt->execute();
$next_res = $stmt->get_result();
$next_speaker_id = $next_res->num_rows > 0 ? $next_res->fetch_assoc()['id'] : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timer:
        <?php echo htmlspecialchars($speaker['name']); ?>
    </title>
    <link rel="icon" type="image/png" href="Toastmasters%20icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=3">
    <style>
        body {
            transition: background-color 0.5s ease;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            padding: 4rem 2rem 0 2rem;
            margin: 0;
        }

        #timer-display {
            font-size: 12rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            margin-bottom: 2rem;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .speaker-meta {
            text-align: center;
            margin-bottom: 3rem;
            z-index: 10;
        }

        .speaker-meta h1 {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .speaker-meta p {
            font-size: 1.5rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .controls {
            display: flex;
            gap: 1.5rem;
            z-index: 10;
            align-items: center;
        }

        .btn-timer {
            font-size: 1.5rem;
            padding: 1rem 3rem;
            border-radius: 50px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .bg-green {
            background-color: #22c55e !important;
            color: white !important;
        }

        .bg-yellow {
            background-color: #eab308 !important;
            color: white !important;
        }

        .bg-red {
            background-color: #ef4444 !important;
            color: white !important;
        }

        .bg-green #timer-display,
        .bg-yellow #timer-display,
        .bg-red #timer-display {
            color: white;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .bg-green .speaker-meta p,
        .bg-yellow .speaker-meta p,
        .bg-red .speaker-meta p {
            color: rgba(255, 255, 255, 0.8);
        }

        .back-link {
            display: none;
        }

        .btn-nav {
            font-size: 0.9rem;
            text-decoration: none;
            font-weight: 700;
            color: var(--text-muted);
            opacity: 0.6;
            transition: all 0.2s;
            padding: 0.5rem 1rem;
        }

        .btn-nav:hover {
            opacity: 1;
            color: var(--primary);
        }

        .bg-green .btn-nav,
        .bg-yellow .btn-nav,
        .bg-red .btn-nav {
            color: white;
            opacity: 0.8;
        }
        
        body.obs-safe-mode {
            padding-right: 33vw !important;
            padding-top: 10px !important;
            padding-bottom: 10px !important;
            transition: padding-right 0.3s ease;
        }

        /* Transform the floating overlay frame into a native flex stack item */
        body.obs-safe-mode .tm-frame {
            position: relative !important;
            inset: auto !important;
            border: none !important;
            display: flex !important;
            justify-content: center;
            margin-bottom: 15px !important;
        }
        
        body.obs-safe-mode .tm-logo-container {
            position: relative !important;
            top: auto !important; 
            left: auto !important;
        }
        
        body.obs-safe-mode .tm-logo {
            width: 140px !important; 
            height: auto !important;
        }
        
        body.obs-safe-mode .tm-card-label {
            display: none !important;
        }

        body.obs-safe-mode .speaker-meta {
            margin-bottom: 10px !important;
        }

        body.obs-safe-mode #timer-display {
            font-size: clamp(4rem, 18vw, 15rem);
            line-height: 1;
            margin-bottom: 15px !important;
        }

        body.obs-safe-mode .speaker-meta h1 {
            font-size: clamp(1.2rem, 3.5vw, 2rem);
            margin-bottom: 5px;
        }

        body.obs-safe-mode .speaker-meta p {
            font-size: clamp(0.8rem, 1.8vw, 1.2rem);
        }
        
        body.obs-safe-mode .controls {
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }
    </style>
</head>

<body id="timer-body">
    
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
            <?php echo htmlspecialchars($speaker['name']); ?>
        </h1>
        <p id="speaker-role-display">
            <?php echo htmlspecialchars($speaker['role']); ?>
        </p>
    </div>

    <div id="timer-display" class="zoom-timer">00:00</div>

    <div class="controls">
        <a href="meeting.php?id=<?php echo $speaker['meeting_id']; ?>" class="btn-nav">← EXIT</a>
        
        <button id="startBtn" class="btn btn-timer btn-primary">START</button>
        <button id="stopBtn" class="btn btn-timer"
            style="background:var(--secondary); color:white; display:none;">STOP</button>
        <button id="resetBtn" class="btn btn-timer" style="background:var(--gray); color:white;">RESET</button>

        <?php if ($next_speaker_id): ?>
            <a href="timer.php?id=<?php echo $next_speaker_id; ?>" class="btn-nav">NEXT →</a>
        <?php else: ?>
            <div style="width: 60px;"></div>
        <?php endif; ?>
    </div>

    <script>
        const config = {
            speakerId: <?php echo $speaker['id']; ?>,
            role: "<?php echo $speaker['role']; ?>",
            min: <?php echo $speaker['et_min'] ?? $speaker['min_time']; ?>,
            target: <?php echo $speaker['et_target'] ?? $speaker['target_time']; ?>,
            max: <?php echo $speaker['et_max'] ?? $speaker['max_time']; ?>
        };
        
        function toggleQuickEdit() {
            const panel = document.getElementById('quickEditPanel');
            if(panel.style.display === 'none' || panel.style.display === '') {
                panel.style.display = 'block';
            } else {
                panel.style.display = 'none';
            }
        }
        
        function saveQuickEdit() {
            const newName = document.getElementById('editSpeakerName').value;
            const newRole = document.getElementById('editSpeakerRole').value;
            const updateBtn = document.getElementById('quickEditSaveBtn');
            updateBtn.innerText = 'Saving...';
            
            fetch('timer.php?id=' + config.speakerId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_update=1&id=${config.speakerId}&name=${encodeURIComponent(newName)}&role=${encodeURIComponent(newRole)}`
            })
            .then(res => res.json())
            .then(data => {
                updateBtn.innerText = 'Save';
                if(data.success) {
                    document.getElementById('speaker-name-display').innerText = newName;
                    document.getElementById('speaker-role-display').innerText = newRole;
                    toggleQuickEdit();
                } else {
                    alert('Failed to save');
                }
            })
            .catch(err => {
                console.error(err);
                updateBtn.innerText = 'Save';
                alert('Connection error');
            });
        }
    </script>
    <script src="js/timer.js"></script>
    <script src="js/obs-toggle.js"></script>
    <script src="js/zoom-controls.js"></script>
    
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
            <input type="text" id="editSpeakerName" value="<?php echo htmlspecialchars($speaker['name']); ?>" style="width:calc(100% - 16px); border:1px solid #cbd5e1; border-radius:6px; padding:8px; font-family:'Outfit',sans-serif; color: #333;">
        </div>
        <div style="margin-bottom:15px;">
            <label style="display:block; font-size:0.8rem; margin-bottom:5px; color:#64748b; font-weight:600;">Timing Name / Role</label>
            <input type="text" id="editSpeakerRole" value="<?php echo htmlspecialchars($speaker['role']); ?>" style="width:calc(100% - 16px); border:1px solid #cbd5e1; border-radius:6px; padding:8px; font-family:'Outfit',sans-serif; color: #333;">
        </div>
        <div style="display:flex; gap:10px;">
            <button id="quickEditSaveBtn" onclick="saveQuickEdit()" class="btn btn-primary" style="flex:1; padding:8px; font-size:0.9rem; border:none; border-radius:6px;">Save</button>
            <button onclick="toggleQuickEdit()" class="btn" style="flex:1; background:#e2e8f0; color:#334155; padding:8px; font-size:0.9rem; border:none; border-radius:6px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</body>
</html>