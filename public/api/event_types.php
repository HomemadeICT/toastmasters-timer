<?php
require_once __DIR__ . '/../../src/bootstrap.php';

$_SERVER['HTTP_X_REQUESTED_WITH'] = 'fetch';
require_method('POST');
$uid = require_login();
require_csrf();

function event_type_input(): array
{
    $name = clean_text($_POST['name'] ?? '');
    if ($name === '') {
        abort(400, 'Event name is required.');
    }
    $limits = valid_limits((int)($_POST['min_time'] ?? 0), (int)($_POST['target_time'] ?? 0), (int)($_POST['max_time'] ?? 0));
    if ($limits === null) {
        abort(400, 'Times must be between 0 and 24h, and min <= target <= max.');
    }
    return [$name, ...$limits];
}

$conn = db();
$action = $_POST['action'] ?? '';

if ($action === 'get_types') {
    $meetingId = (int)($_POST['meeting_id'] ?? 0);
    if (!get_owned_meeting($meetingId, $uid)) {
        abort(404, 'Meeting not found.');
    }
    $stmt = $conn->prepare('SELECT id, meeting_id, name, min_time, target_time, max_time FROM event_types WHERE meeting_id = ? ORDER BY name ASC');
    $stmt->bind_param('i', $meetingId);
    $stmt->execute();
    json_response($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

if ($action === 'create_type') {
    $meetingId = (int)($_POST['meeting_id'] ?? 0);
    if (!get_owned_meeting($meetingId, $uid)) {
        abort(404, 'Meeting not found.');
    }
    [$name, $min, $target, $max] = event_type_input();
    try {
        $stmt = $conn->prepare('INSERT INTO event_types (meeting_id, name, min_time, target_time, max_time) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('isiii', $meetingId, $name, $min, $target, $max);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        abort($e->getCode() === 1062 ? 409 : 500, $e->getCode() === 1062 ? 'An event type with that name already exists.' : 'Failed to create event type.');
    }
    json_response(['success' => true]);
}

if ($action === 'update_type' || $action === 'delete_type') {
    $et = get_owned_event_type((int)($_POST['event_type_id'] ?? 0), $uid);
    if (!$et) {
        abort(404, 'Event type not found.');
    }
    $etId = (int)$et['id'];

    if ($action === 'delete_type') {
        $stmt = $conn->prepare('DELETE FROM event_types WHERE id = ?');
        $stmt->bind_param('i', $etId);
        $stmt->execute();
        json_response(['success' => true]);
    }

    [$name, $min, $target, $max] = event_type_input();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('UPDATE event_types SET name = ?, min_time = ?, target_time = ?, max_time = ? WHERE id = ?');
        $stmt->bind_param('siiii', $name, $min, $target, $max, $etId);
        $stmt->execute();
        $stmt = $conn->prepare('UPDATE speakers SET min_time = ?, target_time = ?, max_time = ? WHERE event_type_id = ?');
        $stmt->bind_param('iiii', $min, $target, $max, $etId);
        $stmt->execute();
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        abort($e->getCode() === 1062 ? 409 : 500, $e->getCode() === 1062 ? 'An event type with that name already exists.' : 'Failed to update event type.');
    }
    json_response(['success' => true]);
}

abort(400, 'Unknown action.');
