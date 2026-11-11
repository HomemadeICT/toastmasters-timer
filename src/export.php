<?php
declare(strict_types=1);

const REPORT_CATEGORY_LABELS = ['ps_speaker' => 'SP', 'tt_speaker' => 'TT', 'ps_eval' => 'EV', 'tt_eval' => 'TE', 'other' => ''];
const EXPORT_MAX_MEETINGS = 50;

/**
 * Grades each speaker, groups them (speakers, then evaluators, then other roles), puts qualified before
 * disqualified and numbers them SP1, TT2, EV1... Shared by the report page and the exports.
 *
 * @param array<int,array<string,mixed>> $speakers rows from the speakers table
 * @return array<int,array<string,mixed>>
 */
function prepare_report_speakers(array $speakers): array
{
    $categoryOrder = ['ps_speaker' => 0, 'tt_speaker' => 1, 'ps_eval' => 2, 'tt_eval' => 3, 'other' => 4];

    $list = [];
    foreach ($speakers as $row) {
        $row['actualSec'] = time_to_sec($row['final_time']);
        $row['is_disqualified'] = false;
        $row['diffText'] = '';
        $row['grace_status'] = ''; // 'strict', 'grace', or ''

        if ($row['final_time'] && $row['role'] !== 'Round Robin') {
            $grade = grade_time($row['role'], (int)$row['min_time'], (int)$row['max_time'], $row['actualSec']);
            $row['effective_lower'] = $grade['lower'];
            $row['effective_upper'] = $grade['upper'];
            $row['is_disqualified'] = $grade['disqualified'];
            $row['grace_status'] = $grade['status'];
            $row['diffText'] = $grade['text'];
        }
        $row['report_category'] = classify_speaker($row['role']);
        $list[] = $row;
    }

    usort($list, function ($a, $b) use ($categoryOrder) {
        $catCmp = $categoryOrder[$a['report_category']] <=> $categoryOrder[$b['report_category']];
        if ($catCmp !== 0) {
            return $catCmp;
        }
        if ($a['is_disqualified'] !== $b['is_disqualified']) {
            return $a['is_disqualified'] ? 1 : -1;
        }
        return $a['sort_order'] <=> $b['sort_order'];
    });

    $counters = [];
    foreach ($list as &$s) {
        $cat = $s['report_category'];
        $counters[$cat] = ($counters[$cat] ?? 0) + 1;
        $label = REPORT_CATEGORY_LABELS[$cat];
        $s['report_index'] = $label ? $label . $counters[$cat] : '';
    }
    unset($s);
    return $list;
}

/** Plain-text result for a graded speaker: QUALIFIED, GRACE..., UNDER/OVER (disqualified), or '' when not timed. */
function result_badge(array $s): string
{
    if (!$s['final_time'] || $s['role'] === 'Round Robin') {
        return '';
    }
    if ($s['is_disqualified']) {
        return 'DISQUALIFIED ' . $s['diffText'];
    }
    return $s['grace_status'] === 'grace' ? 'QUALIFIED ' . $s['diffText'] : 'QUALIFIED';
}

/** 'pass', 'grace', 'fail' or '' for styling the printed badge. */
function result_badge_class(array $s): string
{
    if (!$s['final_time'] || $s['role'] === 'Round Robin') {
        return '';
    }
    if ($s['is_disqualified']) {
        return 'fail';
    }
    return $s['grace_status'] === 'grace' ? 'grace' : 'pass';
}

/** Parses ids[] from a POST body: ints, unique, positive, capped. */
function posted_meeting_ids(): array
{
    $raw = $_POST['ids'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $raw), fn($i) => $i > 0)));
    return array_slice($ids, 0, EXPORT_MAX_MEETINGS);
}

/**
 * Loads the user's own meetings from $ids (foreign or unknown ids are silently dropped) with graded speakers.
 *
 * @return array<int,array{meeting:array<string,mixed>,speakers:array<int,array<string,mixed>>}> ordered by date
 */
function load_report_data(array $ids, int $uid): array
{
    if ($ids === []) {
        return [];
    }
    $conn = db();
    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = 'i' . str_repeat('i', count($ids));

    $stmt = $conn->prepare("SELECT * FROM meetings WHERE user_id = ? AND id IN ($in) ORDER BY date ASC, id ASC");
    $stmt->bind_param($types, $uid, ...$ids);
    $stmt->execute();
    $meetings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if ($meetings === []) {
        return [];
    }

    $owned = array_map(fn($m) => (int)$m['id'], $meetings);
    $in2 = implode(',', array_fill(0, count($owned), '?'));
    $stmt = $conn->prepare("SELECT * FROM speakers WHERE meeting_id IN ($in2) ORDER BY sort_order ASC, id ASC");
    $stmt->bind_param(str_repeat('i', count($owned)), ...$owned);
    $stmt->execute();
    $byMeeting = [];
    foreach ($stmt->get_result() as $row) {
        $byMeeting[(int)$row['meeting_id']][] = $row;
    }

    $out = [];
    foreach ($meetings as $m) {
        $out[] = ['meeting' => $m, 'speakers' => prepare_report_speakers($byMeeting[(int)$m['id']] ?? [])];
    }
    return $out;
}

/** Guards against spreadsheet formula injection: cells starting with = + - @ (or tab/CR) are prefixed with an apostrophe. */
function csv_cell(mixed $value): string
{
    $v = (string)($value ?? '');
    return $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
}

function csv_header(): array
{
    return ['Meeting', 'Date', 'Location', 'Theme', 'TMOD', 'Timer', 'No.', 'Name', 'Role', 'Min', 'Target', 'Max', 'Actual', 'Result', 'Disqualified'];
}

/** @return array<int,array<int,string>> rows for the speakers of one meeting */
function csv_rows_for_meeting(array $meeting, array $speakers): array
{
    $rows = [];
    foreach ($speakers as $s) {
        $rows[] = array_map('csv_cell', [
            $meeting['name'], $meeting['date'], $meeting['location'], $meeting['theme'], $meeting['tmod'], $meeting['timer_name'],
            $s['report_index'], $s['name'], $s['role'],
            mmss((int)$s['min_time']), mmss((int)$s['target_time']), mmss((int)$s['max_time']),
            $s['final_time'] ?? '', result_badge($s), $s['is_disqualified'] ? 'yes' : 'no',
        ]);
    }
    return $rows;
}

function send_csv(array $data, string $filename): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, csv_header());
    foreach ($data as $d) {
        foreach (csv_rows_for_meeting($d['meeting'], $d['speakers']) as $row) {
            fputcsv($out, $row);
        }
    }
    fclose($out);
    exit;
}
