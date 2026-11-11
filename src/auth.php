<?php
declare(strict_types=1);

const LOGIN_MAX_FAILURES = 5;
const LOGIN_WINDOW_MINUTES = 15;

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function require_login(): int
{
    $uid = current_user_id();
    if ($uid === null) {
        if (is_json_request()) {
            json_response(['success' => false, 'error' => 'Not signed in.'], 401);
        }
        redirect('login.php');
    }
    return $uid;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

function login_is_throttled(string $email): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE) AND (ip = ? OR email = ?)'
    );
    $ip = client_ip();
    $stmt->bind_param('ss', $ip, $email);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count >= LOGIN_MAX_FAILURES;
}

function record_login_failure(string $email): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (ip, email) VALUES (?, ?)');
    $ip = client_ip();
    $stmt->bind_param('ss', $ip, $email);
    $stmt->execute();
    $stmt->close();
}

function clear_login_failures(string $email): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
}

function get_owned_meeting(int $id, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM meetings WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function get_owned_speaker(int $id, int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT s.*, et.min_time AS et_min, et.target_time AS et_target, et.max_time AS et_max
         FROM speakers s
         JOIN meetings m ON s.meeting_id = m.id
         LEFT JOIN event_types et ON s.event_type_id = et.id
         WHERE s.id = ? AND m.user_id = ?'
    );
    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function get_owned_event_type(int $id, int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT et.* FROM event_types et JOIN meetings m ON et.meeting_id = m.id WHERE et.id = ? AND m.user_id = ?'
    );
    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}
