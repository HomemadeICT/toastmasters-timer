<?php
require_once 'src/db.php';

// Handle new meeting creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_meeting'])) {
    $name = $_POST['name'];
    $date = $_POST['date'];
    $location = $_POST['location'];
    $theme = $_POST['theme'];

    $stmt = $conn->prepare("INSERT INTO meetings (name, date, location, theme) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $date, $location, $theme);
    $stmt->execute();
    header("Location: index.php");
    exit;
}

// Fetch meetings
$result = $conn->query("SELECT * FROM meetings ORDER BY date DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toastmasters Timer</title>
    <link rel="icon" type="image/png" href="Toastmasters%20icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=3">
</head>

<body>
    <div class="container">
        <header>
            <h1>Toastmasters Timer</h1>
            <p>Simple. Fast. Reliable.</p>
        </header>

        <section class="card">
            <h2>Create New Meeting</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Meeting Name (e.g. Meeting #111)</label>
                    <input type="text" name="name" required placeholder="Meeting #">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Location / Platform</label>
                        <input type="text" name="location" placeholder="Zoom / Physical">
                    </div>
                </div>
                <div class="form-group">
                    <label>Theme</label>
                    <input type="text" name="theme" placeholder="Meeting Theme">
                </div>
                <button type="submit" name="create_meeting" class="btn btn-primary">Create Meeting</button>
            </form>
        </section>

        <section class="meeting-list">
            <h2>Recent Meetings</h2>
            <div class="grid">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <a href="meeting.php?id=<?php echo $row['id']; ?>" class="meeting-card">
                        <div class="meeting-info">
                            <h3>
                                <?php echo htmlspecialchars($row['name']); ?>
                            </h3>
                            <p class="date">
                                <?php echo date('D, j M Y', strtotime($row['date'])); ?>
                            </p>
                            <?php if ($row['theme']): ?>
                                <p class="theme">"
                                    <?php echo htmlspecialchars($row['theme']); ?>"
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="arrow">→</div>
                    </a>
                <?php endwhile; ?>
            </div>
        </section>
    </div>
    <script src="js/obs-toggle.js"></script>
</body>

</html>