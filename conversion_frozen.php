<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';
include 'navbar.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form inputs
    $warehouse_id     = $_POST['warehouse_id'];
    $chilled_item_id  = $_POST['chilled_item_id'];
    $frozen_item_id   = $_POST['frozen_item_id'];
    // Selected inventory records (array of inventory IDs to convert)
    $selected_inventory = isset($_POST['inventory_ids']) ? $_POST['inventory_ids'] : [];
    
    $current_time = date('Y-m-d H:i:s');
    $processed_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';
    
    // Begin transaction
    $conn->begin_transaction();
    $error = false;
    $error_message = "";
    
    foreach ($selected_inventory as $inv_id) {
        // Get the inventory record from inventory table (chilled record)
        $sql = "SELECT * FROM inventory WHERE id = ? AND warehouse_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $inv_id, $warehouse_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            $error = true;
            $error_message = "Inventory record (ID: $inv_id) not found.";
            break;
        }
        $inventory = $result->fetch_assoc();
        $stmt->close();
        
        // Save original values
        $quantity = $inventory['quantity'];
        $items_per_pc = $inventory['items_per_pc'];
        $pallet_id = $inventory['pallet_id'];
        $production_date = $inventory['production_date'];
        $expiry_date = $inventory['expiry_date'];
        $uom = $inventory['uom'];
        
        // Create outbound record for the conversion (removing the chilled item)
        $outbound_sql = "INSERT INTO outbound_inventory 
                        (item_id, pallet_id, quantity_removed, warehouse_id, items_per_pc, outbound_type, date_removed, transfer_id, processed_by, production_date, expiry_date)
                        VALUES (?, ?, ?, ?, ?, 'Frozen Conversion Outbound', ?, NULL, ?, ?, ?)";
        $stmt_out = $conn->prepare($outbound_sql);
        $stmt_out->bind_param("isiiissss", $chilled_item_id, $pallet_id, $quantity, $warehouse_id, $items_per_pc, $current_time, $processed_by, $production_date, $expiry_date);
        if (!$stmt_out->execute()) {
            $error = true;
            $error_message = "Failed to create outbound record for inventory ID $inv_id.";
            break;
        }
        $outbound_id = $conn->insert_id;
        $stmt_out->close();
        
        // Delete the original inventory record (chilled)
        $del_sql = "DELETE FROM inventory WHERE id = ?";
        $stmt_del = $conn->prepare($del_sql);
        $stmt_del->bind_param("i", $inv_id);
        if (!$stmt_del->execute()) {
            $error = true;
            $error_message = "Failed to delete chilled inventory record (ID: $inv_id).";
            break;
        }
        $stmt_del->close();
        
        // Insert a new inventory record with the frozen item
        $inbound_sql = "INSERT INTO inventory 
                        (item_id, quantity, uom, expiry_date, production_date, pallet_id, warehouse_id, items_per_pc, date_received, transfer_id, processed_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)";
        $stmt_in = $conn->prepare($inbound_sql);
        $stmt_in->bind_param("iisssiisss", $frozen_item_id, $quantity, $uom, $expiry_date, $production_date, $pallet_id, $warehouse_id, $items_per_pc, $current_time, $processed_by);
        if (!$stmt_in->execute()) {
            $error = true;
            $error_message = "Failed to insert frozen inventory record for pallet $pallet_id.";
            break;
        }
        $inbound_id = $conn->insert_id;
        $stmt_in->close();
        
        // Insert a row into frozen_transactions table to log the conversion
        $frozen_sql = "INSERT INTO frozen_transactions 
                        (warehouse_id, chilled_item_id, frozen_item_id, inventory_id, pallet_id, quantity, items_per_pc, production_date, expiry_date, date_converted, processed_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_frozen = $conn->prepare($frozen_sql);
        $stmt_frozen->bind_param("iiisiiissss", $warehouse_id, $chilled_item_id, $frozen_item_id, $inv_id, $pallet_id, $quantity, $items_per_pc, $production_date, $expiry_date, $current_time, $processed_by);
        if (!$stmt_frozen->execute()) {
            $error = true;
            $error_message = "Failed to log frozen transaction for pallet $pallet_id.";
            break;
        }
        $stmt_frozen->close();
        
        // Log the outbound conversion in inventory_history
        $history_details_out = json_encode([
            'pallet_id' => $pallet_id,
            'warehouse_id' => $warehouse_id,
            'quantity_removed' => $quantity,
            'items_per_pc' => $items_per_pc,
            'uom' => $uom,
            'production_date' => $production_date,
            'expiry_date' => $expiry_date
        ]);
        $history_sql_out = "INSERT INTO inventory_history 
                            (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, production_date, expiry_date, processed_by, details)
                            VALUES ('frozen_conversion_outbound', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_hist_out = $conn->prepare($history_sql_out);
        $stmt_hist_out->bind_param("iisiiisssss", $outbound_id, $chilled_item_id, $pallet_id, $warehouse_id, $quantity, $items_per_pc, $uom, $production_date, $expiry_date, $processed_by, $history_details_out);
        $stmt_hist_out->execute();
        $stmt_hist_out->close();
        
        // Log the inbound conversion in inventory_history
        $history_details_in = json_encode([
            'pallet_id' => $pallet_id,
            'warehouse_id' => $warehouse_id,
            'quantity' => $quantity,
            'items_per_pc' => $items_per_pc,
            'uom' => $uom,
            'production_date' => $production_date,
            'expiry_date' => $expiry_date
        ]);
        $history_sql_in = "INSERT INTO inventory_history 
                           (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, production_date, expiry_date, processed_by, details)
                           VALUES ('frozen_conversion_inbound', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_hist_in = $conn->prepare($history_sql_in);
        $stmt_hist_in->bind_param("iisiiisssss", $inbound_id, $frozen_item_id, $pallet_id, $warehouse_id, $quantity, $items_per_pc, $uom, $production_date, $expiry_date, $processed_by, $history_details_in);
        $stmt_hist_in->execute();
        $stmt_hist_in->close();
    }
    
    if ($error) {
        $conn->rollback();
        echo "<script>alert('Frozen Conversion failed: $error_message'); window.location='conversion_frozen.php';</script>";
        exit;
    } else {
        $conn->commit();
        echo "<script>alert('Frozen Conversion processed successfully!'); window.location='conversion_frozen.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Frozen Conversion</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap 5 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    /* Additional styling if needed */
  </style>
  <style>
  body {
    padding-top: 70px; /* adjust if your navbar height differs */
  }
</style>
</head>
<body>
<div class="container mt-4">
  <h2>Frozen Conversion</h2>
  
  <!-- Create Transaction Form -->
  <form method="POST">
    <div class="row mb-3">
      <div class="col-md-4">
        <label for="warehouse_id" class="form-label">Select Warehouse:</label>
        <select name="warehouse_id" id="warehouse_id" class="form-control" required>
          <option value="">Select Warehouse</option>
          <?php
          $whResult = $conn->query("SELECT * FROM warehouses ORDER BY name");
          while ($wh = $whResult->fetch_assoc()) {
              echo "<option value='{$wh['id']}'>{$wh['name']}</option>";
          }
          ?>
        </select>
      </div>
      <div class="col-md-4">
        <label for="chilled_item_id" class="form-label">Select Chilled Item:</label>
        <select name="chilled_item_id" id="chilled_item_id" class="form-control" required>
          <option value="">Select Chilled Item</option>
          <?php
          // For example, filter items with "CHILLED" in their name (adjust as needed)
          $chilledItems = $conn->query("SELECT * FROM items WHERE name LIKE '%CHILLED%' ORDER BY name");
          while ($item = $chilledItems->fetch_assoc()) {
              echo "<option value='{$item['id']}'>{$item['name']}</option>";
          }
          ?>
        </select>
      </div>
      <div class="col-md-4">
        <label for="frozen_item_id" class="form-label">Select Frozen Item:</label>
        <select name="frozen_item_id" id="frozen_item_id" class="form-control" required>
          <option value="">Select Frozen Item</option>
          <?php
          // For example, filter items with "FROZEN" in their name
          $frozenItems = $conn->query("SELECT * FROM items WHERE name LIKE '%FROZEN%' ORDER BY name");
          while ($item = $frozenItems->fetch_assoc()) {
              echo "<option value='{$item['id']}'>{$item['name']}</option>";
          }
          ?>
        </select>
      </div>
    </div>
    
    <!-- Load available inventory records for the selected chilled item (via AJAX) -->
    <div class="mb-3">
      <label class="form-label">Select Pallet(s) to Convert:</label>
      <div id="inventoryList">
        <!-- This div will be filled with checkboxes listing inventory records -->
        <p>Please select a chilled item and warehouse to load available pallets.</p>
      </div>
    </div>
    
    <button type="submit" class="btn btn-success">Create Frozen Conversion Transaction</button>
  </form>
  
  <hr>
  <h3>Frozen Conversion History</h3>
  <table class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr class="text-center">
        <th>ID</th>
        <th>Chilled Item ID</th>
        <th>Frozen Item ID</th>
        <th>Pallet ID</th>
        <th>Quantity</th>
        <th>Items Per PC</th>
        <th>Production Date</th>
        <th>Expiry Date</th>
        <th>Date Converted</th>
        <th>Processed By</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php
      // Fetch history from frozen_transactions table
      $history = $conn->query("SELECT * FROM frozen_transactions ORDER BY date_converted DESC");
      if ($history && $history->num_rows > 0) {
          while ($row = $history->fetch_assoc()) {
              echo "<tr class='text-center'>
                      <td>" . htmlspecialchars($row['id']) . "</td>
                      <td>" . htmlspecialchars($row['chilled_item_id']) . "</td>
                      <td>" . htmlspecialchars($row['frozen_item_id']) . "</td>
                      <td>" . htmlspecialchars($row['pallet_id']) . "</td>
                      <td>" . htmlspecialchars($row['quantity']) . "</td>
                      <td>" . htmlspecialchars($row['items_per_pc']) . "</td>
                      <td>" . htmlspecialchars($row['production_date']) . "</td>
                      <td>" . htmlspecialchars($row['expiry_date']) . "</td>
                      <td>" . htmlspecialchars($row['date_converted']) . "</td>
                      <td>" . htmlspecialchars($row['processed_by']) . "</td>
                      <td>
                        <a href='edit_frozen.php?id=" . $row['id'] . "' class='btn btn-warning btn-sm'>Edit</a>
                        <a href='delete_frozen.php?id=" . $row['id'] . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                      </td>
                    </tr>";
          }
      } else {
          echo "<tr><td colspan='11' class='text-center'>No frozen conversion transactions found.</td></tr>";
      }
      ?>
    </tbody>
  </table>
</div>

<!-- jQuery to load available inventory for the selected chilled item -->
<script>
$(document).ready(function(){
    $("#chilled_item_id, #warehouse_id").change(function(){
        let warehouseId = $("#warehouse_id").val();
        let chilledItemId = $("#chilled_item_id").val();
        if (warehouseId && chilledItemId) {
            $.ajax({
                url: "get_chilled_inventory.php",
                type: "POST",
                data: { warehouse_id: warehouseId, item_id: chilledItemId },
                success: function(data) {
                    $("#inventoryList").html(data);
                },
                error: function() {
                    $("#inventoryList").html("<p class='text-danger'>Error loading inventory records.</p>");
                }
            });
        } else {
            $("#inventoryList").html("<p>Please select both warehouse and chilled item.</p>");
        }
    });
});
</script>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
