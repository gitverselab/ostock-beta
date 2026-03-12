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
    $warehouse_id      = $_POST['warehouse_id'];
    $frozen_item_id    = $_POST['frozen_item_id'];
    $chilled_item_id   = $_POST['chilled_item_id'];
    // Selected inventory records (array of inventory IDs to convert)
    $selected_inventory = isset($_POST['inventory_ids']) ? $_POST['inventory_ids'] : [];

    $current_time = date('Y-m-d H:i:s');
    $processed_by = isset($_SESSION['username']) ? $_SESSION['username'] : 'unknown';

    // Begin transaction
    $conn->begin_transaction();
    $error = false;
    $error_message = "";

    foreach ($selected_inventory as $inv_id) {
        // 1) Get the inventory record from inventory table (this time, a FROZEN record)
        $sql = "SELECT * FROM inventory WHERE id = ? AND warehouse_id = ? AND item_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $inv_id, $warehouse_id, $frozen_item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            $error = true;
            $error_message = "Frozen inventory record (ID: $inv_id) not found.";
            $stmt->close();
            break;
        }
        $inventory = $result->fetch_assoc();
        $stmt->close();

        // Save original values
        $quantity        = $inventory['quantity'];
        $items_per_pc    = $inventory['items_per_pc'];
        $pallet_id       = $inventory['pallet_id'];
        $production_date = $inventory['production_date'];
        $expiry_date     = $inventory['expiry_date'];
        $uom             = $inventory['uom'];

        // 2) Create outbound record for the conversion (removing the FROZEN item)
        $outbound_sql = "INSERT INTO outbound_inventory 
                        (item_id, pallet_id, quantity_removed, warehouse_id, items_per_pc, outbound_type, date_removed, transfer_id, processed_by, production_date, expiry_date)
                        VALUES (?, ?, ?, ?, ?, 'Chilled Conversion Outbound', ?, NULL, ?, ?, ?)";
        $stmt_out = $conn->prepare($outbound_sql);
        $stmt_out->bind_param(
            "isiiissss",
            $frozen_item_id,
            $pallet_id,
            $quantity,
            $warehouse_id,
            $items_per_pc,
            $current_time,
            $processed_by,
            $production_date,
            $expiry_date
        );
        if (!$stmt_out->execute()) {
            $error = true;
            $error_message = "Failed to create outbound record for inventory ID $inv_id.";
            $stmt_out->close();
            break;
        }
        $outbound_id = $conn->insert_id;
        $stmt_out->close();

        // 3) Delete the original inventory record (the FROZEN one)
        $del_sql = "DELETE FROM inventory WHERE id = ?";
        $stmt_del = $conn->prepare($del_sql);
        $stmt_del->bind_param("i", $inv_id);
        if (!$stmt_del->execute()) {
            $error = true;
            $error_message = "Failed to delete frozen inventory record (ID: $inv_id).";
            $stmt_del->close();
            break;
        }
        $stmt_del->close();

        // 4) Insert a new inventory record with the CHILLED item
        $inbound_sql = "INSERT INTO inventory 
                        (item_id, quantity, uom, expiry_date, production_date, pallet_id, warehouse_id, items_per_pc, date_received, transfer_id, processed_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)";
        $stmt_in = $conn->prepare($inbound_sql);
        $stmt_in->bind_param(
            "iisssiisss",
            $chilled_item_id,
            $quantity,
            $uom,
            $expiry_date,
            $production_date,
            $pallet_id,
            $warehouse_id,
            $items_per_pc,
            $current_time,
            $processed_by
        );
        if (!$stmt_in->execute()) {
            $error = true;
            $error_message = "Failed to insert chilled inventory record for pallet $pallet_id.";
            $stmt_in->close();
            break;
        }
        $inbound_id = $conn->insert_id;
        $stmt_in->close();

        // 5) Insert a row into chilled_transactions table to log the conversion
        $chilled_sql = "INSERT INTO chilled_transactions 
                        (warehouse_id, frozen_item_id, chilled_item_id, inventory_id, pallet_id, quantity, items_per_pc, production_date, expiry_date, date_converted, processed_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_chill = $conn->prepare($chilled_sql);
        $stmt_chill->bind_param(
            "iiiisiiisss",
            $warehouse_id,
            $frozen_item_id,
            $chilled_item_id,
            $inv_id,
            $pallet_id,
            $quantity,
            $items_per_pc,
            $production_date,
            $expiry_date,
            $current_time,
            $processed_by
        );
        if (!$stmt_chill->execute()) {
            $error = true;
            $error_message = "Failed to log chilled transaction for pallet $pallet_id.";
            $stmt_chill->close();
            break;
        }
        $stmt_chill->close();

        // 6) Log the outbound conversion in inventory_history
        $history_details_out = json_encode([
            'pallet_id'       => $pallet_id,
            'warehouse_id'    => $warehouse_id,
            'quantity_removed'=> $quantity,
            'items_per_pc'    => $items_per_pc,
            'uom'             => $uom,
            'production_date' => $production_date,
            'expiry_date'     => $expiry_date
        ]);
        $history_sql_out = "INSERT INTO inventory_history 
                            (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, production_date, expiry_date, processed_by, details)
                            VALUES ('chilled_conversion_outbound', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_hist_out = $conn->prepare($history_sql_out);
        $stmt_hist_out->bind_param(
            "iisiiisssss",
            $outbound_id,
            $frozen_item_id,
            $pallet_id,
            $warehouse_id,
            $quantity,
            $items_per_pc,
            $uom,
            $production_date,
            $expiry_date,
            $processed_by,
            $history_details_out
        );
        $stmt_hist_out->execute();
        $stmt_hist_out->close();

        // 7) Log the inbound conversion in inventory_history
        $history_details_in = json_encode([
            'pallet_id'       => $pallet_id,
            'warehouse_id'    => $warehouse_id,
            'quantity'        => $quantity,
            'items_per_pc'    => $items_per_pc,
            'uom'             => $uom,
            'production_date' => $production_date,
            'expiry_date'     => $expiry_date
        ]);
        $history_sql_in = "INSERT INTO inventory_history 
                           (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, production_date, expiry_date, processed_by, details)
                           VALUES ('chilled_conversion_inbound', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_hist_in = $conn->prepare($history_sql_in);
        $stmt_hist_in->bind_param(
            "iisiiisssss",
            $inbound_id,
            $chilled_item_id,
            $pallet_id,
            $warehouse_id,
            $quantity,
            $items_per_pc,
            $uom,
            $production_date,
            $expiry_date,
            $processed_by,
            $history_details_in
        );
        $stmt_hist_in->execute();
        $stmt_hist_in->close();
    }

    if ($error) {
        $conn->rollback();
        echo "<script>alert('Chilled Conversion failed: $error_message'); window.location='conversion_chilled.php';</script>";
        exit;
    } else {
        $conn->commit();
        echo "<script>alert('Chilled Conversion processed successfully!'); window.location='conversion_chilled.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Chilled Conversion</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
  body {
    padding-top: 70px; /* adjust if your navbar height differs */
  }
</style>
</head>
<body>
<div class="container mt-4">
  <h2>Chilled Conversion</h2>
  
  <!-- Conversion Form -->
  <form method="POST">
    <div class="row mb-3">
      <!-- Warehouse selector -->
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

      <!-- Frozen Item selector -->
      <div class="col-md-4">
        <label for="frozen_item_id" class="form-label">Select Frozen Item:</label>
        <select name="frozen_item_id" id="frozen_item_id" class="form-control" required>
          <option value="">Select Frozen Item</option>
          <?php
          // Filter items with "FROZEN" in their name
          $frozenItems = $conn->query("SELECT * FROM items WHERE name LIKE '%FROZEN%' ORDER BY name");
          while ($item = $frozenItems->fetch_assoc()) {
              echo "<option value='{$item['id']}'>{$item['name']}</option>";
          }
          ?>
        </select>
      </div>

      <!-- Chilled Item selector -->
      <div class="col-md-4">
        <label for="chilled_item_id" class="form-label">Select Chilled Item:</label>
        <select name="chilled_item_id" id="chilled_item_id" class="form-control" required>
          <option value="">Select Chilled Item</option>
          <?php
          // Filter items with "CHILLED" in their name
          $chilledItems = $conn->query("SELECT * FROM items WHERE name LIKE '%CHILLED%' ORDER BY name");
          while ($item = $chilledItems->fetch_assoc()) {
              echo "<option value='{$item['id']}'>{$item['name']}</option>";
          }
          ?>
        </select>
      </div>
    </div>
    
    <!-- Load available frozen inventory records for the selected frozen item -->
    <div class="mb-3">
      <label class="form-label">Select Pallet(s) to Convert:</label>
      <div id="inventoryList">
        <p>Please select both warehouse and frozen item to load available pallets.</p>
      </div>
    </div>
    
    <button type="submit" class="btn btn-success">Create Chilled Conversion Transaction</button>
  </form>
  
  <hr>
  <h3>Chilled Conversion History</h3>
  <table class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr class="text-center">
        <th>ID</th>
        <th>Frozen Item ID</th>
        <th>Chilled Item ID</th>
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
      // Fetch history from chilled_transactions table
      $history = $conn->query("SELECT * FROM chilled_transactions ORDER BY date_converted DESC");
      if ($history && $history->num_rows > 0) {
          while ($row = $history->fetch_assoc()) {
              echo "<tr class='text-center'>
                      <td>" . htmlspecialchars($row['id']) . "</td>
                      <td>" . htmlspecialchars($row['frozen_item_id']) . "</td>
                      <td>" . htmlspecialchars($row['chilled_item_id']) . "</td>
                      <td>" . htmlspecialchars($row['pallet_id']) . "</td>
                      <td>" . htmlspecialchars($row['quantity']) . "</td>
                      <td>" . htmlspecialchars($row['items_per_pc']) . "</td>
                      <td>" . htmlspecialchars($row['production_date']) . "</td>
                      <td>" . htmlspecialchars($row['expiry_date']) . "</td>
                      <td>" . htmlspecialchars($row['date_converted']) . "</td>
                      <td>" . htmlspecialchars($row['processed_by']) . "</td>
                      <td>
                        <a href='edit_chilled.php?id=" . $row['id'] . "' class='btn btn-warning btn-sm'>Edit</a>
                        <a href='delete_chilled.php?id=" . $row['id'] . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                      </td>
                    </tr>";
          }
      } else {
          echo "<tr><td colspan='11' class='text-center'>No chilled conversion transactions found.</td></tr>";
      }
      ?>
    </tbody>
  </table>
</div>

<!-- jQuery to load available frozen inventory for the selected frozen item -->
<script>
$(document).ready(function(){
    $("#frozen_item_id, #warehouse_id").change(function(){
        let warehouseId = $("#warehouse_id").val();
        let frozenItemId = $("#frozen_item_id").val();
        if (warehouseId && frozenItemId) {
            $.ajax({
                url: "get_frozen_inventory.php",
                type: "POST",
                data: { warehouse_id: warehouseId, item_id: frozenItemId },
                success: function(data) {
                    $("#inventoryList").html(data);
                },
                error: function() {
                    $("#inventoryList").html("<p class='text-danger'>Error loading inventory records.</p>");
                }
            });
        } else {
            $("#inventoryList").html("<p>Please select both warehouse and frozen item.</p>");
        }
    });
});
</script>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
