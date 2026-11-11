<?php
require_once 'db.php';

// Get all event types for a meeting
function getEventTypes($meeting_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM event_types WHERE meeting_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $meeting_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Get default event type details
function getEventTypeDefaults($role) {
    $defaults = [
        'Prepared Speech' => ['min' => 300, 'target' => 360, 'max' => 420],
        'Ice Breaker' => ['min' => 240, 'target' => 300, 'max' => 360],
        'Table Topic' => ['min' => 60, 'target' => 90, 'max' => 120],
        'Evaluator' => ['min' => 120, 'target' => 150, 'max' => 180],
        'Round Robin' => ['min' => 10, 'target' => 15, 'max' => 20],
        'Q&A Session' => ['min' => 300, 'target' => 480, 'max' => 600],
    ];
    return $defaults[$role] ?? ['min' => 300, 'target' => 300, 'max' => 300];
}

// Create a new event type
function createEventType($meeting_id, $name, $min_time, $target_time, $max_time) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO event_types (meeting_id, name, min_time, target_time, max_time) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiii", $meeting_id, $name, $min_time, $target_time, $max_time);
    return $stmt->execute();
}

// Update event type
function updateEventType($event_type_id, $name, $min_time, $target_time, $max_time) {
    global $conn;
    $stmt = $conn->prepare("UPDATE event_types SET name = ?, min_time = ?, target_time = ?, max_time = ? WHERE id = ?");
    $stmt->bind_param("siiii", $name, $min_time, $target_time, $max_time, $event_type_id);
    $result = $stmt->execute();
    
    if ($result) {
        $stmt_speakers = $conn->prepare("UPDATE speakers SET min_time = ?, target_time = ?, max_time = ? WHERE event_type_id = ?");
        $stmt_speakers->bind_param("iiii", $min_time, $target_time, $max_time, $event_type_id);
        $stmt_speakers->execute();
    }
    
    return $result;
}

// Delete event type
function deleteEventType($event_type_id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM event_types WHERE id = ?");
    $stmt->bind_param("i", $event_type_id);
    return $stmt->execute();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'get_types') {
        $meeting_id = intval($_POST['meeting_id']);
        $types = getEventTypes($meeting_id);
        $result = [];
        while ($row = $types->fetch_assoc()) {
            $result[] = $row;
        }
        echo json_encode($result);
    } elseif ($_POST['action'] === 'create_type') {
        $meeting_id = intval($_POST['meeting_id']);
        $name = $_POST['name'];
        $min_time = intval($_POST['min_time']);
        $target_time = intval($_POST['target_time']);
        $max_time = intval($_POST['max_time']);
        
        if (createEventType($meeting_id, $name, $min_time, $target_time, $max_time)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create event type']);
        }
    } elseif ($_POST['action'] === 'update_type') {
        $event_type_id = intval($_POST['event_type_id']);
        $name = $_POST['name'];
        $min_time = intval($_POST['min_time']);
        $target_time = intval($_POST['target_time']);
        $max_time = intval($_POST['max_time']);
        
        if (updateEventType($event_type_id, $name, $min_time, $target_time, $max_time)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update event type']);
        }
    } elseif ($_POST['action'] === 'delete_type') {
        $event_type_id = intval($_POST['event_type_id']);
        if (deleteEventType($event_type_id)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete event type']);
        }
    }
}
?>
