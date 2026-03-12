<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';

// Inbound record ID to delete
if (!isset($_GET['id'])) {
    die("Error: Inbound ID not specified.");
}
$inbound_id = intval($_GET['id']);

// Get the username from session for logging
$deleted_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';

// Begin transaction
$conn->begin_transaction();

// Retrieve the inbound record from the inventory table
$sql = "SELECT * FROM inventory WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $inbound_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    $conn->rollback();
    die("Error: Inbound record not found.");
}
$inbound = $result->fetch_assoc();
$stmt->close();

// Log the deletion into deleted_transactions table
$details = json_encode($inbound);
$log_sql = "INSERT INTO deleted_transactions (transaction_type, original_id, details, deleted_by) VALUES ('inbound', ?, ?, ?)";
$logStmt = $conn->prepare($log_sql);
$logStmt->bind_param("iss", $inbound_id, $details, $deleted_by);
if (!$logStmt->execute()) {
    $conn->rollback();
    die("Error: Failed to log deletion.");
}
$logStmt->close();

// Log the deletion in the inventory_history table (as an inbound_delete transaction)
$history_sql = "INSERT INTO inventory_history 
                (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, production_date, expiry_date, processed_by, details)
                VALUES ('inbound_delete', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($history_sql);
$stmt->bind_param(
    "iisiiisssss", 
    $inbound_id, 
    $inbound['item_id'], 
    $inbound['pallet_id'], 
    $inbound['warehouse_id'], 
    $inbound['quantity'], 
    $inbound['items_per_pc'], 
    $inbound['uom'], 
    $inbound['production_date'], 
    $inbound['expiry_date'], 
    $deleted_by, 
    $details
);
if (!$stmt->execute()) {
    $conn->rollback();
    die("Error: Failed to log deletion in inventory history.");
}
$stmt->close();

// Delete the inbound record from the inventory table
$del = $conn->prepare("DELETE FROM inventory WHERE id = ?");
$del->bind_param("i", $inbound_id);
if (!$del->execute()) {
    $conn->rollback();
    die("Error: Failed to delete inbound record.");
}
$del->close();

$conn->commit();
echo "<script>alert('Inbound transaction deleted and inventory reverted successfully.'); window.location='inbound_history.php';</script>";
exit;
?>
