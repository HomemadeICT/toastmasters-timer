<?php
require_once __DIR__ . '/../src/bootstrap.php';

$uid = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_meeting'])) {
    require_csrf();
    $name = clean_text($_POST['name'] ?? '');
    $date = valid_date($_POST['date'] ?? '');
    $location = clean_text($_POST['location'] ?? '');
    $theme = clean_text($_POST['theme'] ?? '');
    if ($name === '' || $date === null) {
        abort(400, 'A meeting name and a valid date are required.');
    }

    $stmt = db()->prepare("INSERT INTO meetings (user_id, name, date, location, theme) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $uid, $name, $date, $location, $theme);
    $stmt->execute();
    redirect("index.php");
}

$stmt = db()->prepare("SELECT * FROM meetings WHERE user_id = ? ORDER BY date DESC");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
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
        <header>
            <h1>Toastmasters Timer</h1>
            <p>Simple. Fast. Reliable.</p>
            <form method="POST" action="logout.php" class="inline-form">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn-link">Sign out</button>
            </form>
        </header>

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

        <section class="meeting-list">
            <h2>Recent Meetings</h2>
            <div class="grid">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <a href="meeting.php?id=<?php echo (int)$row['id']; ?>" class="meeting-card">
                        <div class="meeting-info">
                            <h3><?php echo h($row['name']); ?></h3>
                            <p class="date"><?php echo date('D, j M Y', strtotime($row['date'])); ?></p>
                            <?php if ($row['theme']): ?>
                                <p class="theme">"<?php echo h($row['theme']); ?>"</p>
                            <?php endif; ?>
                        </div>
                        <div class="arrow">→</div>
                    </a>
                <?php endwhile; ?>
            </div>
        </section>
    </div>
    <script src="<?php echo h(asset('js/obs-toggle.js')); ?>"></script>
</body>

</html>
