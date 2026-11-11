<?php
require_once __DIR__ . '/../src/bootstrap.php';

$uid = require_login();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$meeting = $id ? get_owned_meeting($id, $uid) : null;
if (!$meeting) {
    abort(404, 'Meeting not found.');
}

// Handle Master Edit Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report_edits'])) {
    require_csrf();
    $m_name = clean_text($_POST['m_name'] ?? '');
    $m_date = valid_date($_POST['m_date'] ?? '');
    if ($m_name === '' || $m_date === null) {
        abort(400, 'A meeting name and a valid date are required.');
    }
    $m_location = clean_text($_POST['m_location'] ?? '');
    $m_theme = clean_text($_POST['m_theme'] ?? '');
    $m_tmod = clean_text($_POST['m_tmod'] ?? '');
    $m_timer = clean_text($_POST['m_timer'] ?? '');

    $times = [];
    if (isset($_POST['times']) && is_array($_POST['times'])) {
        foreach ($_POST['times'] as $s_id => $f_time) {
            $normalized = normalize_final_time($f_time);
            if ($normalized === null) {
                abort(400, 'Times must look like 7:31 (minutes:seconds).');
            }
            $times[(int)$s_id] = $normalized;
        }
    }

    $conn = db();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE meetings SET name = ?, date = ?, location = ?, theme = ?, tmod = ?, timer_name = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ssssssii", $m_name, $m_date, $m_location, $m_theme, $m_tmod, $m_timer, $id, $uid);
        $stmt->execute();

        $stmt2 = $conn->prepare("UPDATE speakers SET final_time = ?, status = ? WHERE id = ? AND meeting_id = ?");
        foreach ($times as $s_id => $f_time) {
            $status = $f_time !== '' ? 'completed' : 'pending';
            $value = $f_time !== '' ? $f_time : null;
            $stmt2->bind_param("ssii", $value, $status, $s_id, $id);
            $stmt2->execute();
        }
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        throw $e;
    }
    redirect("report.php?id=$id");
}

