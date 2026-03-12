<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
include 'db.php';
header('Content-Type: application/json');

if (!isset($_POST['item_id']) || empty($_POST['item_id'])) {
    json_response(['success' => false, 'message' => 'Item ID not provided.']);
    exit;
}

$item_id = intval($_POST['item_id']);

$stmt = $conn->prepare("SELECT uom, primary_uom_label, secondary_uom_label FROM items WHERE id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if ($item) {
    json_response([
        'success' => true,
        'uom' => $item['uom'],
        'primary_label' => $item['primary_uom_label'] ?? 'Primary Qty',
        'secondary_label' => $item['secondary_uom_label'] ?? 'Secondary Qty'
    ]);
} else {
    json_response(['success' => false, 'message' => 'Item not found.'], 404);
}

function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}
?>