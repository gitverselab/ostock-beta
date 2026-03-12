<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';
include 'navbar.php';

// Ensure inventory_history table exists with columns: 
// id, transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, production_date, expiry_date, transaction_date, processed_by, details

if (!isset($_GET['id'])) {
    die("Error: Inbound ID not specified.");
}
$inbound_id = intval($_GET['id']);

// Retrieve the original inbound record
$sql = "SELECT * FROM inventory WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $inbound_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("Error: Inbound record not found.");
}
$original = $result->fetch_assoc();
$stmt->close();

// Display edit form and process submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_quantity = intval($_POST['quantity']);
    $new_items_per_pc = intval($_POST['items_per_pc']);
    $new_uom = $_POST['uom'];
    $new_expiry_date = date('Y-m-d H:i:s', strtotime($_POST['expiry_date']));
    $new_production_date = date('Y-m-d H:i:s', strtotime($_POST['production_date']));
    $new_pallet_id = $_POST['pallet_id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if the inbound record is still intact (i.e. not partially consumed)
    if ($original['quantity'] != $new_quantity || $original['items_per_pc'] != $new_items_per_pc) {
        $conn->rollback();
        die("Error: This inbound transaction has been partially consumed and cannot be edited.");
    }
    
    // Get current processed_by from session
    $processed_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';
    
    // Update the inventory record
    $upd_sql = "UPDATE inventory 
                SET quantity = ?, 
                    items_per_pc = ?, 
                    uom = ?, 
                    expiry_date = ?, 
                    production_date = ?, 
                    pallet_id = ?, 
                    processed_by = ? 
                WHERE id = ?";
    $updStmt = $conn->prepare($upd_sql);
    $updStmt->bind_param("iisssssi", $new_quantity, $new_items_per_pc, $new_uom, $new_expiry_date, $new_production_date, $new_pallet_id, $processed_by, $inbound_id);
    if (!$updStmt->execute()) {
        $conn->rollback();
        die("Error: Failed to update inbound record.");
    }
    $updStmt->close();
    
    // Prepare details for inventory_history log
    $history_details = json_encode([
        'old' => [
            'quantity'      => $original['quantity'],
            'items_per_pc'  => $original['items_per_pc'],
            'uom'           => $original['uom'],
            'expiry_date'   => $original['expiry_date'],
            'production_date'=> $original['production_date'],
            'pallet_id'     => $original['pallet_id']
        ],
        'new' => [
            'quantity'      => $new_quantity,
            'items_per_pc'  => $new_items_per_pc,
            'uom'           => $new_uom,
            'expiry_date'   => $new_expiry_date,
            'production_date'=> $new_production_date,
            'pallet_id'     => $new_pallet_id
        ]
    ]);
    
    // Insert a history record indicating the edit (transaction_type "inbound_edit")
    $history_sql = "INSERT INTO inventory_history 
                    (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, production_date, expiry_date, processed_by, details)
                    VALUES ('inbound_edit', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($history_sql);
    $stmt->bind_param("iisiiisssss", $inbound_id, $original['item_id'], $new_pallet_id, $original['warehouse_id'], $new_quantity, $new_items_per_pc, $new_uom, $new_production_date, $new_expiry_date, $processed_by, $history_details);
    if (!$stmt->execute()) {
        $conn->rollback();
        die("Error: Failed to log inbound edit.");
    }
    $stmt->close();
    
    $conn->commit();
    echo "<script>alert('Inbound transaction updated successfully.'); window.location='inbound_history.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Inbound Transaction</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap 5 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
  body {
    padding-top: 70px; /* adjust if your navbar height differs */
  }
</style>
</head>
<body>
<div class="container mt-4">
  <h2>Edit Inbound Transaction</h2>
  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Quantity (Crates):</label>
      <input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars($original['quantity']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Items Per PC:</label>
      <input type="number" name="items_per_pc" class="form-control" value="<?= htmlspecialchars($original['items_per_pc']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Unit of Measure:</label>
      <input type="text" name="uom" class="form-control" value="<?= htmlspecialchars($original['uom']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Production Date:</label>
      <input type="date" name="production_date" class="form-control" value="<?= date('Y-m-d', strtotime($original['production_date'])) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Expiry Date:</label>
      <input type="date" name="expiry_date" class="form-control" value="<?= date('Y-m-d', strtotime($original['expiry_date'])) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Pallet ID:</label>
      <input type="text" name="pallet_id" class="form-control" value="<?= htmlspecialchars($original['pallet_id']) ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Update Inbound</button>
    <a href="inbound_history.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>
<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
