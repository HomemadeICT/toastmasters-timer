<?php
declare(strict_types=1);

const MAX_SECONDS = 86400;

function clean_text(mixed $value, int $maxLen = 255): string
{
    $s = trim((string)$value);
    return mb_substr($s, 0, $maxLen);
}

function valid_date(mixed $value): ?string
{
    $s = (string)$value;
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

function valid_email(mixed $value): ?string
{
    $s = strtolower(trim((string)$value));
    return (strlen($s) <= 254 && filter_var($s, FILTER_VALIDATE_EMAIL)) ? $s : null;
}

/** Accepts m:ss or m.ss (as the report page allows); returns canonical "m:ss", '' for empty, null for invalid. */
function normalize_final_time(mixed $value): ?string
{
    $s = trim((string)$value);
    if ($s === '') {
        return '';
    }
    if (!preg_match('/^(\d{1,3})[:.]([0-5]\d)$/', $s, $m)) {
        return null;
    }
    return ((int)$m[1]) . ':' . $m[2];
}

/** Returns [min, target, max] in seconds, or null when out of range / not ordered. */
function valid_limits(int $min, int $target, int $max): ?array
{
    foreach ([$min, $target, $max] as $v) {
        if ($v < 0 || $v > MAX_SECONDS) {
            return null;
        }
    }
    return ($min <= $target && $target <= $max) ? [$min, $target, $max] : null;
}

function post_seconds(string $minKey, string $secKey): int
{
    return (int)($_POST[$minKey] ?? 0) * 60 + (int)($_POST[$secKey] ?? 0);
}
