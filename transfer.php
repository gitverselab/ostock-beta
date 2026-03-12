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
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (empty($_POST['items']) || empty($_POST['source_warehouse']) || empty($_POST['destination_warehouse'])) {
        $error_message = "Please fill out all warehouse fields and add at least one item.";
    } else {
        $source_warehouse = intval($_POST['source_warehouse']);
        $destination_warehouse = intval($_POST['destination_warehouse']);
        $items = $_POST['items'];

        if ($source_warehouse === $destination_warehouse) {
            $error_message = "Source and destination warehouses cannot be the same.";
        } else {
            $conn->begin_transaction();
            try {
                $current_time = date('Y-m-d H:i:s');
                foreach ($items as $item) {
                    $item_id = intval($item['item_id']);
                    $source_pallet = trim($item['source_pallet']);
                    $dest_pallet = (isset($item['dest_pallet']) && $item['dest_pallet'] === "manual") ? trim($item['dest_pallet_manual']) : trim($item['dest_pallet']);
                    $transfer_crates = intval($item['quantity']);
                    $transfer_pieces = intval($item['items_per_pc']);

                    if (empty($item_id) || empty($source_pallet) || empty($dest_pallet)) {
                        throw new Exception("Missing data for one of the items. Please check all fields.");
                    }
                    
                    // All your original, secure transaction logic is preserved here
                    $stmt_agg = $conn->prepare("SELECT * FROM inventory WHERE item_id = ? AND pallet_id = ? AND warehouse_id = ? ORDER BY date_received ASC");
                    $stmt_agg->bind_param("isi", $item_id, $source_pallet, $source_warehouse);
                    $stmt_agg->execute();
                    $result_agg = $stmt_agg->get_result();
                    
                    $inventoryRows = [];
                    $total_quantity = 0;
                    $total_items = 0;
                    while ($row = $result_agg->fetch_assoc()) {
                        $inventoryRows[] = $row;
                        $total_quantity += $row['quantity'];
                        $total_items += $row['items_per_pc'];
                    }
                    $stmt_agg->close();

                    if (empty($inventoryRows)) throw new Exception("Source inventory not found for item ID {$item_id} on pallet {$source_pallet}.");
                    if ($transfer_crates > $total_quantity || $transfer_pieces > $total_items) {
                        throw new Exception("Not enough stock available for item ID {$item_id} on pallet {$source_pallet}.");
                    }

                    $remaining_crates_to_deduct = $transfer_crates;
                    $remaining_pieces_to_deduct = $transfer_pieces;
                    foreach ($inventoryRows as $invRow) {
                        if ($remaining_crates_to_deduct <= 0 && $remaining_pieces_to_deduct <= 0) break;
                        $deduct_crates = min($remaining_crates_to_deduct, $invRow['quantity']);
                        $deduct_pieces = min($remaining_pieces_to_deduct, $invRow['items_per_pc']);
                        $new_qty = $invRow['quantity'] - $deduct_crates;
                        $new_pieces = $invRow['items_per_pc'] - $deduct_pieces;

                        if ($new_qty <= 0 && $new_pieces <= 0) {
                            $stmt_del = $conn->prepare("DELETE FROM inventory WHERE id = ?");
                            $stmt_del->bind_param("i", $invRow['id']);
                            if (!$stmt_del->execute()) throw new Exception("Failed to delete depleted source inventory.");
                            $stmt_del->close();
                        } else {
                            $stmt_upd = $conn->prepare("UPDATE inventory SET quantity = ?, items_per_pc = ? WHERE id = ?");
                            $stmt_upd->bind_param("iii", $new_qty, $new_pieces, $invRow['id']);
                            if (!$stmt_upd->execute()) throw new Exception("Failed to update source inventory.");
                            $stmt_upd->close();
                        }
                        $remaining_crates_to_deduct -= $deduct_crates;
                        $remaining_pieces_to_deduct -= $deduct_pieces;
                    }
                    
                    $firstRow = $inventoryRows[0];
                    $stmt_transfer = $conn->prepare("INSERT INTO transfers (item_id, source_warehouse, destination_warehouse, source_pallet, dest_pallet, quantity_transferred, pieces_transferred, date_transferred, processed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_transfer->bind_param("iiississs", $item_id, $source_warehouse, $destination_warehouse, $source_pallet, $dest_pallet, $transfer_crates, $transfer_pieces, $current_time, $processed_by);
                    if (!$stmt_transfer->execute()) throw new Exception("Failed to record transfer transaction.");
                    $transfer_id = $conn->insert_id;
                    $stmt_transfer->close();

                    $stmt_out = $conn->prepare("INSERT INTO outbound_inventory (item_id, pallet_id, quantity_removed, warehouse_id, items_per_pc, outbound_type, date_removed, transfer_id, processed_by, production_date, expiry_date) VALUES (?, ?, ?, ?, ?, 'Transfer_Outbound', ?, ?, ?, ?, ?)");
                    $stmt_out->bind_param("isiiiissss", $item_id, $source_pallet, $transfer_crates, $source_warehouse, $transfer_pieces, $current_time, $transfer_id, $processed_by, $firstRow['production_date'], $firstRow['expiry_date']);
                    if (!$stmt_out->execute()) throw new Exception("Failed to create outbound record.");
                    $stmt_out->close();

                    $stmt_in = $conn->prepare("INSERT INTO inventory (item_id, quantity, uom, expiry_date, production_date, pallet_id, warehouse_id, items_per_pc, date_received, transfer_id, processed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_in->bind_param("iissssiisss", $item_id, $transfer_crates, $firstRow['uom'], $firstRow['expiry_date'], $firstRow['production_date'], $dest_pallet, $destination_warehouse, $transfer_pieces, $current_time, $transfer_id, $processed_by);
                    if (!$stmt_in->execute()) throw new Exception("Failed to create inbound record at destination.");
                    $stmt_in->close();
                }
                $conn->commit();
                header("Location: transfer_history.php?success=true");
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = $e->getMessage();
            }
        }
    }
}
if(isset($_GET['success'])){
    $success_message = "Transfer completed successfully!";
}

// --- Now, include visual elements for display ---
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transfer Items</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-4">Transfer Items</h2>

    <?php if ($error_message): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
    <?php if ($success_message): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>

    <form method="POST" action="transfer.php">
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="source_warehouse" class="form-label">Source Warehouse</label>
                <select name="source_warehouse" id="source_warehouse" class="form-select" required>
                    <option value="">Select Source Warehouse</option>
                    <?php
                    $srcResult = $conn->query("SELECT * FROM warehouses ORDER BY name");
                    while ($warehouse = $srcResult->fetch_assoc()) {
                        echo "<option value='{$warehouse['id']}'>" . htmlspecialchars($warehouse['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="destination_warehouse" class="form-label">Destination Warehouse</label>
                <select name="destination_warehouse" id="destination_warehouse" class="form-select" required>
                    <option value="">Select Destination Warehouse</option>
                    <?php
                    $destResult = $conn->query("SELECT * FROM warehouses ORDER BY name");
                    while ($warehouse = $destResult->fetch_assoc()) {
                        echo "<option value='{$warehouse['id']}'>" . htmlspecialchars($warehouse['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
    
        <h4 class="mt-4">Items to Transfer</h4>
        <div id="items-container">
            <div class="item-entry mb-3 border p-3 rounded">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Select Item:</label>
                        <select name="items[0][item_id]" class="form-select item-select" required>
                            <option value="">-- Select Item --</option>
                            <?php
                            $itemResult = $conn->query("SELECT DISTINCT items.id, items.name FROM inventory JOIN items ON inventory.item_id = items.id ORDER BY name");
                            while ($row = $itemResult->fetch_assoc()) {
                                echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Select Source Pallet / Batch:</label>
                        <select name="items[0][source_pallet]" class="form-select source-pallet-select" required>
                            <option value="">-- Select an Item and Warehouse First --</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Destination Pallet / Batch ID:</label>
                        <div class="input-group">
                            <select name="items[0][dest_pallet]" class="form-select dest-pallet-select" required>
                                <option value="">-- Select from Existing or Choose Manual --</option>
                                <option value="manual">-- Enter Manually --</option>
                            </select>
                            <input type="text" name="items[0][dest_pallet_manual]" class="form-control d-none" placeholder="Enter destination pallet ID manually">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label primary-qty-label">Primary Quantity to Transfer</label>
                        <input type="number" name="items[0][quantity]" class="form-control" required min="0" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label secondary-qty-label">Secondary Quantity to Transfer</label>
                        <input type="number" name="items[0][items_per_pc]" class="form-control" required min="0" value="0">
                    </div>
                </div>
                <button type="button" class="btn btn-danger remove-item mt-3 btn-sm">Remove</button>
            </div>
        </div>
    
        <button type="button" class="btn btn-secondary" id="add-item">Add Another Item</button>
        <button type="submit" class="btn btn-primary">Process Transfer</button>
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
            primaryLabel.text('Primary Quantity to Transfer');
            secondaryLabel.text('Secondary Quantity to Transfer');
            return;
        }

        $.ajax({
            url: 'get_item_details_ajax.php', type: 'POST', data: { item_id: itemId }, dataType: 'json',
            success: function(response) {
                if (response.success) {
                    primaryLabel.text(`Quantity to Transfer (${response.primary_label})`);
                    secondaryLabel.text(`Quantity to Transfer (${response.secondary_label})`);
                }
            }
        });
    }

    function loadPallets(element, warehouseFieldId, palletSelectClass) {
        let itemEntry = $(element).closest('.item-entry');
        let itemId = itemEntry.find('.item-select').val();
        let warehouseId = $(warehouseFieldId).val();
        let palletSelect = itemEntry.find(palletSelectClass);

        if(itemId && warehouseId) {
            $.ajax({
                url: "get_pallets.php", type: "POST", data: { item_id: itemId, warehouse_id: warehouseId },
                success: function(data) {
                    let currentHtml = palletSelect.html().includes('value="manual"') ? '<option value="">-- Select or Choose Manual --</option>' + data + '<option value="manual">-- Enter Manually --</option>' : data;
                    palletSelect.html(currentHtml);
                }
            });
        }
    }

    $(document).on("change", ".item-select, #source_warehouse, #destination_warehouse", function() {
        let itemEntry = $(this).closest('.item-entry');
        if(!itemEntry.length) { // This handles warehouse changes affecting all rows
            $('.item-entry').each(function() {
                loadPallets($(this).find('.item-select'), '#source_warehouse', '.source-pallet-select');
                loadPallets($(this).find('.item-select'), '#destination_warehouse', '.dest-pallet-select');
                updateItemDetails(this);
            });
        } else { // This handles a change within a single item row
            loadPallets(itemEntry.find('.item-select'), '#source_warehouse', '.source-pallet-select');
            loadPallets(itemEntry.find('.item-select'), '#destination_warehouse', '.dest-pallet-select');
            updateItemDetails(itemEntry);
        }
    });
    
    $(document).on("change", ".dest-pallet-select", function() {
        let manualInput = $(this).closest(".input-group").find("input[name$='[dest_pallet_manual]']");
        if($(this).val() === "manual") {
            manualInput.removeClass("d-none").prop('required', true);
        } else {
            manualInput.addClass("d-none").prop('required', false).val('');
        }
    });
    
    $("#add-item").click(function() {
        let newItem = $(".item-entry:first").clone();
        newItem.find("select, input").each(function () {
            let name = $(this).attr("name");
            if(name){
                name = name.replace(/\[\d+\]/, "[" + itemIndex + "]");
                $(this).attr("name", name).val("");
            }
        });
        newItem.find("input[type='number']").val("0");
        newItem.find('.primary-qty-label').text('Primary Quantity to Transfer');
        newItem.find('.secondary-qty-label').text('Secondary Quantity to Transfer');
        newItem.find('.source-pallet-select').html('<option value="">-- Select an Item and Warehouse First --</option>');
        newItem.find('.dest-pallet-select').html('<option value="">-- Select or Choose Manual --</option><option value="manual">-- Enter Manually --</option>');
        newItem.find("input[name$='[dest_pallet_manual]']").addClass("d-none").prop('required', false);
        newItem.appendTo("#items-container");
        itemIndex++;
    });
    
    $(document).on("click", ".remove-item", function () {
        if ($(".item-entry").length > 1) {
            $(this).closest(".item-entry").remove();
        }
    });
});
</script>
</body>
</html>