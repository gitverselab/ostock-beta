<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';

// Ensure a transfer_id is provided
if (!isset($_GET['transfer_id']) || empty($_GET['transfer_id'])) {
    die("Error: Transfer ID not specified. Please select a valid transfer to delete.");
}
$transfer_id = intval($_GET['transfer_id']);

// Get the username from session for logging
$deleted_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';

// Begin transaction
$conn->begin_transaction();
$error = false;
$error_message = "";

// 1. Retrieve the transfer record
$sql = "SELECT * FROM transfers WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $transfer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    $conn->rollback();
    die("Error: Transfer record not found.");
}
$transfer = $result->fetch_assoc();
$stmt->close();

// Log the deletion into deleted_transactions table
$details = json_encode($transfer);
$log_sql = "INSERT INTO deleted_transactions (transaction_type, original_id, details, deleted_by) VALUES ('transfer', ?, ?, ?)";
$logStmt = $conn->prepare($log_sql);
$logStmt->bind_param("iss", $transfer_id, $details, $deleted_by);
if (!$logStmt->execute()) {
    $conn->rollback();
    die("Error: Failed to log transfer deletion.");
}
$logStmt->close();

// Extract transfer details
$item_id = $transfer['item_id'];
$source_warehouse = $transfer['source_warehouse'];
$destination_warehouse = $transfer['destination_warehouse'];
$source_pallet = $transfer['source_pallet'];
$dest_pallet = $transfer['dest_pallet'];
$qty_transferred = $transfer['quantity_transferred']; // crates
$pieces_transferred = $transfer['pieces_transferred'];  // pieces
$uom_value = isset($transfer['uom']) ? $transfer['uom'] : "";

// 2. Reverse Transfer Adjustments

// --- Reverse Source Inventory: Add back the transferred stock ---
$sql = "SELECT id, quantity, items_per_pc FROM inventory WHERE item_id = ? AND pallet_id = ? AND warehouse_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isi", $item_id, $source_pallet, $source_warehouse);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $new_qty = $row['quantity'] + $qty_transferred;
    $new_items = $row['items_per_pc'] + $pieces_transferred;
    $upd = $conn->prepare("UPDATE inventory SET quantity = ?, items_per_pc = ? WHERE id = ?");
    $upd->bind_param("iii", $new_qty, $new_items, $row['id']);
    if (!$upd->execute()) {
        $error = true;
        $error_message = "Failed to update source inventory.";
    }
    $upd->close();
} else {
    // If no matching source inventory record exists, insert new record.
    $ins = $conn->prepare("INSERT INTO inventory (item_id, quantity, items_per_pc, pallet_id, warehouse_id, date_received) VALUES (?, ?, ?, ?, ?, NOW())");
    $ins->bind_param("iiisi", $item_id, $qty_transferred, $pieces_transferred, $source_pallet, $source_warehouse);
    if (!$ins->execute()) {
        $error = true;
        $error_message = "Failed to insert source inventory record.";
    }
    $ins->close();
}
$stmt->close();

// --- Reverse Destination Inventory: Remove the transferred stock that was added by the transfer ---
// Instead of selecting by item, pallet, and warehouse, select the inbound record created for the transfer using transfer_id.
$sql = "SELECT id, quantity, items_per_pc FROM inventory WHERE transfer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $transfer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    // This inbound record should have quantity equal to the transferred amount.
    // To reverse, we want to remove it completely.
    // However, if the inbound record was partially modified, we subtract the original transferred amounts.
    $new_qty_dest = $row['quantity'] - $qty_transferred;
    $new_items_dest = $row['items_per_pc'] - $pieces_transferred;
    if ($new_qty_dest < 0 || $new_items_dest < 0) {
        $error = true;
        $error_message = "Reversing transfer would lead to negative stock at destination.";
    } else {
        if ($new_qty_dest == 0 && $new_items_dest == 0) {
            $del_sql = "DELETE FROM inventory WHERE id = ?";
            $delStmt = $conn->prepare($del_sql);
            $delStmt->bind_param("i", $row['id']);
            if (!$delStmt->execute()) {
                $error = true;
                $error_message = "Failed to delete destination inventory record.";
            }
            $delStmt->close();
        } else {
            $upd_sql = "UPDATE inventory SET quantity = ?, items_per_pc = ? WHERE id = ?";
            $updStmt = $conn->prepare($upd_sql);
            $updStmt->bind_param("iii", $new_qty_dest, $new_items_dest, $row['id']);
            if (!$updStmt->execute()) {
                $error = true;
                $error_message = "Failed to update destination inventory.";
            }
            $updStmt->close();
        }
    }
} else {
    $error = true;
    $error_message = "Destination inventory record not found for reversal.";
}
$stmt->close();

// 3. Delete related outbound and inbound records associated with this transfer using transfer_id.
$delOutbound = $conn->prepare("DELETE FROM outbound_inventory WHERE transfer_id = ?");
$delOutbound->bind_param("i", $transfer_id);
$delOutbound->execute();
$delOutbound->close();

$delInbound = $conn->prepare("DELETE FROM inventory WHERE transfer_id = ?");
$delInbound->bind_param("i", $transfer_id);
$delInbound->execute();
$delInbound->close();

// 4. Log the transfer deletion in inventory_history (transaction type "transfer_delete")
$history_details = json_encode($transfer);
$stmt_tr = $conn->prepare("INSERT INTO inventory_history (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, processed_by, details) VALUES ('transfer_delete', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt_tr->bind_param("iisiiisss", $transfer_id, $item_id, $source_pallet, $source_warehouse, $qty_transferred, $pieces_transferred, $uom_value, $deleted_by, $history_details);
$stmt_tr->execute();
$stmt_tr->close();

// 5. Delete the transfer record
$delTransfer = $conn->prepare("DELETE FROM transfers WHERE id = ?");
$delTransfer->bind_param("i", $transfer_id);
$delTransfer->execute();
$delTransfer->close();

if ($error) {
    $conn->rollback();
    echo "<script>alert('Transfer deletion failed: $error_message'); window.location='transfer_history.php';</script>";
    exit;
} else {
    $conn->commit();
    echo "<script>alert('Transfer deleted and inventory reversed successfully!'); window.location='transfer_history.php';</script>";
    exit;
}
?>
