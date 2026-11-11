<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $time = $_POST['time'];

    $stmt = $conn->prepare("UPDATE speakers SET final_time = ?, status = 'completed' WHERE id = ?");
    $stmt->bind_param("si", $time, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}
?>