-- Upgrades a pre-auth database. Existing meetings are assigned to the first user
-- who registers after this runs (see bin/migrate.php --adopt-email).
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(254) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_ip (ip, attempted_at),
    KEY idx_attempts_email (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE meetings ADD COLUMN user_id INT NULL AFTER id;
ALTER TABLE meetings ADD KEY idx_meetings_user_date (user_id, date);
ALTER TABLE speakers ADD KEY idx_speakers_meeting_order (meeting_id, sort_order);
ALTER TABLE meetings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE event_types CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE speakers CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
