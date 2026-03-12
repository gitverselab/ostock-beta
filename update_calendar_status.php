<?php
session_start();
header('Content-Type: application/json');

// Check for authentication and role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['item_id']) || !isset($_POST['status'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
        exit;
    }

    $itemId = intval($_POST['item_id']);
    $status = intval($_POST['status']) === 1 ? 1 : 0; // Sanitize status to 1 or 0

    $stmt = $conn->prepare("UPDATE items SET is_calendar_item = ? WHERE id = ?");
    $stmt->bind_param("ii", $status, $itemId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
    }
    $stmt->close();
    $conn->close();
}
?>