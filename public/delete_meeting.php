<?php
require_once __DIR__ . '/../src/bootstrap.php';

$uid = require_login();

$id = (int)($_REQUEST['id'] ?? 0);
$meeting = $id ? get_owned_meeting($id, $uid) : null;
if (!$meeting) {
    abort(404, 'Meeting not found.');
}
$affected = count_meeting_speakers($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    delete_owned_meeting($id, $uid);
    flash('success', 'Deleted "' . $meeting['name'] . '" and its speakers.');
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete meeting - Toastmasters Timer</title>
    <?php echo favicon_links(); ?>
    <link rel="stylesheet" href="<?php echo h(asset('css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/styles.css')); ?>">
</head>
<body>
    <div class="container">
        <section class="card">
                <h2>Delete this meeting?</h2>
                <p><strong><?php echo h($meeting['name']); ?></strong> (<?php echo h(date('j M Y', strtotime($meeting['date']))); ?>) and its <?php echo $affected; ?> speaker<?php echo $affected === 1 ? '' : 's'; ?> will be permanently deleted. This cannot be undone.</p>
                <form method="POST" action="export.php" class="form-actions export-actions">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="ids[]" value="<?php echo (int)$id; ?>">
                    <button type="submit" name="format" value="csv" class="btn btn-secondary">Export CSV first</button>
                    <button type="submit" name="format" value="print" formtarget="_blank" class="btn btn-secondary">Print / PDF first</button>
                </form>
                <form method="POST" class="form-actions">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <button type="submit" class="btn btn-danger">Delete meeting</button>
                    <a href="meeting.php?id=<?php echo (int)$id; ?>" class="btn btn-secondary">Keep it</a>
                </form>
        </section>
    </div>
</body>
</html>
