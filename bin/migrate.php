<?php
declare(strict_types=1);
// Usage: php bin/migrate.php [--adopt-email=you@example.com]
// Applies sql/migrations/*.sql once each. --adopt-email assigns ownerless meetings to that user.
require __DIR__ . '/../src/bootstrap.php';

$conn = db();
$conn->query("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(64) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$applied = [];
foreach ($conn->query('SELECT version FROM schema_migrations') as $row) {
    $applied[$row['version']] = true;
}

foreach (glob(APP_ROOT . '/sql/migrations/*.sql') as $file) {
    $version = basename($file, '.sql');
    if (isset($applied[$version])) {
        continue;
    }
    echo "Applying $version\n";
    $conn->multi_query(file_get_contents($file));
    while ($conn->more_results() && $conn->next_result()) {
    }
    $stmt = $conn->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
    $stmt->bind_param('s', $version);
    $stmt->execute();
}

$opts = getopt('', ['adopt-email:']);
if (!empty($opts['adopt-email'])) {
    $email = strtolower($opts['adopt-email']);
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $uid = $stmt->get_result()->fetch_column();
    if (!$uid) {
        fwrite(STDERR, "No user $email; register first.\n");
        exit(1);
    }
    $stmt = $conn->prepare('UPDATE meetings SET user_id = ? WHERE user_id IS NULL');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    echo "Adopted {$stmt->affected_rows} meeting(s) for $email\n";
    $conn->query('ALTER TABLE meetings MODIFY user_id INT NOT NULL');
    $conn->query('ALTER TABLE meetings ADD CONSTRAINT fk_meetings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
}
echo "Done.\n";
