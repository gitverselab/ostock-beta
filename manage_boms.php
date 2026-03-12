<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';

if ($_SESSION['role_id'] != 1) {
    echo "<script>alert('You do not have permission.'); window.location='dashboard.php';</script>";
    exit;
}

$action = $_GET['action'] ?? 'list';
$error_message = '';
$success_message = '';

// --- Handle Form Submissions (Add/Edit a BOM) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        $finished_good_id = intval($_POST['finished_good_item_id']);
        $bom_name = trim($_POST['bom_name']);
        $yield_quantity = intval($_POST['yield_quantity']);
        $bom_items = $_POST['bom_items'] ?? [];
        $bom_id = isset($_POST['bom_id']) ? intval($_POST['bom_id']) : null;

        if (empty($finished_good_id) || empty($bom_name) || empty($bom_items)) {
            throw new Exception("Please select a finished good, provide a BOM name, and add at least one raw material.");
        }
        if ($yield_quantity <= 0) {
            throw new Exception("Base Yield Quantity must be greater than zero.");
        }

        if ($bom_id) { // --- This is an EDIT ---
            $stmt = $conn->prepare("UPDATE boms SET finished_good_item_id = ?, bom_name = ?, yield_quantity = ? WHERE id = ?");
            $stmt->bind_param("isii", $finished_good_id, $bom_name, $yield_quantity, $bom_id);
            $action_type = "edit";
        } else { // --- This is an ADD ---
            $stmt = $conn->prepare("INSERT INTO boms (finished_good_item_id, bom_name, yield_quantity) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $finished_good_id, $bom_name, $yield_quantity);
            $action_type = "add";
        }

        if (!$stmt->execute()) throw new Exception("Failed to save BOM header.");
        if (!$bom_id) $bom_id = $conn->insert_id;
        $stmt->close();
        
        $stmt_del = $conn->prepare("DELETE FROM bom_items WHERE bom_id = ?");
        $stmt_del->bind_param("i", $bom_id);
        if (!$stmt_del->execute()) throw new Exception("Failed to clear old BOM items.");
        $stmt_del->close();

        $stmt_item = $conn->prepare("INSERT INTO bom_items (bom_id, raw_material_item_id, quantity_required) VALUES (?, ?, ?)");
        foreach ($bom_items as $item) {
            $raw_material_id = intval($item['raw_material_item_id']);
            $quantity = floatval($item['quantity_required']);
            if ($raw_material_id > 0 && $quantity > 0) {
                $stmt_item->bind_param("iid", $bom_id, $raw_material_id, $quantity);
                if (!$stmt_item->execute()) throw new Exception("Failed to save a BOM item.");
            }
        }
        $stmt_item->close();

        $conn->commit();
        header("Location: manage_boms.php?success=$action_type");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        $action = $bom_id ? 'edit' : 'add'; 
        $_GET['id'] = $bom_id;
    }
}

