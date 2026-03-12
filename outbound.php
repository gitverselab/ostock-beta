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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['items']) || empty($_POST['warehouse_id'])) {
        $error_message = "Please select a warehouse and add at least one item for outbound processing.";
    } else {
        $warehouse_id = intval($_POST['warehouse_id']);
        $outbound_type = trim($_POST['outbound_type']);
        $items = $_POST['items'];
        $date_removed = date('Y-m-d H:i:s');
        $conn->begin_transaction();
        try {
            foreach ($items as $item) {
                $item_id = intval($item['item_id']);
                $pallet_id = trim($item['pallet_id']);
                $crates_to_remove = intval($item['quantity']);
                $pieces_to_remove = intval($item['items_per_pc']);

                if (empty($item_id) || empty($pallet_id)) {
                    throw new Exception("Invalid data for one of the items. Please check all fields.");
                }
                
                $stmt_check = $conn->prepare("SELECT id, quantity, items_per_pc, production_date, expiry_date, uom FROM inventory WHERE item_id = ? AND pallet_id = ? AND warehouse_id = ? FOR UPDATE");
                $stmt_check->bind_param("isi", $item_id, $pallet_id, $warehouse_id);
                $stmt_check->execute();
                $row = $stmt_check->get_result()->fetch_assoc();
                $stmt_check->close();

                if (!$row || $crates_to_remove > $row['quantity'] || $pieces_to_remove > $row['items_per_pc']) {
                    throw new Exception("Not enough stock available for item ID {$item_id} on pallet {$pallet_id}.");
                }
                
                $new_quantity = $row['quantity'] - $crates_to_remove;
                $new_items_per_pc = $row['items_per_pc'] - $pieces_to_remove;
                $inventory_id = $row['id'];
                
                if ($new_quantity <= 0 && $new_items_per_pc <= 0) {
                    $stmt_delete = $conn->prepare("DELETE FROM inventory WHERE id = ?");
                    $stmt_delete->bind_param("i", $inventory_id);
                    if(!$stmt_delete->execute()) throw new Exception("Failed to delete inventory record.");
                    $stmt_delete->close();
                } else {
                    $stmt_update = $conn->prepare("UPDATE inventory SET quantity = ?, items_per_pc = ? WHERE id = ?");
                    $stmt_update->bind_param("iii", $new_quantity, $new_items_per_pc, $inventory_id);
                    if(!$stmt_update->execute()) throw new Exception("Failed to update inventory record.");
                    $stmt_update->close();
                }
                
                $production_date = $row['production_date']; $expiry_date = $row['expiry_date']; $uom = $row['uom'];
                
                $stmt_outbound = $conn->prepare("INSERT INTO outbound_inventory (item_id, pallet_id, quantity_removed, warehouse_id, items_per_pc, outbound_type, date_removed, processed_by, production_date, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_outbound->bind_param("isiiisssss", $item_id, $pallet_id, $crates_to_remove, $warehouse_id, $pieces_to_remove, $outbound_type, $date_removed, $processed_by, $production_date, $expiry_date);
                if(!$stmt_outbound->execute()) throw new Exception("Failed to create outbound record.");
                $outbound_id = $conn->insert_id;
                $stmt_outbound->close();
                
                $history_details = json_encode(['pallet_id' => $pallet_id, 'warehouse_id' => $warehouse_id, 'quantity_removed' => $crates_to_remove, 'pieces_removed' => $pieces_to_remove, 'outbound_type' => $outbound_type, 'uom' => $uom]);
                $stmt_history = $conn->prepare("INSERT INTO inventory_history (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, production_date, expiry_date, processed_by, details) VALUES ('outbound', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_history->bind_param("iisiiisssss", $outbound_id, $item_id, $pallet_id, $warehouse_id, $crates_to_remove, $pieces_to_remove, $uom, $production_date, $expiry_date, $processed_by, $history_details);
                if(!$stmt_history->execute()) throw new Exception("Failed to log outbound transaction history.");
                $stmt_history->close();
            }
            $conn->commit();
            header("Location: outbound_history.php?success=true");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}
if(isset($_GET['success'])){
    $success_message = "Outbound transaction successfully recorded!";
}

// --- Now, include visual elements for display ---
include 'navbar.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Outbound Inventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <h2>Outbound Inventory</h2>

    <?php if ($error_message): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
    <?php if ($success_message): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>

    <form method="POST" id="outboundForm" action="outbound.php">
        <div class="mb-3">
            <label for="warehouse_id">Select Warehouse:</label>
            <select name="warehouse_id" id="warehouse_id" class="form-select" required>
                <option value="">Select a warehouse</option>
                <?php
                $warehouseResult = $conn->query("SELECT * FROM warehouses ORDER BY name");
                while ($warehouse = $warehouseResult->fetch_assoc()) {
                    echo "<option value='{$warehouse['id']}'>" . htmlspecialchars($warehouse['name']) . "</option>";
                }
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="outbound_type">Outbound Type:</label>
            <select name="outbound_type" id="outbound_type" class="form-select" required>
                <option value="Normal Outbound">Normal Outbound</option>
                <option value="Return to Vendor">Return to Vendor</option>
                <option value="Production Usage">Production Usage</option>
                <option value="Spoilage">Spoilage</option>
                <option value="Other">Other</option>
            </select>
        </div>
    
        <div id="items-container">
            <div class="item-entry mb-3 border p-3 rounded">
                 <div class="row g-3">
                    <div class="col-md-6">
                        <label>Select Item:</label>
                        <select name="items[0][item_id]" class="form-select item-select" required>
                            <option value="">-- Select Item --</option>
                            <?php
                            $result = $conn->query("SELECT DISTINCT items.id, items.name FROM inventory JOIN items ON inventory.item_id = items.id ORDER BY items.name");
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label>Select Pallet / Batch ID:</label>
                        <select name="items[0][pallet_id]" class="form-select pallet-select" required>
                            <option value="">-- Select an Item and Warehouse First --</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label primary-qty-label">Primary Quantity</label>
                        <input type="number" name="items[0][quantity]" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label secondary-qty-label">Secondary Quantity</label>
                        <input type="number" name="items[0][items_per_pc]" class="form-control" min="0" value="0">
                    </div>
                </div>
                <button type="button" class="btn btn-danger remove-item mt-3 btn-sm">Remove This Item</button>
            </div>
        </div>
    
        <button type="button" class="btn btn-secondary" id="add-item">Add Another Item</button>
        <button type="submit" class="btn btn-primary">Process Outbound</button>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function () {
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
                    primaryLabel.text(`Quantity to Remove (${response.primary_label})`);
                    secondaryLabel.text(`Quantity to Remove (${response.secondary_label})`);
                }
            }
        });
    }

    function loadPallets(element) {
        let itemEntry = $(element).closest('.item-entry');
        let itemId = itemEntry.find('.item-select').val();
        let warehouseId = $("#warehouse_id").val();
        let palletSelect = itemEntry.find(".pallet-select");

        if (itemId && warehouseId) {
            palletSelect.prop('disabled', true).html('<option>Loading...</option>');
            $.ajax({
                url: "get_pallets.php", type: "POST", data: { item_id: itemId, warehouse_id: warehouseId },
                success: function(data) { palletSelect.html(data); },
                error: function() { palletSelect.html('<option>Error loading pallets</option>'); },
                complete: function() { palletSelect.prop('disabled', false); }
            });
        } else {
            palletSelect.html('<option>-- Select an Item and Warehouse First --</option>');
        }
    }

    $(document).on("change", ".item-select, #warehouse_id", function() {
        if ($(this).attr('id') === 'warehouse_id') {
            $('.item-select').each(function() {
                loadPallets(this);
                updateItemDetails($(this).closest('.item-entry'));
            });
        } else {
            loadPallets(this);
            updateItemDetails($(this).closest('.item-entry'));
        }
    });

    $("#add-item").click(function () {
        let newItem = $(".item-entry:first").clone();
        newItem.find("select, input").each(function () {
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

    $(document).on("click", ".remove-item", function () {
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