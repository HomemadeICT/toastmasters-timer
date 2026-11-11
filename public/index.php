<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (current_user_id() === null) {
    require __DIR__ . '/../src/landing.php';
    exit;
}
$uid = require_login();
run_maintenance_if_due();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_meeting'])) {
    require_csrf();
    $name = clean_text($_POST['name'] ?? '');
    $date = valid_date($_POST['date'] ?? '');
    $location = clean_text($_POST['location'] ?? '');
    $theme = clean_text($_POST['theme'] ?? '');
    if ($name === '' || $date === null) {
        abort(400, 'A meeting name and a valid date are required.');
    }

    $conn = db();
    $conn->begin_transaction();
    try {
        // Lock the user row so two parallel requests cannot both pass the cap check.
        $lock = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $lock->bind_param('i', $uid);
        $lock->execute();
        $lock->get_result();
        if (count_user_meetings($uid) >= max_meetings()) {
            $conn->rollback();
            flash('danger', 'You have reached the limit of ' . max_meetings() . ' meetings. Delete meetings you no longer need to create a new one.');
            redirect('index.php');
        }
        $stmt = $conn->prepare("INSERT INTO meetings (user_id, name, date, location, theme) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $uid, $name, $date, $location, $theme);
        $stmt->execute();
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        throw $e;
    }
    redirect("index.php");
}

$stmt = db()->prepare("SELECT * FROM meetings WHERE user_id = ? ORDER BY date DESC");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$meetingCount = $result->num_rows;
$maxMeetings = max_meetings();
$usage = usage_state($meetingCount, $maxMeetings);
$select = $_GET['select'] ?? '';
$expiringCount = 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toastmasters Timer</title>
    <?php echo favicon_links(); ?>
    <link rel="stylesheet" href="<?php echo h(asset('css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/styles.css')); ?>">
</head>

<body>
    <div class="container">
        <?php render_flash(); ?>
        <header>
            <h1>Toastmasters Timer</h1>
            <p>Simple. Fast. Reliable.</p>
            <form method="POST" action="logout.php" class="inline-form">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn-link">Sign out</button>
            </form>
        </header>

        <section class="card usage usage-<?php echo $usage; ?>" aria-label="Your storage">
            <div class="usage-head">
                <h2>Your meetings</h2>
                <span class="usage-count"><?php echo $meetingCount; ?> of <?php echo $maxMeetings; ?> used</span>
            </div>
            <div class="usage-bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo $maxMeetings; ?>" aria-valuenow="<?php echo $meetingCount; ?>">
                <span style="width: <?php echo min(100, (int)round($meetingCount / $maxMeetings * 100)); ?>%"></span>
            </div>
            <p class="usage-note">Meetings are kept for <?php echo retention_days(); ?> days after their date, then removed automatically. Your account is never deleted.</p>
            <?php if ($usage === 'warn'): ?>
                <p class="usage-alert">You are close to your limit of <?php echo $maxMeetings; ?> meetings. <a href="index.php?select=old">Review old meetings</a> to make room.</p>
            <?php elseif ($usage === 'full'): ?>
                <p class="usage-alert">You have reached the limit of <?php echo $maxMeetings; ?> meetings. Delete meetings you no longer need to create a new one.
                    <a href="index.php?select=old" class="btn btn-danger">Review old meetings</a></p>
            <?php endif; ?>
        </section>

        <?php if ($usage !== 'full'): ?>
        <section class="card">
            <h2>Create New Meeting</h2>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label>Meeting Name (e.g. Meeting #111)</label>
                    <input type="text" name="name" required maxlength="255" placeholder="Meeting #">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Location / Platform</label>
                        <input type="text" name="location" maxlength="255" placeholder="Zoom / Physical">
                    </div>
                </div>
                <div class="form-group">
                    <label>Theme</label>
                    <input type="text" name="theme" maxlength="255" placeholder="Meeting Theme">
                </div>
                <button type="submit" name="create_meeting" class="btn btn-primary">Create Meeting</button>
            </form>
        </section>
        <?php endif; ?>

        <?php
        $meetings = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($meetings as &$mt) {
            $mt['left'] = days_until(meeting_expires_on($mt['date']));
            $mt['old'] = days_until($mt['date']) < -14;
            if ($mt['left'] <= 7) {
                $expiringCount++;
            }
        }
        unset($mt);
        ?>
        <?php if ($expiringCount > 0): ?>
            <div class="notice notice-warning" role="alert">
                <?php echo $expiringCount; ?> meeting<?php echo $expiringCount === 1 ? ' is' : 's are'; ?> due to be removed within 7 days.
                Export <?php echo $expiringCount === 1 ? 'it' : 'them'; ?> to keep a copy. <a href="index.php?select=expiring">Select <?php echo $expiringCount === 1 ? 'it' : 'them'; ?></a>
            </div>
        <?php endif; ?>

        <section class="meeting-list">
            <h2>Recent Meetings</h2>
            <form method="POST" action="manage_meetings.php" id="select-form">
                <?php echo csrf_field(); ?>
                <?php if ($meetings): ?>
                <div class="select-tools">
                    <button type="button" class="btn-link" data-select="all">Select all</button>
                    <button type="button" class="btn-link" data-select="none">Clear</button>
                    <button type="button" class="btn-link" data-select="old">Older than 14 days</button>
                    <button type="button" class="btn-link" data-select="expiring">Expiring soon</button>
                </div>
                <?php endif; ?>
                <div class="grid">
                    <?php foreach ($meetings as $row):
                        $expires = meeting_expires_on($row['date']);
                        $left = $row['left'];
                        $pre = ($select === 'old' && $row['old']) || ($select === 'expiring' && $left <= 7);
                    ?>
                        <div class="meeting-card-wrap">
                            <label class="pick" title="Select <?php echo h($row['name']); ?>">
                                <input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" data-old="<?php echo $row['old'] ? 1 : 0; ?>" data-expiring="<?php echo $left <= 7 ? 1 : 0; ?>"<?php echo $pre ? ' checked' : ''; ?>>
                                <span class="sr-only">Select <?php echo h($row['name']); ?></span>
                            </label>
                            <a href="meeting.php?id=<?php echo (int)$row['id']; ?>" class="meeting-card">
                                <div class="meeting-info">
                                    <h3><?php echo h($row['name']); ?></h3>
                                    <p class="date"><?php echo date('D, j M Y', strtotime($row['date'])); ?></p>
                                    <?php if ($row['theme']): ?>
                                        <p class="theme">"<?php echo h($row['theme']); ?>"</p>
                                    <?php endif; ?>
                                    <p class="expiry<?php echo $left <= 7 ? ' expiry-soon' : ''; ?>"><?php echo $left <= 0 ? 'Removed soon' : 'Removed on ' . h(date('j M Y', strtotime($expires))); ?></p>
                                </div>
                                <div class="arrow">→</div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="action-bar" id="action-bar" hidden>
                    <span id="selected-count">0 selected</span>
                    <button type="submit" formaction="export.php" name="format" value="csv" class="btn btn-secondary">Export CSV</button>
                    <button type="submit" formaction="export.php" formtarget="_blank" name="format" value="print" class="btn btn-secondary">Print / PDF</button>
                    <button type="submit" class="btn btn-danger">Delete selected…</button>
                </div>
            </form>
        </section>
    </div>
    <script src="<?php echo h(asset('js/obs-toggle.js')); ?>"></script>
    <script src="<?php echo h(asset('js/select-meetings.js')); ?>"></script>
</body>

</html>