// --- Handle Deletion ---
if ($action === 'delete' && isset($_GET['id'])) {
    $bom_id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM boms WHERE id = ?");
    $stmt->bind_param("i", $bom_id);
    if ($stmt->execute()) {
        header("Location: manage_boms.php?success=delete");
        exit;
    } else {
        $error_message = "Failed to delete BOM.";
    }
}
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Bill of Materials (BOM)</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Manage Bill of Materials (BOM)</h2>
        <?php if ($action === 'list'): ?>
            <a href="manage_boms.php?action=add" class="btn btn-primary">Create New BOM</a>
        <?php else: ?>
            <a href="manage_boms.php" class="btn btn-secondary">Back to List</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">BOM successfully managed!</div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <?php $boms_result = $conn->query("SELECT b.*, i.name as finished_good_name, (SELECT COUNT(*) FROM bom_items bi WHERE bi.bom_id = b.id) as item_count FROM boms b JOIN items i ON b.finished_good_item_id = i.id ORDER BY bom_name"); ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>BOM Name</th>
                            <th>Finished Good</th>
                            <th class="text-center">Base Yield</th>
                            <th class="text-center">Raw Materials #</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($bom = $boms_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($bom['bom_name']) ?></td>
                            <td><?= htmlspecialchars($bom['finished_good_name']) ?></td>
                            <td class="text-center"><strong><?= htmlspecialchars($bom['yield_quantity']) ?></strong> pcs</td>
                            <td class="text-center"><?= $bom['item_count'] ?></td>
                            <td>
                                <a href="manage_boms.php?action=edit&id=<?= $bom['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="manage_boms.php?action=delete&id=<?= $bom['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <?php
        $bom_data = ['finished_good_item_id' => '', 'bom_name' => '', 'yield_quantity' => 1];
        $bom_items_data = [];
        if ($action === 'edit' && isset($_GET['id'])) {
            $bom_id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM boms WHERE id = ?");
            $stmt->bind_param("i", $bom_id);
            $stmt->execute();
            $bom_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $stmt_items = $conn->prepare("SELECT * FROM bom_items WHERE bom_id = ?");
            $stmt_items->bind_param("i", $bom_id);
            $stmt_items->execute();
            $result_items = $stmt_items->get_result();
            while($row = $result_items->fetch_assoc()) $bom_items_data[] = $row;
            $stmt_items->close();
        }
        $finished_goods = $conn->query("SELECT i.id, i.name FROM items i JOIN item_categories ic ON i.category_id = ic.id WHERE ic.category_name = 'Finished Good' ORDER BY i.name");
        $raw_materials = $conn->query("SELECT i.id, i.name, i.uom FROM items i JOIN item_categories ic ON i.category_id = ic.id WHERE ic.category_name = 'Raw Material' ORDER BY i.name");
        ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST">
                    <?php if ($action === 'edit'): ?><input type="hidden" name="bom_id" value="<?= htmlspecialchars($bom_data['id']) ?>"><?php endif; ?>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label">Finished Good:</label>
                            <select name="finished_good_item_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php $finished_goods->data_seek(0); while($fg = $finished_goods->fetch_assoc()): ?>
                                <option value="<?= $fg['id'] ?>" <?= ($fg['id'] == $bom_data['finished_good_item_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fg['name']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label">BOM Name (Recipe Name):</label>
                            <input type="text" name="bom_name" class="form-control" value="<?= htmlspecialchars($bom_data['bom_name']) ?>" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Base Yield Qty:</label>
                            <input type="number" name="yield_quantity" class="form-control" value="<?= htmlspecialchars($bom_data['yield_quantity']) ?>" min="1" required>
                        </div>
                    </div>
                    <hr>
                    <h5>Raw Materials Required (for the base yield)</h5>
                    <div id="bom-items-container">
                        <?php if (empty($bom_items_data)): ?>
                        <div class="row bom-item-row gx-2 mb-2">
                            <div class="col-md-7"><select name="bom_items[0][raw_material_item_id]" class="form-select raw-material-select" required></select></div>
                            <div class="col-md-3"><input type="number" step="0.0001" name="bom_items[0][quantity_required]" class="form-control" placeholder="Quantity" required></div>
                            <div class="col-md-2"><button type="button" class="btn btn-danger remove-item-btn w-100">Remove</button></div>
                        </div>
                        <?php else: ?>
                            <?php foreach ($bom_items_data as $index => $bom_item): ?>
                            <div class="row bom-item-row gx-2 mb-2">
                                <div class="col-md-7"><select name="bom_items[<?= $index ?>][raw_material_item_id]" class="form-select raw-material-select" data-selected-id="<?= $bom_item['raw_material_item_id'] ?>" required></select></div>
                                <div class="col-md-3"><input type="number" step="0.0001" name="bom_items[<?= $index ?>][quantity_required]" class="form-control" placeholder="Quantity" value="<?= htmlspecialchars($bom_item['quantity_required']) ?>" required></div>
                                <div class="col-md-2"><button type="button" class="btn btn-danger remove-item-btn w-100">Remove</button></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-secondary" id="add-item-btn">Add Raw Material</button>
                    <hr>
                    <button type="submit" class="btn btn-primary">Save BOM</button>
                </form>
            </div>
        </div>
        <script>
            const rawMaterials = [{ id: '', name: '-- Select Raw Material --'},<?php $raw_materials->data_seek(0); while ($rm = $raw_materials->fetch_assoc()): ?>{ id: <?= $rm['id'] ?>, name: '<?= htmlspecialchars(addslashes($rm['name']), ENT_QUOTES) ?> (<?= htmlspecialchars(addslashes($rm['uom']), ENT_QUOTES) ?>)' },<?php endwhile; ?>];
            function populateRawMaterialSelect(selectElement) {
                const selectedId = $(selectElement).data('selected-id') || '';
                let optionsHtml = '';
                rawMaterials.forEach(rm => {
                    const isSelected = (rm.id == selectedId) ? 'selected' : '';
                    optionsHtml += `<option value="${rm.id}" ${isSelected}>${rm.name}</option>`;
                });
                $(selectElement).html(optionsHtml);
            }
            $(document).ready(function() {
                let itemIndex = <?= count($bom_items_data) ?> || 1;
                $('.raw-material-select').each(function() { populateRawMaterialSelect(this); });
                $('#add-item-btn').click(function() {
                    const newRowHtml = `<div class="row bom-item-row gx-2 mb-2"><div class="col-md-7"><select name="bom_items[${itemIndex}][raw_material_item_id]" class="form-select raw-material-select" required></select></div><div class="col-md-3"><input type="number" step="0.0001" name="bom_items[${itemIndex}][quantity_required]" class="form-control" placeholder="Quantity" required></div><div class="col-md-2"><button type="button" class="btn btn-danger remove-item-btn w-100">Remove</button></div></div>`;
                    $('#bom-items-container').append(newRowHtml);
                    populateRawMaterialSelect($(`select[name="bom_items[${itemIndex}][raw_material_item_id]"]`));
                    itemIndex++;
                });
                $(document).on('click', '.remove-item-btn', function() {
                    if ($('.bom-item-row').length > 1) { $(this).closest('.bom-item-row').remove(); } 
                    else { alert("A BOM must have at least one raw material."); }
                });
            });
        </script>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>