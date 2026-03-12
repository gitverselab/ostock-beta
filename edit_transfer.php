<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';
include 'navbar.php';

// Ensure a transfer_id is provided
if (!isset($_GET['transfer_id']) || empty($_GET['transfer_id'])) {
    die("Error: Transfer ID not specified. Please select a valid transfer to edit.");
}
$transfer_id = intval($_GET['transfer_id']);

// Retrieve the original transfer record
$sql = "SELECT * FROM transfers WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $transfer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("Error: Transfer record not found.");
}
$transfer = $result->fetch_assoc();
$stmt->close();

// Store key original fields for reversal
$item_id = intval($transfer['item_id']);
$source_warehouse = intval($transfer['source_warehouse']);
$destination_warehouse = intval($transfer['destination_warehouse']);
$orig_source_pallet = $transfer['source_pallet'];
$orig_dest_pallet = $transfer['dest_pallet'];
$orig_qty = intval($transfer['quantity_transferred']);  // crates
$orig_pieces = intval($transfer['pieces_transferred']);   // pieces

// Retrieve production_date, expiry_date, and uom from an outbound_inventory record for this transfer.
$sql = "SELECT oi.production_date, oi.expiry_date, i.uom 
        FROM outbound_inventory oi 
        JOIN items i ON oi.item_id = i.id 
        WHERE oi.transfer_id = ? 
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $transfer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()){
    $production_date_val = $row['production_date'];
    $expiry_date_val = $row['expiry_date'];
    $uom_val = $row['uom'];
} else {
    $production_date_val = "";
    $expiry_date_val = "";
    $uom_val = "";
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get new values from form
    $new_source_pallet = $_POST['source_pallet'];
    $new_dest_pallet = $_POST['dest_pallet'];
    $new_qty = intval($_POST['quantity_transferred']);
    $new_pieces = intval($_POST['pieces_transferred']);
    
    // Get current processed_by from session
    $processed_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';
    $current_time = date('Y-m-d H:i:s');
    
    $conn->begin_transaction();
    $error = false;
    $error_message = "";
    
    // --- Step 1: Reverse the Original Transfer ---
    // Reverse Source: add back original amounts to source inventory.
    $sql = "SELECT id, quantity, items_per_pc FROM inventory 
            WHERE item_id = ? AND pallet_id = ? AND warehouse_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $item_id, $orig_source_pallet, $source_warehouse);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()){
        $reversed_qty = $row['quantity'] + $orig_qty;
        $reversed_pieces = $row['items_per_pc'] + $orig_pieces;
        $upd = $conn->prepare("UPDATE inventory SET quantity = ?, items_per_pc = ? WHERE id = ?");
        $upd->bind_param("iii", $reversed_qty, $reversed_pieces, $row['id']);
        if (!$upd->execute()){
            $conn->rollback();
            die("Error: Failed to reverse source inventory.");
        }
        $upd->close();
    } else {
        // No record exists: re-insert using the outbound values.
        $insert_sql = "INSERT INTO inventory 
            (item_id, quantity, items_per_pc, pallet_id, warehouse_id, date_received, uom, production_date, expiry_date, processed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insStmt = $conn->prepare($insert_sql);
        // Types: i, i, i, s, i, s, s, s, s, s → "iiisisssss"
        $insStmt->bind_param("iiisisssss", $item_id, $orig_qty, $orig_pieces, $orig_source_pallet, $source_warehouse, $current_time, $uom_val, $production_date_val, $expiry_date_val, $processed_by);
        if (!$insStmt->execute()){
            $conn->rollback();
            die("Error: Failed to reverse source inventory (insert).");
        }
        $insStmt->close();
    }
    $stmt->close();
    
    // --- Step 2: Reverse Destination: Subtract the original transferred amounts from destination inventory ---
    // We select the inbound record created by this transfer (matching transfer_id).
    $sql = "SELECT id, quantity, items_per_pc FROM inventory 
            WHERE item_id = ? AND warehouse_id = ? AND transfer_id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $item_id, $destination_warehouse, $transfer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()){
        $dest_new_qty = $row['quantity'] - $orig_qty;
        $dest_new_pieces = $row['items_per_pc'] - $orig_pieces;
        if ($dest_new_qty < 0 || $dest_new_pieces < 0) {
            $error = true;
            $error_message = "Reversing original transfer would lead to negative stock at destination.";
        } else {
            if ($dest_new_qty == 0 && $dest_new_pieces == 0) {
                $del_sql = "DELETE FROM inventory WHERE id = ?";
                $delStmt = $conn->prepare($del_sql);
                $delStmt->bind_param("i", $row['id']);
                if (!$delStmt->execute()){
                    $error = true;
                    $error_message = "Failed to delete destination inventory record during reversal.";
                }
                $delStmt->close();
            } else {
                $upd_sql = "UPDATE inventory SET quantity = ?, items_per_pc = ? WHERE id = ?";
                $updStmt = $conn->prepare($upd_sql);
                $updStmt->bind_param("iii", $dest_new_qty, $dest_new_pieces, $row['id']);
                if (!$updStmt->execute()){
                    $error = true;
                    $error_message = "Failed to update destination inventory during reversal.";
                }
                $updStmt->close();
            }
        }
    } else {
        $error = true;
        $error_message = "Destination inventory record not found during reversal.";
    }
    $stmt->close();
    
    // --- Before applying the new transfer, mark previous outbound records as overridden ---
    $upd_overridden = $conn->prepare("UPDATE outbound_inventory SET outbound_type = 'Outbound Overridden (Transfer)' WHERE transfer_id = ?");
    $upd_overridden->bind_param("i", $transfer_id);
    $upd_overridden->execute();
    $upd_overridden->close();
    
    // --- Step 3: Apply the New Transfer ---
    // Deduct new amounts from source inventory using new source pallet.
    $sql = "SELECT id, quantity, items_per_pc FROM inventory 
            WHERE item_id = ? AND pallet_id = ? AND warehouse_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $item_id, $new_source_pallet, $source_warehouse);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()){
        if ($row['quantity'] < $new_qty || $row['items_per_pc'] < $new_pieces) {
            $error = true;
            $error_message = "Not enough stock in source for new transfer.";
        } else {
            $update_sql = "UPDATE inventory SET quantity = quantity - ?, items_per_pc = items_per_pc - ? WHERE id = ?";
            $updStmt = $conn->prepare($update_sql);
            $updStmt->bind_param("iii", $new_qty, $new_pieces, $row['id']);
            if (!$updStmt->execute()){
                $error = true;
                $error_message = "Failed to apply new transfer to source inventory.";
            }
            $updStmt->close();
        }
    } else {
        $error = true;
        $error_message = "Source inventory record not found for new transfer.";
    }
    $stmt->close();
    
    // --- Step 4: Create new outbound record for the new transfer (type: "Transfer_Outbound(edited)") ---
    $outbound_sql = "INSERT INTO outbound_inventory 
      (item_id, pallet_id, quantity_removed, warehouse_id, items_per_pc, outbound_type, date_removed, transfer_id, processed_by, production_date, expiry_date)
      VALUES (?, ?, ?, ?, ?, 'Transfer_Outbound(edited)', ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($outbound_sql);
    // Types: i (item_id), s (new_source_pallet), i (new_qty), i (source_warehouse), i (new_pieces), s (current_time), i (transfer_id), s (processed_by), s (production_date_val), s (expiry_date_val)
    $stmt->bind_param("isiiisssss", $item_id, $new_source_pallet, $new_qty, $source_warehouse, $new_pieces, $current_time, $transfer_id, $processed_by, $production_date_val, $expiry_date_val);
    if (!$stmt->execute()){
        $error = true;
        $error_message = "Failed to create new outbound record.";
    }
    $new_outbound_id = $conn->insert_id;
    $stmt->close();
    
    // --- Step 5: Create new inbound record at destination for the new transfer ---
    $inbound_sql = "INSERT INTO inventory 
      (item_id, quantity, uom, expiry_date, production_date, pallet_id, warehouse_id, items_per_pc, date_received, transfer_id, processed_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_in = $conn->prepare($inbound_sql);
    // Types: i (item_id), i (new_qty), s (uom_val), s (expiry_date_val), s (production_date_val), s (new_dest_pallet), i (destination_warehouse), i (new_pieces), s (current_time), i (transfer_id), s (processed_by)
    $stmt_in->bind_param("iissssiisss", $item_id, $new_qty, $uom_val, $expiry_date_val, $production_date_val, $new_dest_pallet, $destination_warehouse, $new_pieces, $current_time, $transfer_id, $processed_by);
    if (!$stmt_in->execute()){
        $error = true;
        $error_message = "Failed to create new inbound record.";
    }
    $new_inbound_id = $conn->insert_id;
    $stmt_in->close();
    
    // --- Step 6: Update the transfer record with new details ---
    $upd_transfer_sql = "UPDATE transfers 
      SET source_pallet = ?, dest_pallet = ?, quantity_transferred = ?, pieces_transferred = ?, date_transferred = ?
      WHERE id = ?";
    $updStmt = $conn->prepare($upd_transfer_sql);
    $updStmt->bind_param("ssissi", $new_source_pallet, $new_dest_pallet, $new_qty, $new_pieces, $current_time, $transfer_id);
    if (!$updStmt->execute()){
        $error = true;
        $error_message = "Failed to update transfer record.";
    }
    $updStmt->close();
    
    // --- Step 7: Log the Transfer Edit in inventory_history ---
    $history_details = json_encode([
        'original' => [
            'source_pallet' => $orig_source_pallet,
            'dest_pallet' => $orig_dest_pallet,
            'quantity_transferred' => $orig_qty,
            'pieces_transferred' => $orig_pieces
        ],
        'new' => [
            'source_pallet' => $new_source_pallet,
            'dest_pallet' => $new_dest_pallet,
            'quantity_transferred' => $new_qty,
            'pieces_transferred' => $new_pieces,
            'date_transferred' => $current_time
        ]
    ]);
    $history_sql = "INSERT INTO inventory_history 
      (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, processed_by, details)
      VALUES ('transfer_edit', ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_tr = $conn->prepare($history_sql);
    // Type string: i (transfer_id), i (item_id), s (new_source_pallet), i (source_warehouse), i (new_qty), i (new_pieces), s (uom_val), s (processed_by), s (history_details)
    $stmt_tr->bind_param("iisiiisss", $transfer_id, $item_id, $new_source_pallet, $source_warehouse, $new_qty, $new_pieces, $uom_val, $processed_by, $history_details);
    $stmt_tr->execute();
    $stmt_tr->close();
    
    if ($error) {
         $conn->rollback();
         echo "<script>alert('Transfer edit failed: $error_message'); window.location='edit_transfer.php?transfer_id=$transfer_id';</script>";
         exit;
    } else {
         $conn->commit();
         echo "<script>alert('Transfer edited successfully.'); window.location='transfer_history.php';</script>";
         exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Transfer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
  body {
    padding-top: 70px; /* adjust if your navbar height differs */
  }
</style>
</head>
<body>
<div class="container mt-4">
  <h2>Edit Transfer</h2>
  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Source Pallet</label>
      <input type="text" name="source_pallet" class="form-control" value="<?= htmlspecialchars($transfer['source_pallet']); ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Destination Pallet</label>
      <input type="text" name="dest_pallet" class="form-control" value="<?= htmlspecialchars($transfer['dest_pallet']); ?>" required>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
         <label class="form-label">Crates Transferred</label>
         <input type="number" name="quantity_transferred" class="form-control" value="<?= intval($transfer['quantity_transferred']); ?>" required>
      </div>
      <div class="col-md-6 mb-3">
         <label class="form-label">Pieces Transferred</label>
         <input type="number" name="pieces_transferred" class="form-control" value="<?= intval($transfer['pieces_transferred']); ?>" required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Update Transfer</button>
    <a href="transfer_history.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
