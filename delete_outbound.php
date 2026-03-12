<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';

if (!isset($_GET['id'])) {
    die("Error: Outbound ID not specified.");
}
$outbound_id = intval($_GET['id']);

// Get the username from session for logging
$deleted_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';

// Begin transaction
$conn->begin_transaction();

// Retrieve the outbound record from outbound_inventory
$sql = "SELECT * FROM outbound_inventory WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $outbound_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    $conn->rollback();
    die("Error: Outbound record not found.");
}
$outbound = $result->fetch_assoc();
$stmt->close();

// Log the deletion into deleted_transactions table
$details = json_encode($outbound);
$log_sql = "INSERT INTO deleted_transactions (transaction_type, original_id, details, deleted_by) VALUES ('outbound', ?, ?, ?)";
$logStmt = $conn->prepare($log_sql);
$logStmt->bind_param("iss", $outbound_id, $details, $deleted_by);
if (!$logStmt->execute()) {
    $conn->rollback();
    die("Error: Failed to log outbound deletion.");
}
$logStmt->close();

// Reverse the outbound: Add back the removed stock to inventory.
// Find the inventory record corresponding to the outbound record’s item, pallet, and warehouse.
$sql = "SELECT id, quantity, items_per_pc FROM inventory WHERE item_id = ? AND pallet_id = ? AND warehouse_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isi", $outbound['item_id'], $outbound['pallet_id'], $outbound['warehouse_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    // Add back the removed amounts
    $new_qty = $row['quantity'] + $outbound['quantity_removed'];
    $new_items = $row['items_per_pc'] + $outbound['items_per_pc'];
    $upd = $conn->prepare("UPDATE inventory SET quantity = ?, items_per_pc = ? WHERE id = ?");
    $upd->bind_param("iii", $new_qty, $new_items, $row['id']);
    if (!$upd->execute()) {
        $conn->rollback();
        die("Error: Failed to update inventory during reversal.");
    }
    $upd->close();
    $inventory_id = $row['id'];
} else {
    // If no matching inventory record exists, insert one with the reversed amounts.
    $ins = $conn->prepare("INSERT INTO inventory (item_id, quantity, items_per_pc, pallet_id, warehouse_id, date_received) VALUES (?, ?, ?, ?, ?, NOW())");
    $ins->bind_param("iiisi", $outbound['item_id'], $outbound['quantity_removed'], $outbound['items_per_pc'], $outbound['pallet_id'], $outbound['warehouse_id']);
    if (!$ins->execute()) {
        $conn->rollback();
        die("Error: Failed to insert inventory during reversal.");
    }
    $inventory_id = $conn->insert_id;
    $ins->close();
}
$stmt->close();

// Delete the outbound record
$del = $conn->prepare("DELETE FROM outbound_inventory WHERE id = ?");
$del->bind_param("i", $outbound_id);
if (!$del->execute()) {
    $conn->rollback();
    die("Error: Failed to delete outbound record.");
}
$del->close();

// Log the deletion in inventory_history as an "outbound_delete" transaction.
$history_details = json_encode($outbound);
$history_sql = "INSERT INTO inventory_history 
                (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, production_date, expiry_date, processed_by, details)
                VALUES ('outbound_delete', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$historyStmt = $conn->prepare($history_sql);
$historyStmt->bind_param(
    "iisiiisssss", 
    $outbound_id, 
    $outbound['item_id'], 
    $outbound['pallet_id'], 
    $outbound['warehouse_id'], 
    $outbound['quantity_removed'], 
    $outbound['items_per_pc'], 
    $outbound['uom'], 
    $outbound['production_date'], 
    $outbound['expiry_date'], 
    $deleted_by, 
    $history_details
);
if (!$historyStmt->execute()) {
    $conn->rollback();
    die("Error: Failed to log outbound deletion in inventory history.");
}
$historyStmt->close();

$conn->commit();
echo "<script>alert('Outbound transaction deleted and inventory reverted successfully.'); window.location='outbound_history.php';</script>";
exit;
?>
