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
        $error_message = "Please select a warehouse and add at least one item.";
    } else {
        $warehouse_id = intval($_POST['warehouse_id']);
        $items = $_POST['items'];
        $date_received = date('Y-m-d H:i:s');
        $conn->begin_transaction();
        try {
            foreach ($items as $item) {
                $item_id = intval($item['item_id']);
                $quantity = intval($item['quantity']);
                $uom = trim($item['uom']);
                $items_per_pc = (isset($item['items_per_pc']) && $item['items_per_pc'] !== '') ? intval($item['items_per_pc']) : 0;
                $expiry_date = date('Y-m-d H:i:s', strtotime($item['expiry_date']));
                $production_date = date('Y-m-d H:i:s', strtotime($item['production_date']));
                $pallet_id = trim($item['pallet_id']);

                if (empty($item_id) || empty($pallet_id)) { throw new Exception("Invalid data for one of the items."); }

                $stmt_check = $conn->prepare("SELECT id, quantity, items_per_pc FROM inventory WHERE item_id = ? AND pallet_id = ? AND warehouse_id = ?");
                $stmt_check->bind_param("isi", $item_id, $pallet_id, $warehouse_id);
                $stmt_check->execute();
                $existing = $stmt_check->get_result()->fetch_assoc();
                $stmt_check->close();

                $inventory_id = 0;
                if ($existing) {
                    $new_quantity = $existing['quantity'] + $quantity;
                    $new_items_per_pc = $existing['items_per_pc'] + $items_per_pc;
                    $inventory_id = $existing['id'];
                    $stmt_update = $conn->prepare("UPDATE inventory SET quantity = ?, items_per_pc = ?, date_received = ?, processed_by = ? WHERE id = ?");
                    $stmt_update->bind_param("iissi", $new_quantity, $new_items_per_pc, $date_received, $processed_by, $inventory_id);
                    if (!$stmt_update->execute()) { throw new Exception("Failed to update existing inventory record."); }
                    $stmt_update->close();
                } else {
                    $stmt_insert = $conn->prepare("INSERT INTO inventory (item_id, quantity, uom, expiry_date, production_date, pallet_id, warehouse_id, items_per_pc, date_received, processed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_insert->bind_param("iissssiiss", $item_id, $quantity, $uom, $expiry_date, $production_date, $pallet_id, $warehouse_id, $items_per_pc, $date_received, $processed_by);
                    if (!$stmt_insert->execute()) { throw new Exception("Failed to insert new inventory record."); }
                    $inventory_id = $conn->insert_id;
                    $stmt_insert->close();
                }

                $history_details = json_encode(['pallet_id' => $pallet_id, 'warehouse_id' => $warehouse_id, 'quantity' => $quantity, 'items_per_pc' => $items_per_pc, 'uom' => $uom, 'expiry_date' => $expiry_date, 'production_date' => $production_date]);
                $stmt_history = $conn->prepare("INSERT INTO inventory_history (transaction_type, reference_id, item_id, pallet_id, warehouse_id, quantity, items_per_pc, uom, production_date, expiry_date, processed_by, details) VALUES ('inbound', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_history->bind_param("iisiiisssss", $inventory_id, $item_id, $pallet_id, $warehouse_id, $quantity, $items_per_pc, $uom, $production_date, $expiry_date, $processed_by, $history_details);
                if (!$stmt_history->execute()) { throw new Exception("Failed to log transaction in inventory history."); }
                $stmt_history->close();
            }
            $conn->commit();
            header("Location: inbound_history.php?success=true");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}
if (isset($_GET['success'])) {
    $success_message = "Inbound transaction successfully recorded!";
}

// --- Now, include visual elements for display ---
include 'navbar.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Inbound Inventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <h2>Inbound Inventory</h2>

    <?php if ($error_message): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
    <?php if ($success_message): ?><div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div><?php endif; ?>

    <form method="POST" action="inbound.php">
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
    
        <div id="items-container">
            <div class="item-entry mb-3 border p-3 rounded">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label>Select Item:</label>
                        <select name="items[0][item_id]" class="form-select item-select" required>
                            <option value="">Select an item</option>
                            <?php
                            $result = $conn->query("SELECT * FROM items ORDER BY name");
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label>Pallet / Batch ID:</label>
                        <div class="input-group">
                            <input type="text" name="items[0][pallet_id]" class="form-control" required>
                            <button type="button" class="btn btn-outline-secondary generate-pallet-btn">Generate</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label primary-qty-label">Primary Quantity</label>
                        <input type="number" name="items[0][quantity]" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label secondary-qty-label">Secondary Quantity</label>
                        <input type="number" name="items[0][items_per_pc]" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                         <label>UOM:</label>
                        <input type="text" name="items[0][uom]" class="form-control uom-input" readonly required>
                    </div>
                    <div class="col-md-6">
                        <label>Production Date:</label>
                        <input type="date" name="items[0][production_date]" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>Expiry Date:</label>
                        <input type="date" name="items[0][expiry_date]" class="form-control" required>
                    </div>
                </div>
                <button type="button" class="btn btn-danger remove-item mt-3 btn-sm">Remove This Item</button>
            </div>
        </div>
    
        <button type="button" class="btn btn-secondary" id="add-item">Add Another Item</button>
        <button type="submit" class="btn btn-primary">Submit Inbound</button>
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
        const uomInput = $(itemEntryElement).find('.uom-input');

        if (!itemId) {
            primaryLabel.text('Primary Quantity');
            secondaryLabel.text('Secondary Quantity');
            uomInput.val('');
            return;
        }

        $.ajax({
            url: 'get_item_details_ajax.php',
            type: 'POST',
            data: { item_id: itemId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    primaryLabel.text(`Quantity (${response.primary_label})`);
                    secondaryLabel.text(`Quantity (${response.secondary_label})`);
                    uomInput.val(response.uom);
                }
            }
        });
    }

    $(document).on('change', '.item-select', function () {
        updateItemDetails($(this).closest('.item-entry'));
    });

    $("#add-item").click(function () {
        let newItem = $(".item-entry:first").clone();
        newItem.find("input, select").each(function () {
            let name = $(this).attr("name");
            if(name) {
                name = name.replace(/\[\d+\]/, "[" + itemIndex + "]");
                $(this).attr("name", name);
                if($(this).is('select')) { $(this).val(''); }
                else { $(this).val($(this).is("input[type=number]") ? "0" : ""); }
            }
        });
        newItem.find('.primary-qty-label').text('Primary Quantity');
        newItem.find('.secondary-qty-label').text('Secondary Quantity');
        newItem.find('.uom-input').val('');
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
    
    $(document).on("click", ".generate-pallet-btn", function() {
        let button = $(this);
        let itemEntry = button.closest('.item-entry');
        let itemId = itemEntry.find('.item-select').val();
        
        if (!itemId) { alert("Please select an item first."); return; }
        button.prop('disabled', true).text('Generating...');

        $.ajax({
            url: "generate_pallet.php", type: "POST", dataType: "json", data: { item_id: itemId },
            success: function(response) {
                if (response.error) { alert(response.error); } 
                else if (response.pallet_id) { itemEntry.find("input[name$='[pallet_id]']").val(response.pallet_id); }
            },
            error: function() { alert("Error generating pallet ID."); },
            complete: function() { button.prop('disabled', false).text('Generate'); }
        });
    });
});
</script>
</body>
</html>