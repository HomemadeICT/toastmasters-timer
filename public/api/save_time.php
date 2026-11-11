<?php
require_once __DIR__ . '/../../src/bootstrap.php';

$_SERVER['HTTP_X_REQUESTED_WITH'] = 'fetch';
require_method('POST');
$uid = require_login();
require_csrf();

$speaker = get_owned_speaker((int)($_POST['id'] ?? 0), $uid);
if (!$speaker) {
    abort(404, 'Speaker not found.');
}
$time = normalize_final_time($_POST['time'] ?? '');
if ($time === null || $time === '') {
    abort(400, 'Invalid time.');
}

$stmt = db()->prepare("UPDATE speakers SET final_time = ?, status = 'completed' WHERE id = ?");
$stmt->bind_param('si', $time, $speaker['id']);
$stmt->execute();
json_response(['success' => true, 'time' => $time]);
