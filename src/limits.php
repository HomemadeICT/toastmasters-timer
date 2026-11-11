<?php
declare(strict_types=1);

const DEFAULT_RETENTION_DAYS = 30;
const DEFAULT_MAX_MEETINGS = 20;
const DEFAULT_MAX_SPEAKERS = 40;

function limit_setting(string $key, int $default): int
{
    $v = (int)(app_config()[$key] ?? 0);
    return $v > 0 ? $v : $default;
}

function retention_days(): int { return limit_setting('retention_days', DEFAULT_RETENTION_DAYS); }
function max_meetings(): int { return limit_setting('max_meetings', DEFAULT_MAX_MEETINGS); }
function max_speakers(): int { return limit_setting('max_speakers', DEFAULT_MAX_SPEAKERS); }

/** Meetings are removed once the meeting date is this many days in the past. */
function meeting_expires_on(string $date, ?int $days = null): string
{
    return (new DateTimeImmutable($date))->modify('+' . ($days ?? retention_days()) . ' days')->format('Y-m-d');
}

function days_until(string $ymd, ?DateTimeImmutable $today = null): int
{
    $today ??= new DateTimeImmutable('today');
    return (int)$today->diff(new DateTimeImmutable($ymd))->format('%r%a');
}

/** 'ok' below 80% of the cap, 'warn' from 80%, 'full' at the cap. */
function usage_state(int $used, int $max): string
{
    if ($used >= $max) {
        return 'full';
    }
    return $used * 5 >= $max * 4 ? 'warn' : 'ok';
}

function count_user_meetings(int $uid): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM meetings WHERE user_id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_column();
}

function count_meeting_speakers(int $meetingId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM speakers WHERE meeting_id = ?');
    $stmt->bind_param('i', $meetingId);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_column();
}

/** Deletes one owned meeting (speakers and custom events cascade). Returns false if it is not theirs. */
function delete_owned_meeting(int $meetingId, int $uid): bool
{
    $stmt = db()->prepare('DELETE FROM meetings WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $meetingId, $uid);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}
