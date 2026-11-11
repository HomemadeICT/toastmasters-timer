<?php
declare(strict_types=1);

/** Removes expired meetings and stale login attempts. Accounts are never touched. */
function purge_expired(mysqli $conn, int $days): array
{
    $stmt = $conn->prepare('DELETE FROM meetings WHERE date < DATE_SUB(CURDATE(), INTERVAL ? DAY)');
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $meetings = $stmt->affected_rows;

    $attempts = 0;
    $res = $conn->query('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    if ($res) {
        $attempts = $conn->affected_rows;
    }
    return ['meetings' => $meetings, 'login_attempts' => $attempts];
}

/**
 * Shared hosting has no cron, so the first request after 24h runs the cleanup.
 * The UPDATE is the claim: only the request that changes the row proceeds.
 * Never lets a failure reach the page.
 */
function run_maintenance_if_due(): void
{
    try {
        $conn = db();
        $cutoff = date('Y-m-d H:i:s', time() - 86400);
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE app_state SET v = ? WHERE k = 'last_cleanup' AND v < ?");
        $stmt->bind_param('ss', $now, $cutoff);
        $stmt->execute();
        if ($stmt->affected_rows === 1) {
            purge_expired($conn, retention_days());
        }
    } catch (Throwable $e) {
        error_log('[toastmasters-timer] maintenance failed: ' . $e->getMessage());
    }
}
