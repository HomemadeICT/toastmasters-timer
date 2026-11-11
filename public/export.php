<?php
require_once __DIR__ . '/../src/bootstrap.php';

$uid = require_login();
require_method('POST');
require_csrf();

$format = $_POST['format'] ?? '';
if (!in_array($format, ['csv', 'print'], true)) {
    abort(400, 'Choose CSV or print.');
}
$data = load_report_data(posted_meeting_ids(), $uid);
if ($data === []) {
    abort(400, 'Select at least one of your meetings to export.');
}

$_SESSION['exported_ids'] = array_values(array_unique(array_merge(
    $_SESSION['exported_ids'] ?? [],
    array_map(fn($d) => (int)$d['meeting']['id'], $data)
)));

if ($format === 'csv') {
    send_csv($data, 'toastmasters-timer-' . date('Y-m-d') . '.csv');
}

$badgeLabels = ['pass' => 'Qualified', 'grace' => 'Qualified (grace)', 'fail' => 'Disqualified'];
$sections = [
    'ps_speaker' => 'Prepared Speeches', 'tt_speaker' => 'Table Topics', 'ps_eval' => 'Prepared Speech Evaluators',
    'tt_eval' => 'Table Topic Evaluators', 'other' => 'Other Roles',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timer reports - <?php echo (int)count($data); ?> meeting<?php echo count($data) === 1 ? '' : 's'; ?></title>
    <?php echo favicon_links(); ?>
    <link rel="stylesheet" href="<?php echo h(asset('css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/report-sheet.css')); ?>">
</head>
<body>
    <div class="toolbar no-print">
        <span>Use <strong>Print</strong>, then choose <strong>Save as PDF</strong> to keep these reports.</span>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <a href="index.php">Back to meetings</a>
    </div>

    <?php foreach ($data as $d): $m = $d['meeting']; ?>
        <section class="sheet">
            <header class="sheet-head">
                <img src="Toastmasters%20icon.png" alt="" width="110">
                <div>
                    <h1>Timing Report</h1>
                    <p class="sheet-title"><?php echo h($m['name']); ?></p>
                    <?php if (!empty($m['theme'])): ?><p class="sheet-sub">Theme: <?php echo h($m['theme']); ?></p><?php endif; ?>
                </div>
            </header>
            <div class="meta">
                <?php foreach (['Date' => date('F j, Y', strtotime($m['date'])), 'Location' => $m['location'], 'Timer' => $m['timer_name'], 'TMOD' => $m['tmod']] as $label => $val):
                    if ($val === null || $val === '') continue; ?>
                    <div><strong><?php echo h($label); ?>:</strong> <?php echo h($val); ?></div>
                <?php endforeach; ?>
            </div>
            <table>
                <thead>
                    <tr><th>No.</th><th class="left">Name</th><th class="left">Role</th><th>Min</th><th>Target</th><th>Max</th><th>Actual</th><th class="left">Result</th></tr>
                </thead>
                <tbody>
                <?php $last = null; foreach ($d['speakers'] as $s):
                    $cls = result_badge_class($s);
                    if ($s['report_category'] !== $last): $last = $s['report_category']; ?>
                        <tr class="section-row"><td colspan="8"><?php echo h($sections[$last] ?? ''); ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <td><?php echo h($s['report_index']); ?></td>
                        <td class="left"><?php echo h($s['name']); ?></td>
                        <td class="left"><?php echo h($s['role']); ?></td>
                        <td><?php echo h(mmss((int)$s['min_time'])); ?></td>
                        <td><?php echo h(mmss((int)$s['target_time'])); ?></td>
                        <td><?php echo h(mmss((int)$s['max_time'])); ?></td>
                        <td class="actual"><?php echo h($s['final_time'] ?: '-'); ?></td>
                        <td class="left"><?php if ($cls): ?><span class="badge badge-<?php echo $cls; ?>"><?php echo h($badgeLabels[$cls]); ?></span>
                            <?php if ($s['diffText'] && $cls !== 'pass'): ?><small><?php echo h($s['diffText']); ?></small><?php endif; ?>
                        <?php else: ?>-<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($d['speakers'] === []): ?><tr><td colspan="8" class="empty">No speakers in this meeting.</td></tr><?php endif; ?>
                </tbody>
            </table>
            <p class="foot">Qualified: within the role's limits. Grace: within 30 seconds of a limit. Disqualified: more than 30 seconds outside (table topics have no lower grace).</p>
        </section>
    <?php endforeach; ?>
</body>
</html>
