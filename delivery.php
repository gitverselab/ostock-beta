<?php
// Ensure session starts only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';

// --- Handle Form Submission FIRST ---
$processed_by = $_SESSION['username'] ?? 'unknown';
date_default_timezone_set('Asia/Manila');
$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['items']) || empty($_POST['warehouse_id']) || empty($_POST['client_id'])) {
        $error_message = "Please fill out all required fields and add at least one item.";
    } else {
        $conn->begin_transaction();
        try {
            // Your original, secure PHP processing logic is preserved here
            $warehouse_id   = intval($_POST['warehouse_id']);
            $client_id      = intval($_POST['client_id']);
            $location_id    = intval($_POST['location_id']);
            $outbound_type  = trim($_POST['outbound_type']);
            $gr_number      = trim($_POST['gr_number']);
            $dr_number      = trim($_POST['dr_number']);
            $delivery_date  = date('Y-m-d', strtotime($_POST['delivery_date']));
            $items          = $_POST['items'];
            $current_time   = date('Y-m-d H:i:s');

            $stmt_delivery = $conn->prepare("INSERT INTO deliveries (warehouse_id, client_id, delivery_location_id, gr_number, dr_number, delivery_date, processed_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_delivery->bind_param("iiissss", $warehouse_id, $client_id, $location_id, $gr_number, $dr_number, $delivery_date, $processed_by);
            if (!$stmt_delivery->execute()) throw new Exception("Failed to create delivery header record.");
            $delivery_id = $conn->insert_id;
            $stmt_delivery->close();

            foreach ($items as $item) {
                $item_id = intval($item['item_id']);
                $source_pallet = trim($item['pallet_id']);
                $crates_to_remove = intval($item['quantity']);
                $pieces_to_remove = intval($item['items_per_pc']);
                
                $stmt_inv = $conn->prepare("SELECT * FROM inventory WHERE item_id = ? AND pallet_id = ? AND warehouse_id = ? FOR UPDATE");
                $stmt_inv->bind_param("isi", $item_id, $source_pallet, $warehouse_id);
                $stmt_inv->execute();
                $row = $stmt_inv->get_result()->fetch_assoc();
                $stmt_inv->close();

                if (!$row) throw new Exception("Source inventory not found for item ID {$item_id} on pallet {$source_pallet}.");
                if ($crates_to_remove > $row['quantity'] || $pieces_to_remove > $row['items_per_pc']) {
                    throw new Exception("Not enough stock available for item ID {$item_id} on pallet {$source_pallet}.");
                }

                $new_quantity = $row['quantity'] - $crates_to_remove;
                $new_items_per_pc = $row['items_per_pc'] - $pieces_to_remove;
                $inventory_id = $row['id'];

                if ($new_quantity <= 0 && $new_items_per_pc <= 0) {
                    $stmt_del_inv = $conn->prepare("DELETE FROM inventory WHERE id = ?");
                    $stmt_del_inv->bind_param("i", $inventory_id);
                    if (!$stmt_del_inv->execute()) throw new Exception("Failed to delete depleted inventory.");
                    $stmt_del_inv->close();
                } else {
                    $stmt_upd_inv = $conn->prepare("UPDATE inventory SET quantity = ?, items_per_pc = ? WHERE id = ?");
                    $stmt_upd_inv->bind_param("iii", $new_quantity, $new_items_per_pc, $inventory_id);
                    if (!$stmt_upd_inv->execute()) throw new Exception("Failed to update inventory stock.");
                    $stmt_upd_inv->close();
                }

                $stmt_outbound = $conn->prepare("INSERT INTO outbound_inventory (item_id, pallet_id, quantity_removed, warehouse_id, items_per_pc, outbound_type, date_removed, processed_by, production_date, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_outbound->bind_param("isiiisssss", $item_id, $source_pallet, $crates_to_remove, $warehouse_id, $pieces_to_remove, $outbound_type, $current_time, $processed_by, $row['production_date'], $row['expiry_date']);
                if (!$stmt_outbound->execute()) throw new Exception("Failed to create outbound record.");
                $stmt_outbound->close();

                $stmt_delivery_item = $conn->prepare("INSERT INTO delivery_items (delivery_id, item_id, pallet_id, quantity_removed, items_per_pc) VALUES (?, ?, ?, ?, ?)");
                $stmt_delivery_item->bind_param("iisii", $delivery_id, $item_id, $source_pallet, $crates_to_remove, $pieces_to_remove);
                if (!$stmt_delivery_item->execute()) throw new Exception("Failed to create delivery detail record.");
                $stmt_delivery_item->close();

                $details = json_encode(['pallet_id' => $source_pallet, 'warehouse_id' => $warehouse_id, 'quantity_removed' => $crates_to_remove, 'items_per_pc' => $pieces_to_remove, 'outbound_type' => $outbound_type, 'uom' => $row['uom']]);
                $stmt_hist = $conn->prepare("INSERT INTO inventory_history (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, processed_by, details, production_date, expiry_date) VALUES ('delivery', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_hist->bind_param("iisiiisssss", $delivery_id, $item_id, $source_pallet, $warehouse_id, $crates_to_remove, $pieces_to_remove, $row['uom'], $processed_by, $details, $row['production_date'], $row['expiry_date']);
                if (!$stmt_hist->execute()) throw new Exception("Failed to log delivery in inventory history.");
                $stmt_hist->close();
            }
            $conn->commit();
            header("Location: delivery_history.php?success=true");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}
if(isset($_GET['success'])){
    $success_message = "Delivery processed successfully!";
}

// --- Now, include visual elements for display ---
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-4">Delivery</h2>

    <?php if ($error_message): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
    <?php if ($success_message): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>

    <form method="POST" action="delivery.php">
        <div class="row mb-3">
            <div class="col-md-4"><label for="warehouse" class="form-label">Select Warehouse:</label><select name="warehouse_id" id="warehouse" class="form-select" required><option value="">Select a warehouse</option><?php $warehouseResult = $conn->query("SELECT * FROM warehouses ORDER BY name"); while ($warehouse = $warehouseResult->fetch_assoc()) { echo "<option value='{$warehouse['id']}'>" . htmlspecialchars($warehouse['name']) . "</option>"; } ?></select></div>
            <div class="col-md-4"><label for="client_id" class="form-label">Select Client:</label><select name="client_id" id="client_id" class="form-select" required><option value="">Select a Client</option><?php $clientResult = $conn->query("SELECT * FROM clients ORDER BY business_name"); while ($client = $clientResult->fetch_assoc()) { echo "<option value='{$client['id']}'>" . htmlspecialchars($client['business_name']) . "</option>"; } ?></select></div>
            <div class="col-md-4"><label for="location_id" class="form-label">Delivery Destination:</label><select name="location_id" id="location_id" class="form-select" required><option value="">Select a Client First</option></select></div>
        </div>
        <div class="row mb-3">
            <div class="col-md-3"><label for="delivery_date" class="form-label">Delivery Date:</label><input type="date" name="delivery_date" id="delivery_date" class="form-control" required></div>
            <div class="col-md-3"><label for="dr_number" class="form-label">DR Number:</label><input type="text" name="dr_number" id="dr_number" class="form-control" required></div>
            <div class="col-md-3"><label for="gr_number" class="form-label">GR Number:</label><input type="text" name="gr_number" id="gr_number" class="form-control" required></div>
            <div class="col-md-3"><label for="outbound_type" class="form-label">Outbound Type:</label><input type="text" name="outbound_type" id="outbound_type" class="form-control" value="Delivery" readonly></div>
        </div>

        <h4 class="mt-4">Items to Deliver</h4>
        <div id="items-container">
            <div class="item-entry mb-3 border p-3 rounded">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Select Item:</label><select name="items[0][item_id]" class="form-select item-select" required><option value="">-- Select Item --</option><?php $itemResult = $conn->query("SELECT DISTINCT items.id, items.name FROM inventory JOIN items ON inventory.item_id = items.id ORDER BY items.name"); while ($row = $itemResult->fetch_assoc()) { echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>"; } ?></select></div>
                    <div class="col-md-6"><label class="form-label">Select Pallet / Batch:</label><select name="items[0][pallet_id]" class="form-select pallet-select" required><option value="">-- Select an Item and Warehouse First --</option></select></div>
                    <div class="col-md-6"><label class="form-label primary-qty-label">Primary Quantity</label><input type="number" name="items[0][quantity]" class="form-control" min="0" value="0" required></div>
                    <div class="col-md-6"><label class="form-label secondary-qty-label">Secondary Quantity</label><input type="number" name="items[0][items_per_pc]" class="form-control" min="0" value="0" required></div>
                </div>
                <button type="button" class="btn btn-danger remove-item mt-3 btn-sm">Remove This Item</button>
            </div>
        </div>
        <button type="button" class="btn btn-secondary" id="add-item">Add Another Item</button>
        <button type="submit" class="btn btn-primary">Process Delivery</button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    let itemIndex = 1;

    function updateItemDetails(itemEntryElement) {
        const itemId = $(itemEntryElement).find('.item-select').val();
        const primaryLabel = $(itemEntryElement).find('.primary-qty-label');
        const secondaryLabel = $(itemEntryElement).find('.secondary-qty-label');
        if (!itemId) {
            primaryLabel.text('Primary Quantity');
            secondaryLabel.text('Secondary Quantity');
            return;
        }
        $.ajax({
            url: 'get_item_details_ajax.php', type: 'POST', data: { item_id: itemId }, dataType: 'json',
            success: function(response) {
                if (response.success) {
                    primaryLabel.text(`Quantity to Deliver (${response.primary_label})`);
                    secondaryLabel.text(`Quantity to Deliver (${response.secondary_label})`);
                }
            }
        });
    }

    function loadPallets(element) {
        let itemEntry = $(element).closest('.item-entry');
        let itemId = itemEntry.find('.item-select').val();
        let warehouseId = $("#warehouse").val();
        let palletSelect = itemEntry.find(".pallet-select");
        if (itemId && warehouseId) {
            $.ajax({
                url: "get_pallets.php", type: "POST", data: { item_id: itemId, warehouse_id: warehouseId },
                success: function(data) { palletSelect.html(data); }
            });
        } else {
            palletSelect.html('<option value="">-- Select Item and Warehouse --</option>');
        }
    }

    $("#client_id").change(function() {
        let clientId = $(this).val();
        if (clientId) {
            $.ajax({
                url: "fetch_client_locations.php", type: "POST", data: { client_id: clientId },
                success: function(data) { $("#location_id").html(data); }
            });
        } else {
            $("#location_id").html('<option value="">Select a Client First</option>');
        }
    });

    $(document).on("change", ".item-select, #warehouse", function() {
        if ($(this).attr('id') === 'warehouse') {
            $('.item-select').each(function() { 
                loadPallets(this); 
                updateItemDetails($(this).closest('.item-entry'));
            });
        } else {
            loadPallets(this);
            updateItemDetails($(this).closest('.item-entry'));
        }
    });

    $("#add-item").click(function() {
        let newItem = $(".item-entry:first").clone();
        newItem.find("select, input").each(function() {
            let name = $(this).attr("name");
            if(name){
                name = name.replace(/\[\d+\]/, "[" + itemIndex + "]");
                $(this).attr("name", name).val("");
                 if($(this).is("input[type=number]")) $(this).val("0");
            }
        });
        newItem.find('.primary-qty-label').text('Primary Quantity');
        newItem.find('.secondary-qty-label').text('Secondary Quantity');
        newItem.find('.pallet-select').html('<option value="">-- Select an Item and Warehouse First --</option>');
        newItem.appendTo("#items-container");
        itemIndex++;
    });

    $(document).on("click", ".remove-item", function() {
        if ($(".item-entry").length > 1) {
            $(this).closest(".item-entry").remove();
        } else {
            alert("You must have at least one item.");
        }
    });
});
</script>
</body>
</html>