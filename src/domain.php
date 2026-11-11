<?php
declare(strict_types=1);

function mmss(int $seconds): string
{
    return intdiv($seconds, 60) . ':' . str_pad((string)($seconds % 60), 2, '0', STR_PAD_LEFT);
}

function time_to_sec(?string $str): int
{
    if (!$str) {
        return 0;
    }
    $p = explode(':', str_replace('.', ':', $str));
    return (int)$p[0] * 60 + (int)($p[1] ?? 0);
}

function is_table_topic_role(string $role): bool
{
    return (bool)preg_match('/\btable\s*topics?\b|\btt\b|\btte\b/i', $role);
}

/** Report category for a speaker role. */
function classify_speaker(string $role): string
{
    $r = strtolower(trim($role));
    if ($r === 'round robin' || str_contains($r, 'general evaluator') || preg_match('/^ge\b/', $r)) {
        return 'other';
    }
    $isEval = (bool)preg_match('/\beval/', $r) || (bool)preg_match('/\btte\b/', $r);
    $isTT = is_table_topic_role($r);
    if ($isEval && $isTT) return 'tt_eval';
    if ($isEval)          return 'ps_eval';
    if ($isTT)            return 'tt_speaker';
    return 'ps_speaker';
}

/**
 * Qualification result for a timed speaker.
 * Returns ['disqualified'=>bool, 'status'=>'strict'|'grace'|'', 'text'=>string, 'lower'=>int, 'upper'=>int].
 */
function grade_time(string $role, int $min, int $max, int $actual, int $grace = 30): array
{
    $lower = is_table_topic_role($role) ? $min : max(0, $min - $grace);
    $upper = $max + $grace;
    $out = ['disqualified' => false, 'status' => '', 'text' => '', 'lower' => $lower, 'upper' => $upper];
    if ($actual < $lower) {
        $out['disqualified'] = true;
        $out['text'] = '(-' . mmss($min - $actual) . ' UNDER)';
    } elseif ($actual > $upper) {
        $out['disqualified'] = true;
        $out['text'] = '(+' . mmss($actual - $max) . ' OVER)';
    } elseif ($actual < $min) {
        $out['status'] = 'grace';
        $out['text'] = '(GRACE -' . mmss($min - $actual) . ')';
    } elseif ($actual > $max) {
        $out['status'] = 'grace';
        $out['text'] = '(GRACE +' . mmss($actual - $max) . ')';
    } else {
        $out['status'] = 'strict';
        $out['text'] = '(QUALIFIED)';
    }
    return $out;
}
