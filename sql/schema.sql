-- Fresh install. Existing databases: run `php bin/migrate.php` instead.
-- No CREATE DATABASE / USE: shared hosts give you one pre-made database.

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(64) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    location VARCHAR(255),
    theme VARCHAR(255),
    tmod VARCHAR(255),
    timer_name VARCHAR(255),
    ah_counter VARCHAR(255),
    grammarian VARCHAR(255),
    ge VARCHAR(255),
    rrm VARCHAR(255),
    ttm VARCHAR(255),
    KEY idx_meetings_user_date (user_id, date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    min_time INT, -- seconds
    target_time INT, -- seconds
    max_time INT, -- seconds
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event_per_meeting (meeting_id, name),
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS speakers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(255) NOT NULL,
    event_type_id INT,
    min_time INT, -- seconds
    target_time INT, -- seconds
    max_time INT, -- seconds
    final_time VARCHAR(10), -- e.g. "6:14"
    sort_order INT DEFAULT 0,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    KEY idx_speakers_meeting_order (meeting_id, sort_order),
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (event_type_id) REFERENCES event_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version) VALUES ('001_users_and_tenancy');
