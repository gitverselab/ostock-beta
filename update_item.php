<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $item_code = trim($_POST['item_code']);
    $uom = trim($_POST['uom']);

    if ($id <= 0 || empty($name) || empty($item_code) || empty($uom)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid input.']);
        exit;
    }

    $sql = "UPDATE items SET name = ?, item_code = ?, uom = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $name, $item_code, $uom, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Item updated successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update item.']);
    }
    $stmt->close();
    $conn->close();
}
?>