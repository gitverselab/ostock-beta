<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';
include 'navbar.php';

$processed_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';

if (!isset($_GET['id'])) {
    die("Error: Outbound ID not specified.");
}
$outbound_id = intval($_GET['id']);

// Retrieve the original outbound record
$sql = "SELECT * FROM outbound_inventory WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $outbound_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("Error: Outbound record not found.");
}
$original = $result->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_crates = intval($_POST['quantity_removed']);
    $new_pieces = intval($_POST['items_per_pc']);
    $new_date_removed = date('Y-m-d H:i:s');
    
    $conn->begin_transaction();
    
    // --- Step 1: Reverse the Original Outbound ---
    // Look for the corresponding inventory record (by item, pallet, warehouse)
    $sql = "SELECT id, quantity, items_per_pc FROM inventory 
            WHERE item_id = ? AND pallet_id = ? AND warehouse_id = ?";
    // Assuming: item_id is integer, pallet_id is string, warehouse_id is integer.
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $original['item_id'], $original['pallet_id'], $original['warehouse_id']);
    $stmt->execute();
    $inv_result = $stmt->get_result();
    if ($row = $inv_result->fetch_assoc()) {
        // Inventory exists; add back the original outbound amounts
        $restored_qty = $row['quantity'] + $original['quantity_removed'];
        $restored_pieces = $row['items_per_pc'] + $original['items_per_pc'];
        $upd = $conn->prepare("UPDATE inventory SET quantity = ?, items_per_pc = ? WHERE id = ?");
        $upd->bind_param("iii", $restored_qty, $restored_pieces, $row['id']);
        if (!$upd->execute()) {
            $conn->rollback();
            die("Error: Failed to reverse original outbound.");
        }
        $upd->close();
        $inventory_id = $row['id'];
    } else {
        // Inventory record missing: re-create it using original outbound data
        $reAdd = $conn->prepare("INSERT INTO inventory 
            (item_id, quantity, items_per_pc, warehouse_id, pallet_id, production_date, expiry_date, date_received, processed_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $reAdd->bind_param("iiisssss", $original['item_id'], $original['quantity_removed'], $original['items_per_pc'], $original['warehouse_id'], $original['pallet_id'], $original['production_date'], $original['expiry_date'], $processed_by);
        if (!$reAdd->execute()) {
            $conn->rollback();
            die("Error: Failed to re-create deleted inventory for reversal.");
        }
        $inventory_id = $conn->insert_id;
        $reAdd->close();
    }
    $stmt->close();
    
    // --- Step 2: Check and Deduct New Outbound Amounts ---
    $stmt = $conn->prepare("SELECT id, quantity, items_per_pc FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($inv = $result->fetch_assoc()) {
        if ($new_crates > $inv['quantity'] || $new_pieces > $inv['items_per_pc']) {
            $conn->rollback();
            die("Error: Not enough stock available for new outbound values.");
        }
        $updated_qty = $inv['quantity'] - $new_crates;
        $updated_pieces = $inv['items_per_pc'] - $new_pieces;
        $upd = $conn->prepare("UPDATE inventory SET quantity = ?, items_per_pc = ? WHERE id = ?");
        $upd->bind_param("iii", $updated_qty, $updated_pieces, $inventory_id);
        if (!$upd->execute()) {
            $conn->rollback();
            die("Error: Failed to update inventory with new outbound values.");
        }
        $upd->close();
    } else {
        $conn->rollback();
        die("Error: Source inventory not found after reversal.");
    }
    $stmt->close();
    
    // --- Step 3: Update the Outbound Record ---
    $update_sql = "UPDATE outbound_inventory 
                   SET quantity_removed = ?, items_per_pc = ?, date_removed = ?, processed_by = ? 
                   WHERE id = ?";
    $updStmt = $conn->prepare($update_sql);
    $updStmt->bind_param("iissi", $new_crates, $new_pieces, $new_date_removed, $processed_by, $outbound_id);
    if (!$updStmt->execute()) {
        $conn->rollback();
        die("Error: Failed to update outbound record.");
    }
    $updStmt->close();
    
    // --- Step 4: Log the Edit in Inventory History ---
    $history_details = json_encode([
        'original' => [
            'quantity_removed' => $original['quantity_removed'],
            'items_per_pc'     => $original['items_per_pc'],
            'pallet_id'        => $original['pallet_id'],
            'production_date'  => $original['production_date'],
            'expiry_date'      => $original['expiry_date']
        ],
        'new' => [
            'quantity_removed' => $new_crates,
            'items_per_pc'     => $new_pieces,
            'date_removed'     => $new_date_removed
        ]
    ]);
    
    $history_sql = "INSERT INTO inventory_history 
                    (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, production_date, expiry_date, processed_by, details)
                    VALUES ('outbound_edit', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $historyStmt = $conn->prepare($history_sql);
    $historyStmt->bind_param(
        "iisiiisssss", 
        $outbound_id, 
        $original['item_id'], 
        $original['pallet_id'], 
        $original['warehouse_id'], 
        $new_crates, 
        $new_pieces, 
        $original['uom'], 
        $original['production_date'], 
        $original['expiry_date'], 
        $processed_by, 
        $history_details
    );
    if (!$historyStmt->execute()) {
        $conn->rollback();
        die("Error: Failed to log outbound edit in inventory history.");
    }
    $historyStmt->close();
    
    $conn->commit();
    echo "<script>alert('Outbound transaction updated successfully.'); window.location='outbound_history.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Outbound Transaction</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
  body {
    padding-top: 70px; /* adjust if your navbar height differs */
  }
</style>
</head>
<body>
<div class="container mt-4">
  <h2>Edit Outbound Transaction</h2>
  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Crates Removed:</label>
      <input type="number" name="quantity_removed" class="form-control" value="<?= htmlspecialchars($original['quantity_removed']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Pieces Removed:</label>
      <input type="number" name="items_per_pc" class="form-control" value="<?= htmlspecialchars($original['items_per_pc']) ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Update Outbound</button>
    <a href="outbound_history.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>
<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