$stmt = db()->prepare("SELECT * FROM speakers WHERE meeting_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->bind_param("i", $id);
$stmt->execute();
$speakers_result = $stmt->get_result();

$speakerList = prepare_report_speakers($speakers_result->fetch_all(MYSQLI_ASSOC));
$categoryLabels = REPORT_CATEGORY_LABELS;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Timer Report - <?php echo h($meeting['name']); ?></title>
    <?php echo favicon_links(); ?>
    <link rel="stylesheet" href="<?php echo h(asset('css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/styles.css')); ?>">
    <meta name="csrf-token" content="<?php echo h(csrf_token()); ?>">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            padding: 40px;
            color: #333;
        }

        .report-header {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #ccc;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }

        .report-header img {
            width: 140px;
            margin-right: 20px;
            padding-right: 20px;
            border-right: 1px solid #ccc;
        }

        .report-header h1 {
            margin: 0;
            color: #004165;
            font-size: 26px;
            font-weight: bold;
        }

        .report-header p {
            margin: 5px 0 0 0;
            color: #004165;
            font-size: 18px;
            font-weight: 400;
        }

        .meeting-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .meta-box {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border: 1px solid #000;
        }

        th, td {
            border: 1px solid #000;
            padding: 12px;
            text-align: center;
        }

        th {
            background: #f0f4f8;
            color: #000;
            font-weight: bold;
        }

        .no-print {
            margin-bottom: 30px;
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            background: #004165;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
        }

        .print-only { display: none; }
        
        .instructions {
            margin-top: 30px;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }

        .instructions p { margin-bottom: 10px; }

        @media print {
            .no-print, .hide-print, .meeting-meta, #editMeetingForm {
                display: none !important;
            }
            th.print-only, td.print-only { display: table-cell; }
            tr.print-only { display: table-row; }

            body {
                padding: 0;
            }
            
            .report-header h1 { font-size: 22px; }
            .report-header p { font-size: 16px; }

            th {
                background: none;
            }
            th, td { padding: 6px; font-size: 12px; }
            tbody tr:nth-child(even) { background-color: #f0f4f8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            
            .instructions {
                font-size: 9px;
                line-height: 1.2;
            }
            .instructions p { margin-bottom: 6px; }
            
            /* Hide badges by default, show if forced */
            .pres-eval-badge { display: none; }
            body.show-eval-badges .pres-eval-badge { display: inline-block !important; }
            
            /* Strip OBS padding natively on print sheets */
            body.obs-safe-mode { padding-right: 0 !important; }
        }

        /* Presentation Mode mimics print view on screen */
        .pres-eval-badge { 
            display: none; 
            font-size: 11px;
            font-weight: 700;
            margin-left: 8px;
        }
        body.show-eval-badges .pres-eval-badge {
            display: inline-block !important;
        }
        
        /* Override global OBS shift specifically for this dense report table if needed */
        body.obs-safe-mode {
            padding-right: 40vw !important;
        }
        
        .pres-eval-badge.pass { color: #10b981; }
        .pres-eval-badge.fail { color: #ef4444; }
        .pres-eval-badge.grace { color: #f59e0b; }
        
        .grace-range {
            font-size: 10px;
            color: #9ca3af;
            display: block;
            margin-top: 2px;
        }
        body.presentation-mode .hide-print,
        body.presentation-mode .meeting-meta,
        body.presentation-mode #editMeetingForm {
            display: none !important;
        }

        body.presentation-mode th.print-only, body.presentation-mode td.print-only {
            display: table-cell;
        }
        body.presentation-mode tr.print-only {
            display: table-row;
        }
        body.presentation-mode tbody tr:nth-child(even) { background-color: #f0f4f8; }
        
        body.presentation-mode .report-header h1 { font-size: 24px; }
        body.presentation-mode .report-header p { font-size: 18px; }
        body.presentation-mode th, body.presentation-mode td {
            font-size: 14px;
            padding: 8px;
        }

        /* ── Speaker Visibility Toggle ─────────────────────────── */
        .toggle-col {
            width: 36px;
            padding: 4px !important;
            text-align: center;
        }
        .vis-toggle-btn {
            background: none;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            cursor: pointer;
            padding: 4px 6px;
            font-size: 16px;
            line-height: 1;
            transition: all 0.2s ease;
            color: #334155;
        }
        .vis-toggle-btn:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
        }
        .vis-toggle-btn.hidden-speaker {
            opacity: 0.4;
            color: #94a3b8;
        }
        tr.speaker-hidden td:not(.toggle-col) {
            opacity: 0.15;
            text-decoration: line-through;
            transition: opacity 0.25s ease;
        }
        /* Completely hide rows in print when hidden */
        @media print {
            tr.speaker-hidden { display: none !important; }
            .toggle-col, th.toggle-col { display: none !important; }
        }
        /* Also hide toggle col in presentation mode */
        body.presentation-mode .toggle-col,
        body.presentation-mode th.toggle-col {
            display: none !important;
        }
        body.presentation-mode tr.speaker-hidden { display: none !important; }

        /* Bulk toggle dropdown */
        .vis-dropdown {
            position: relative;
            display: inline-block;
        }
        .vis-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            border-radius: 8px;
            z-index: 100;
            padding: 6px 0;
            margin-top: 4px;
            border: 1px solid #e2e8f0;
        }
        .vis-dropdown-content.show { display: block; }
        .vis-dropdown-content button {
            display: block;
            width: 100%;
            text-align: left;
            padding: 8px 16px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 13px;
            color: #334155;
            font-family: inherit;
        }
        .vis-dropdown-content button:hover {
            background: #f1f5f9;
        }
        .vis-dropdown-content hr {
            margin: 4px 12px;
            border: none;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>

<body class="zoom-global">
    <div class="no-print">
        <a href="meeting.php?id=<?php echo $id; ?>" class="btn" style="background:#6b7280;">Back</a>
        <button type="button" class="btn" style="background:#8b5cf6;" onclick="document.body.classList.toggle('presentation-mode'); this.innerText = document.body.classList.contains('presentation-mode') ? 'Exit Presentation Mode' : 'Present Official View';">Present Official View</button>
        <button type="button" class="btn" style="background:#f43f5e;" onclick="document.body.classList.toggle('show-eval-badges'); this.innerText = document.body.classList.contains('show-eval-badges') ? 'Hide Eval Badges' : 'Show Eval Badges';">Show Eval Badges</button>
        <div class="vis-dropdown" style="display:inline-block;">
            <button type="button" class="btn" style="background:#0ea5e9;" onclick="toggleVisDropdown()">&#128065; Toggle Speakers</button>
            <div class="vis-dropdown-content" id="visDropdown">
                <button type="button" onclick="bulkToggle('show')">&#128065; Show All Speakers</button>
                <button type="button" onclick="bulkToggle('hide')">&#128064; Hide All Speakers</button>
                <hr>
                <button type="button" onclick="bulkToggleCategory('ps_speaker','show')">Show Prepared Speeches</button>
                <button type="button" onclick="bulkToggleCategory('ps_speaker','hide')">Hide Prepared Speeches</button>
                <hr>
                <button type="button" onclick="bulkToggleCategory('tt_speaker','show')">Show Table Topics</button>
                <button type="button" onclick="bulkToggleCategory('tt_speaker','hide')">Hide Table Topics</button>
                <hr>
                <button type="button" onclick="bulkToggleCategory('ps_eval','show')">Show PS Evaluators</button>
                <button type="button" onclick="bulkToggleCategory('ps_eval','hide')">Hide PS Evaluators</button>
                <hr>
                <button type="button" onclick="bulkToggleCategory('tt_eval','show')">Show TT Evaluators</button>
                <button type="button" onclick="bulkToggleCategory('tt_eval','hide')">Hide TT Evaluators</button>
                <hr>
                <button type="button" onclick="bulkToggleCategory('other','show')">Show Other Roles</button>
                <button type="button" onclick="bulkToggleCategory('other','hide')">Hide Other Roles</button>
            </div>
        </div>
        <button type="button" class="btn" style="background:#f59e0b;" id="toggleEditBtn" onclick="toggleEditMode()">Edit Report</button>
        <button type="submit" form="masterEditForm" name="save_report_edits" id="saveReportBtn" class="btn" style="background:#10b981; display:none;">Save All Edits</button>
        <button onclick="window.print();" class="btn">Print A4 Report</button>
    </div>

    <div class="report-header">
        <img src="Toastmasters%20icon.png" alt="Toastmasters International">
        <div class="report-header-text">
            <h1>Speech Contest Time Record Sheet</h1>
            <p>and Instructions for Timers</p>
            <p style="font-size: 14px; margin-top: 5px; font-weight: normal; color: #555; line-height: 1.4;">
                <strong><?php echo h($meeting['name'] ?? ''); ?></strong>
                <?php if (!empty($meeting['theme'])): ?>
                    <br>Meeting Theme : <?php echo h($meeting['theme']); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <form method="POST" id="masterEditForm">
    <?php echo csrf_field(); ?>
    <div class="meeting-meta" id="meetingMetaDisplay">
        <div class="meta-box">
            <strong>Meeting:</strong> <?php echo h($meeting['name'] ?? ''); ?><br>
            <strong>Date:</strong> <?php echo date('F j, Y', strtotime($meeting['date'])); ?><br>
            <strong>Theme:</strong> <?php echo h($meeting['theme'] ?? ''); ?>
        </div>
        <div class="meta-box">
            <strong>Timer:</strong> <?php echo h($meeting['timer_name'] ?? ''); ?><br>
            <strong>TMOD:</strong> <?php echo h($meeting['tmod'] ?? ''); ?><br>
            <strong>Location:</strong> <?php echo h($meeting['location'] ?? ''); ?>
        </div>
    </div>

    <div id="editMeetingForm" style="display:none; margin-bottom: 30px;" class="no-print meta-box">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                <label style="display:block; margin-bottom:5px;"><strong>Meeting Name</strong></label>
                <input type="text" name="m_name" value="<?php echo h($meeting['name'] ?? ''); ?>" required style="width:calc(100% - 16px); padding:8px; border:1px solid #ccc; border-radius:4px; margin-bottom:10px;">
                
                <label style="display:block; margin-bottom:5px;"><strong>Date</strong></label>
                <input type="date" name="m_date" value="<?php echo h($meeting['date']); ?>" required style="width:calc(100% - 16px); padding:8px; border:1px solid #ccc; border-radius:4px; margin-bottom:10px;">
                
                <label style="display:block; margin-bottom:5px;"><strong>Theme</strong></label>
                <input type="text" name="m_theme" value="<?php echo h($meeting['theme'] ?? ''); ?>" style="width:calc(100% - 16px); padding:8px; border:1px solid #ccc; border-radius:4px; margin-bottom:10px;">
            </div>
            <div>
                <label style="display:block; margin-bottom:5px;"><strong>Timer</strong></label>
                <input type="text" name="m_timer" value="<?php echo h($meeting['timer_name'] ?? ''); ?>" style="width:calc(100% - 16px); padding:8px; border:1px solid #ccc; border-radius:4px; margin-bottom:10px;">
                
                <label style="display:block; margin-bottom:5px;"><strong>TMOD</strong></label>
                <input type="text" name="m_tmod" value="<?php echo h($meeting['tmod'] ?? ''); ?>" style="width:calc(100% - 16px); padding:8px; border:1px solid #ccc; border-radius:4px; margin-bottom:10px;">
                
                <label style="display:block; margin-bottom:5px;"><strong>Location</strong></label>
                <input type="text" name="m_location" value="<?php echo h($meeting['location'] ?? ''); ?>" style="width:calc(100% - 16px); padding:8px; border:1px solid #ccc; border-radius:4px; margin-bottom:10px;">
            </div>
            <div style="grid-column: 1 / -1; display: flex; gap: 10px;">
                <button type="button" class="btn" style="background:#6b7280;" onclick="toggleEditMode()">Cancel Edit</button>
            </div>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th rowspan="2" class="toggle-col hide-print" style="width:36px;"></th>
                <th rowspan="2" style="text-align: center; width: 60%;">Contestant</th>
                <th colspan="2" class="print-only">Presentation Time</th>
                
                <th rowspan="2" class="hide-print">Role / Project</th>
                <th rowspan="2" class="hide-print">Min Time</th>
                <th rowspan="2" class="hide-print">Target</th>
                <th rowspan="2" class="hide-print">Max Time</th>
                <th rowspan="2" class="hide-print">Actual Time</th>
                <th rowspan="2" class="hide-print">Effective Range<br><small style="font-weight:normal;color:#666;">(with 30s buffer)</small></th>
                <th rowspan="2" class="hide-print">Status</th>
            </tr>
            <tr class="print-only">
                <th style="width: 20%;">Minutes</th>
                <th style="width: 20%;">Seconds</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Section metadata for group/sub-section headers
            $sectionMeta = [
                'ps_speaker' => ['heading' => 'Prepared Speeches',          'group' => 'speakers', 'group_title' => 'SPEAKERS REPORT'],
                'tt_speaker' => ['heading' => 'Table Topics',               'group' => 'speakers', 'group_title' => 'SPEAKERS REPORT'],
                'ps_eval'    => ['heading' => 'Prepared Speech Evaluators', 'group' => 'evals',    'group_title' => 'EVALUATIONS REPORT'],
                'tt_eval'    => ['heading' => 'Table Topic Evaluators',     'group' => 'evals',    'group_title' => 'EVALUATIONS REPORT'],
                'other'      => ['heading' => 'Other Roles',                'group' => 'other',    'group_title' => 'OTHER ROLES'],
            ];
            $lastCategory = null;
            $lastGroup    = null;
            $speakerCount = 0;

            foreach ($speakerList as $s):
                $speakerCount++;
                $cat       = $s['report_category'];
                $meta      = $sectionMeta[$cat];
                $idx       = $s['report_index']; // e.g. "SP1", "TT2"
                $actualSec = $s['actualSec'];
                $style     = $s['is_disqualified'] ? 'color: #ef4444;' : '';

                $traditionalDiffText = '';
                if ($s['final_time'] && $s['role'] !== 'Round Robin') {
                    if ($actualSec < $s['min_time'])
                        $traditionalDiffText = " (-" . mmss($s['min_time'] - $actualSec) . ")";
                    elseif ($actualSec > $s['max_time'])
                        $traditionalDiffText = " (+" . mmss($actualSec - $s['max_time']) . ")";
                }

                if ($s['is_disqualified'])                                   $badgeClass = 'fail';
                elseif ($s['final_time'] && $s['role'] !== 'Round Robin')    $badgeClass = 'pass';
                else                                                          $badgeClass = '';

                $minPart = ''; $secPart = '';
                if ($s['final_time']) {
                    $p = explode(':', str_replace('.', ':', $s['final_time']));
                    $minPart = $p[0]; $secPart = isset($p[1]) ? $p[1] : '00';
                }
            ?>

            <?php if ($cat !== $lastCategory): ?>
                <?php if ($meta['group'] !== $lastGroup): /* ── Main group banner ── */ ?>
                <tr style="background:#004165; color:#fff;">
                    <td class="print-only" colspan="3"
                        style="padding:7px 12px; font-weight:700; font-size:12px;
                               text-align:left; letter-spacing:0.6px;">
                        &#9654; <?php echo $meta['group_title']; ?>
                    </td>
                    <td class="hide-print toggle-col" style="background:#004165;"></td>
                    <td class="hide-print" colspan="8"
                        style="padding:7px 12px; font-weight:700; font-size:13px;
                               text-align:left; letter-spacing:0.6px;">
                        &#9654; <?php echo $meta['group_title']; ?>
                    </td>
                </tr>
                <?php endif; /* ── Sub-section row ── */ ?>
                <tr style="background:#ddeaf5; color:#003050;">
                    <td class="print-only" colspan="3"
                        style="padding:4px 14px; font-size:10px; font-weight:600;
                               text-align:left; border-left:3px solid #004165;">
                        <?php echo $meta['heading']; ?>
                        <span style="font-weight:400; color:#555; margin-left:5px;">
                            (<?php echo $categoryLabels[$cat] ?: '—'; ?>)
                        </span>
                    </td>
                    <td class="hide-print toggle-col" style="background:#ddeaf5;"></td>
                    <td class="hide-print" colspan="8"
                        style="padding:4px 14px; font-size:12px; font-weight:600;
                               text-align:left; border-left:3px solid #004165;">
                        <?php echo $meta['heading']; ?>
                        <span style="font-weight:400; color:#555; margin-left:6px;">
                            (<?php echo $categoryLabels[$cat] ?: '—'; ?>)
                        </span>
                    </td>
                </tr>
            <?php endif;
            $lastCategory = $cat;
            $lastGroup    = $meta['group']; ?>

                <tr class="speaker-row" data-speaker-id="<?php echo (int)$s['id']; ?>" data-category="<?php echo h($cat); ?>">
                    <td class="toggle-col hide-print">
                        <button type="button" class="vis-toggle-btn" onclick="toggleSpeakerVis(this)" title="Toggle visibility">
                            &#128065;
                        </button>
                    </td>
                    <td style="text-align: left;">
                        <?php if ($idx): ?>
                            <span style="font-weight:700; color:#004165; font-size:11px;
                                         background:#e0eaf5; padding:1px 6px;
                                         border-radius:4px; border:1px solid #b0cde8;
                                         display:inline-block; margin-right:5px;">
                                <?php echo h($idx); ?>
                            </span>
                        <?php endif; ?>
                        <?php echo h($s['name']); ?>
                        <?php if ($badgeClass): ?>
                            <span class="pres-eval-badge <?php echo $badgeClass; ?>">
                                <?php echo h($s['diffText']); ?>
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- Print columns -->
                    <td class="print-only"><?php echo $minPart !== '' ? (int)$minPart : ''; ?></td>
                    <td class="print-only"><?php echo h($secPart); ?></td>

                    <!-- Screen columns -->
                    <td class="hide-print"><?php echo h($s['role']); ?></td>
                    <td class="hide-print"><?php echo floor($s['min_time']/60) . ':' . str_pad($s['min_time']%60,2,'0',STR_PAD_LEFT); ?></td>
                    <td class="hide-print"><?php echo floor($s['target_time']/60) . ':' . str_pad($s['target_time']%60,2,'0',STR_PAD_LEFT); ?></td>
                    <td class="hide-print"><?php echo floor($s['max_time']/60) . ':' . str_pad($s['max_time']%60,2,'0',STR_PAD_LEFT); ?></td>
                    <td class="hide-print" style="font-weight:bold; <?php echo $style; ?>">
                        <span class="display-time">
                            <?php echo h($s['final_time'] ?: '-'); ?>
                            <small style="font-weight:normal;"><?php echo h($traditionalDiffText); ?></small>
                        </span>
                        <input type="text" name="times[<?php echo (int)$s['id']; ?>]"
                               value="<?php echo h($s['final_time'] ?? ''); ?>"
                               placeholder="MM:SS" class="edit-time-input"
                               style="display:none; width:60px; padding:4px; border:1px solid #ccc;
                                      border-radius:4px; text-align:center;">
                    </td>
                    <td class="hide-print" style="font-size:12px;">
                        <?php if ($s['role'] !== 'Round Robin' && isset($s['effective_lower'])): ?>
                            <?php echo floor($s['effective_lower']/60) . ':' . str_pad($s['effective_lower']%60,2,'0',STR_PAD_LEFT); ?>
                            &mdash;
                            <?php echo floor($s['effective_upper']/60) . ':' . str_pad($s['effective_upper']%60,2,'0',STR_PAD_LEFT); ?>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td class="hide-print" style="font-weight:bold;">
                        <?php if ($s['final_time'] && $s['role'] !== 'Round Robin'): ?>
                            <?php if ($s['is_disqualified']): ?>
                                <span style="color:#ef4444;">&#10060; DQ</span>
                                <small style="display:block;font-weight:normal;color:#ef4444;"><?php echo h($s['diffText']); ?></small>
                            <?php elseif ($s['grace_status'] === 'grace'): ?>
                                <span style="color:#10b981;">&#9989; QUALIFIED</span>
                                <small style="display:block;font-weight:normal;color:#f59e0b;">(30s buffer)</small>
                            <?php else: ?>
                                <span style="color:#10b981;">&#9989; OK</span>
                            <?php endif; ?>
                        <?php elseif ($s['role'] === 'Round Robin'): ?>
                            <span style="color:#9ca3af;">N/A</span>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <!-- Filler rows for print sheet -->
            <?php for ($i = 0; $i < max(0, 10 - $speakerCount); $i++): ?>
                <tr class="print-only"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
        </tbody>
    </table>
    </form>
    
    <script>
        function toggleEditMode() {
            const isEditing = document.getElementById('saveReportBtn').style.display === 'block';
            document.getElementById('saveReportBtn').style.display = isEditing ? 'none' : 'block';
            
            // Toggle meta block vs form block
            document.getElementById('meetingMetaDisplay').style.display = isEditing ? 'grid' : 'none';
            document.getElementById('editMeetingForm').style.display = isEditing ? 'block' : 'none';
            
            // Toggle times text vs inputs
            document.querySelectorAll('.display-time').forEach(el => el.style.display = isEditing ? 'inline' : 'none');
            document.querySelectorAll('.edit-time-input').forEach(el => el.style.display = isEditing ? 'none' : 'inline-block');
        }

        // ── Speaker Visibility Toggle ─────────────────────────────────
        const STORAGE_KEY = 'report_hidden_speakers_<?php echo $id; ?>';

        function getHiddenSet() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                return raw ? new Set(JSON.parse(raw)) : new Set();
            } catch(e) { return new Set(); }
        }

        function saveHiddenSet(set) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify([...set]));
        }

        function applyVisibility() {
            const hidden = getHiddenSet();
            document.querySelectorAll('tr.speaker-row').forEach(tr => {
                const sid = tr.dataset.speakerId;
                const btn = tr.querySelector('.vis-toggle-btn');
                if (hidden.has(sid)) {
                    tr.classList.add('speaker-hidden');
                    btn.classList.add('hidden-speaker');
                    btn.innerHTML = '&#128064;'; // closed eye
                    btn.title = 'Show speaker';
                } else {
                    tr.classList.remove('speaker-hidden');
                    btn.classList.remove('hidden-speaker');
                    btn.innerHTML = '&#128065;'; // open eye
                    btn.title = 'Hide speaker';
                }
            });
        }

        function toggleSpeakerVis(btn) {
            const tr = btn.closest('tr.speaker-row');
            const sid = tr.dataset.speakerId;
            const hidden = getHiddenSet();
            if (hidden.has(sid)) {
                hidden.delete(sid);
            } else {
                hidden.add(sid);
            }
            saveHiddenSet(hidden);
            applyVisibility();
        }

        function bulkToggle(action) {
            const hidden = action === 'hide' ? new Set() : new Set();
            if (action === 'hide') {
                document.querySelectorAll('tr.speaker-row').forEach(tr => {
                    hidden.add(tr.dataset.speakerId);
                });
            }
            saveHiddenSet(hidden);
            applyVisibility();
            closeVisDropdown();
        }

        function bulkToggleCategory(cat, action) {
            const hidden = getHiddenSet();
            document.querySelectorAll(`tr.speaker-row[data-category="${cat}"]`).forEach(tr => {
                if (action === 'hide') {
                    hidden.add(tr.dataset.speakerId);
                } else {
                    hidden.delete(tr.dataset.speakerId);
                }
            });
            saveHiddenSet(hidden);
            applyVisibility();
            closeVisDropdown();
        }

        function toggleVisDropdown() {
            document.getElementById('visDropdown').classList.toggle('show');
        }
        function closeVisDropdown() {
            document.getElementById('visDropdown').classList.remove('show');
        }
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.vis-dropdown')) {
                closeVisDropdown();
            }
        });

        // Apply saved visibility on page load
        applyVisibility();
    </script>

    <div class="instructions">
        <p>Two timers, appointed by the chief judge keep the official record of the time of each contestant's speech. One timer is provided with a stopwatch and the other with a signaling device that displays green, yellow, and red colors.</p>
        <p>Timers shall provide warning signals to the contestants, which shall be clearly visible to the speakers but not obvious to the audience. Any visually impaired contestant is permitted to request and must be granted a form of warning signal of their own choosing. If any special device and/or specific instructions for such signal is/are required, the contestant must provide same.</p>
        <p>Any contestant is disqualified whose speech time is less than the minimum or more than the maximum time indicated below. In all speech contests, no signal shall be given for the overtime period. In the event of technical failure of the signal or timing equipment, a speaker is allowed 30 seconds extra overtime before being disqualified.</p>
        <p>Timing begins with the contestant's first definite verbal or nonverbal communication with the audience. This usually will be the first word uttered by the contestant, but would include any other communication such as sound effects, a staged act by another person, etc.</p>
        <p>All speeches delivered by contestants must conform to the timing guidelines for the contest.</p>
        <p><strong>International, Humorous, and Online</strong> speeches shall be from five to seven minutes. A contestant will be disqualified if the speech is less than four minutes 30 seconds or more than seven minutes 30 seconds. A green signal will be displayed at five minutes and remain displayed for one minute. A yellow signal will be displayed at six minutes and remain displayed for one minute. A red signal will be displayed at seven minutes and will remain on until the conclusion of the speech.</p>
        <p><strong>Table Topics®</strong> contest speeches shall be from one minute to two minutes. A contestant will be disqualified if the speech is less than one minute or more than two minutes 30 seconds. The green signal will be displayed at one minute and remain displayed for 30 seconds. The yellow signal will be displayed at one minute 30 seconds and remain displayed for 30 seconds. The red signal will be displayed at two minutes and remain displayed until the speech is concluded.</p>
        <p><strong>Evaluation</strong> contest speeches shall be from two to three minutes. A contestant will be disqualified if the speech is less than one minute 30 seconds or more than three minutes 30 seconds. The green signal will be displayed at two minutes and remain displayed for 30 seconds. The yellow signal will be displayed at two minutes 30 seconds and remain displayed for 30 seconds. The red signal will be displayed at three minutes and remain displayed until the evaluation is concluded.</p>
        <p><strong>Tall Tales</strong> speeches shall be from three to five minutes. A contestant will be disqualified if the speech is less than two minutes 30 seconds or more than five minutes 30 seconds. The green signal will be displayed at three minutes and remain displayed for one minute. The yellow signal will be displayed at four minutes and remain displayed for one minute. The red signal will be displayed at five minutes and remain displayed until the speech is concluded.</p>
        <div style="margin-top: 20px; font-size: 9px; color: #666; border-top: 1px solid #ccc; padding-top: 10px;">
            ©2017 Toastmasters International. All rights reserved. Toastmasters International, the Toastmasters International logo, and all other Toastmasters International trademarks and copyrights are the sole property of Toastmasters International and may be used only by permission. Any illegal reproduction of this content will result in immediate legal action.<br>
            Item 1175 Rev. 10/2024
        </div>
    </div>
    <script src="<?php echo h(asset('js/obs-toggle.js')); ?>"></script>
    <script src="<?php echo h(asset('js/zoom-controls.js')); ?>"></script>
</body>
</html>