<?php
require_once __DIR__ . '/../src/bootstrap.php';

$uid = require_login();
require_method('POST');
require_csrf();

$ids = posted_meeting_ids();
$data = load_report_data($ids, $uid);
if ($data === []) {
    flash('warning', 'Select at least one meeting first.');
    redirect('index.php');
}
$ownedIds = array_map(fn($d) => (int)$d['meeting']['id'], $data);

if (($_POST['action'] ?? '') === 'delete') {
    $conn = db();
    $conn->begin_transaction();
    try {
        $in = implode(',', array_fill(0, count($ownedIds), '?'));
        $stmt = $conn->prepare("DELETE FROM meetings WHERE user_id = ? AND id IN ($in)");
        $stmt->bind_param('i' . str_repeat('i', count($ownedIds)), $uid, ...$ownedIds);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        throw $e;
    }
    $_SESSION['exported_ids'] = array_values(array_diff($_SESSION['exported_ids'] ?? [], $ownedIds));
    flash('success', ($n === 1 ? 'Deleted 1 meeting.' : "Deleted $n meetings.") . ' Your account and your other meetings are untouched.');
    redirect('index.php');
}

$exported = $_SESSION['exported_ids'] ?? [];
$allExported = array_diff($ownedIds, $exported) === [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review before deleting - Toastmasters Timer</title>
    <?php echo favicon_links(); ?>
    <link rel="stylesheet" href="<?php echo h(asset('css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/styles.css')); ?>">
</head>
<body>
    <div class="container">
        <section class="card">
            <h2>Review before deleting</h2>
            <p>These <?php echo count($data); ?> meeting<?php echo count($data) === 1 ? '' : 's'; ?> and all their speakers and timings will be permanently deleted. This cannot be undone.</p>
            <div class="review-list">
                <?php foreach ($data as $d): $m = $d['meeting'];
                    $timed = count(array_filter($d['speakers'], fn($s) => $s['final_time']));
                    $done = in_array((int)$m['id'], $exported, true); ?>
                    <div class="review-row">
                        <div>
                            <strong><?php echo h($m['name']); ?></strong>
                            <span class="usage-note"><?php echo h(date('j M Y', strtotime($m['date']))); ?> · <?php echo count($d['speakers']); ?> speaker<?php echo count($d['speakers']) === 1 ? '' : 's'; ?>, <?php echo $timed; ?> timed</span>
                        </div>
                        <span class="export-state <?php echo $done ? 'done' : ''; ?>"><?php echo $done ? '✓ Exported' : 'Not exported'; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$allExported): ?>
                <p class="notice notice-warning" role="alert">You haven't exported all of these yet. Download a copy first if you may need the results later.</p>
            <?php endif; ?>

            <form method="POST" action="export.php" class="form-actions export-actions">
                <?php echo csrf_field(); ?>
                <?php foreach ($ownedIds as $i): ?><input type="hidden" name="ids[]" value="<?php echo (int)$i; ?>"><?php endforeach; ?>
                <button type="submit" name="format" value="csv" class="btn btn-secondary">Export CSV</button>
                <button type="submit" name="format" value="print" formtarget="_blank" class="btn btn-secondary">Print / PDF reports</button>
            </form>

            <form method="POST" class="form-actions">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <?php foreach ($ownedIds as $i): ?><input type="hidden" name="ids[]" value="<?php echo (int)$i; ?>"><?php endforeach; ?>
                <button type="submit" class="btn btn-danger">Permanently delete <?php echo count($data); ?> meeting<?php echo count($data) === 1 ? '' : 's'; ?></button>
                <a href="index.php" class="btn btn-secondary">Keep them</a>
            </form>
            <p class="usage-note">Export first if you want a copy, then press delete. The ticks above update the next time you open this review.</p>
        </section>
    </div>
</body>
</html>
