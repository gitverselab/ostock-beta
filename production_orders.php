<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';

if ($_SESSION['role_id'] != 1) { echo "<script>alert('You do not have permission.'); window.location='dashboard.php';</script>"; exit; }

$action = $_GET['action'] ?? 'list';
$error_message = '';
$success_message = '';
$processed_by = $_SESSION['username'] ?? 'unknown';

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    $conn->begin_transaction();
    try {
        if ($post_action === 'create') {
            $bom_id = intval($_POST['bom_id']);
            $number_of_batches = intval($_POST['number_of_batches']);
            $order_date = $_POST['order_date'];
            if($number_of_batches <= 0) throw new Exception("Number of batches must be positive.");

            $stmt = $conn->prepare("INSERT INTO production_orders (bom_id, quantity_to_produce, order_date, status) VALUES (?, ?, ?, 'Pending')");
            $stmt->bind_param("iis", $bom_id, $number_of_batches, $order_date);
            if (!$stmt->execute()) throw new Exception("Failed to create production order.");
            $stmt->close();
            $success_message = "Production order created successfully!";

        } elseif ($post_action === 'edit') {
            $production_order_id = intval($_POST['production_order_id']);
            $bom_id = intval($_POST['bom_id']);
            $number_of_batches = intval($_POST['number_of_batches']);
            $order_date = $_POST['order_date'];
            if($number_of_batches <= 0) throw new Exception("Number of batches must be positive.");

            $stmt = $conn->prepare("UPDATE production_orders SET bom_id = ?, quantity_to_produce = ?, order_date = ? WHERE id = ? AND status = 'Pending'");
            $stmt->bind_param("iisi", $bom_id, $number_of_batches, $order_date, $production_order_id);
            if (!$stmt->execute()) throw new Exception("Failed to update production order. It may no longer be in 'Pending' status.");
            $stmt->close();
            $success_message = "Production order updated successfully!";
            
        } elseif ($post_action === 'cancel') {
            $production_order_id = intval($_POST['production_order_id']);
            $stmt = $conn->prepare("UPDATE production_orders SET status = 'Canceled' WHERE id = ? AND status IN ('Pending', 'In Progress')");
            $stmt->bind_param("i", $production_order_id);
            if (!$stmt->execute()) throw new Exception("Failed to cancel order.");
            $stmt->close();
            $success_message = "Production order has been canceled.";

        } elseif ($post_action === 'delete') {
            $production_order_id = intval($_POST['production_order_id']);
            $stmt = $conn->prepare("DELETE FROM production_orders WHERE id = ? AND status = 'Pending'");
            $stmt->bind_param("i", $production_order_id);
            if (!$stmt->execute()) throw new Exception("Failed to delete order.");
            if ($stmt->affected_rows === 0) throw new Exception("Only 'Pending' orders can be deleted.");
            $stmt->close();
            $success_message = "Production order has been permanently deleted.";

        } elseif ($post_action === 'execute') {
            $production_order_id = intval($_POST['production_order_id']);
            $actual_yield = intval($_POST['actual_yield']);
            $new_status = $_POST['new_status'];
            $raw_materials_usage = $_POST['usage'] ?? [];
            
            $stmt_update = $conn->prepare("UPDATE production_orders SET actual_yield = ?, status = ? WHERE id = ?");
            $stmt_update->bind_param("isi", $actual_yield, $new_status, $production_order_id);
            if (!$stmt_update->execute()) throw new Exception("Failed to update order status.");
            $stmt_update->close();

            if ($new_status === 'Completed') {
                $order_info_stmt = $conn->prepare("SELECT po.*, b.finished_good_item_id FROM production_orders po JOIN boms b ON po.bom_id = b.id WHERE po.id = ?");
                $order_info_stmt->bind_param("i", $production_order_id);
                $order_info_stmt->execute();
                $order_info = $order_info_stmt->get_result()->fetch_assoc();
                if ($order_info['completion_date'] != NULL) throw new Exception("This order has already been completed and inventory was updated.");
                $order_info_stmt->close();

                foreach ($raw_materials_usage as $usage_data) {
                    $item_id = intval($usage_data['item_id']);
                    $actual_used = floatval($usage_data['actual_used']);
                    
                    // NOTE: This is a simplified stock deduction. A real-world system needs more complex FIFO logic.
                    $stmt_out = $conn->prepare("INSERT INTO outbound_inventory (item_id, pallet_id, items_per_pc, quantity_removed, warehouse_id, outbound_type, date_removed, processed_by) VALUES (?, 'PRODUCTION', ?, 0, 1, 'Production Usage', NOW(), ?)");
                    $stmt_out->bind_param("ids", $item_id, $actual_used, $processed_by);
                    if (!$stmt_out->execute()) throw new Exception("Failed to create outbound record for raw material ID {$item_id}.");
                    $stmt_out->close();

                    $stmt_usage = $conn->prepare("INSERT INTO production_order_usage (production_order_id, raw_material_item_id, planned_quantity, actual_quantity_used) VALUES (?, ?, ?, ?)");
                    $stmt_usage->bind_param("iidd", $production_order_id, $item_id, floatval($usage_data['planned_qty']), $actual_used);
                    if (!$stmt_usage->execute()) throw new Exception("Failed to log usage variance.");
                    $stmt_usage->close();
                }

                $stmt_inbound = $conn->prepare("INSERT INTO inventory (item_id, items_per_pc, quantity, uom, date_received, processed_by, pallet_id) VALUES (?, ?, 1, 'PCS', NOW(), ?, ?)");
                $new_pallet_id = 'PROD-' . date('Ymd') . '-' . $production_order_id;
                $stmt_inbound->bind_param("iiss", $order_info['finished_good_item_id'], $actual_yield, $processed_by, $new_pallet_id);
                if (!$stmt_inbound->execute()) throw new Exception("Failed to add finished good to inventory.");
                $stmt_inbound->close();
                
                $stmt_complete = $conn->prepare("UPDATE production_orders SET completion_date = NOW() WHERE id = ?");
                $stmt_complete->bind_param("i", $production_order_id);
                $stmt_complete->execute();
                $stmt_complete->close();
                $success_message = "Production order completed and inventory updated!";
            } else {
                $success_message = "Production order status updated!";
            }
        }
        $conn->commit();
        if ($success_message) {
             header("Location: production_orders.php?success=true&msg=" . urlencode($success_message));
             exit;
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Production Orders</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Production Orders</h2>
        <?php if ($action === 'list'): ?><a href="production_orders.php?action=create" class="btn btn-primary">Create Production Order</a><?php else: ?><a href="production_orders.php" class="btn btn-secondary">Back to List</a><?php endif; ?>
    </div>
    
    <?php if ($error_message): ?><div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div><?php endif; ?>
    <?php if (isset($_GET['success'])): ?><div class="alert alert-success"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>

    <?php if ($action === 'list'): ?>
        <?php $orders_result = $conn->query("SELECT po.*, b.bom_name, b.yield_quantity, i.name as finished_good_name FROM production_orders po JOIN boms b ON po.bom_id = b.id JOIN items i ON b.finished_good_item_id = i.id ORDER BY po.order_date DESC, po.id DESC"); ?>
        <div class="card shadow-sm"><div class="card-body">
        <table class="table table-hover">
            <thead class="table-dark"><tr><th>Order ID</th><th>Date</th><th>Finished Good</th><th class="text-center">Batches</th><th class="text-center">Planned Yield</th><th class="text-center">Actual Yield</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php while($order = $orders_result->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $order['id'] ?></td>
                    <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                    <td><?= htmlspecialchars($order['finished_good_name']) ?></td>
                    <td class="text-center"><?= $order['quantity_to_produce'] ?></td>
                    <td class="text-center"><?= $order['yield_quantity'] * $order['quantity_to_produce'] ?></td>
                    <td class="text-center"><?= $order['actual_yield'] ?? 'N/A' ?></td>
                    <td>
                        <?php 
                            $status_color = 'secondary';
                            if ($order['status'] === 'Completed') $status_color = 'success';
                            if ($order['status'] === 'In Progress') $status_color = 'primary';
                            if ($order['status'] === 'Canceled') $status_color = 'danger';
                        ?>
                        <span class="badge bg-<?= $status_color ?>"><?= htmlspecialchars($order['status']) ?></span>
                    </td>
                    <td>
                        <a href="production_orders.php?action=execute&id=<?= $order['id'] ?>" class="btn btn-sm btn-info">View</a>
                        <?php if ($order['status'] === 'Pending'): ?>
                            <a href="production_orders.php?action=edit&id=<?= $order['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                            <form action="production_orders.php" method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="production_order_id" value="<?= $order['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        <?php endif; ?>
                         <?php if ($order['status'] === 'Pending' || $order['status'] === 'In Progress'): ?>
                             <form action="production_orders.php" method="POST" class="d-inline">
                                <input type="hidden" name="action" value="cancel"><input type="hidden" name="production_order_id" value="<?= $order['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Are you sure?')">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div></div>

    <?php elseif ($action === 'create' || $action === 'edit'): ?>
        <?php
        $is_editing = ($action === 'edit');
        $form_action_url = "production_orders.php";
        $order = ['bom_id' => '', 'quantity_to_produce' => 1, 'order_date' => date('Y-m-d')];
        if ($is_editing && isset($_GET['id'])) {
            $order_id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM production_orders WHERE id = ? AND status = 'Pending'");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $order_data = $stmt->get_result()->fetch_assoc();
            if ($order_data) { $order = $order_data; } else { die("Order not found or cannot be edited."); }
        }
        ?>
        <div class="card shadow-sm"><div class="card-body">
            <h5 class="card-title"><?= $is_editing ? 'Edit' : 'Create New' ?> Production Order</h5>
            <form method="POST" action="<?= $form_action_url ?>">
                <input type="hidden" name="action" value="<?= $is_editing ? 'edit' : 'create' ?>">
                <?php if($is_editing): ?><input type="hidden" name="production_order_id" value="<?= $order['id'] ?>"><?php endif; ?>
                <div class="row align-items-end">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Select BOM (Recipe):</label>
                        <select name="bom_id" id="bom_id_select" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php 
                            $boms = $conn->query("SELECT b.id, b.bom_name, i.name as finished_good_name, b.yield_quantity FROM boms b JOIN items i ON b.finished_good_item_id = i.id ORDER BY b.bom_name");
                            while($bom = $boms->fetch_assoc()): ?>
                                <option value="<?= $bom['id'] ?>" data-yield="<?= $bom['yield_quantity'] ?>" <?= ($bom['id'] == $order['bom_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bom['bom_name']) . " (Yields: {$bom['yield_quantity']} " . htmlspecialchars($bom['finished_good_name']) . ")" ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Number of Batches to Run:</label>
                        <input type="number" id="number_of_batches" name="number_of_batches" class="form-control" required min="1" value="<?= htmlspecialchars($order['quantity_to_produce']) ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Order Date:</label>
                        <input type="date" name="order_date" class="form-control" required value="<?= htmlspecialchars(date('Y-m-d', strtotime($order['order_date']))) ?>">
                    </div>
                </div>
                <div class="alert alert-info">Total Planned Yield: <strong id="total_planned_yield">0</strong> pcs</div>
                <button type="submit" class="btn btn-primary"><?= $is_editing ? 'Update Order' : 'Create Order' ?></button>
            </form>
        </div></div>

    <?php elseif ($action === 'execute'): ?>
        <?php 
        $order_id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT po.*, b.bom_name, b.yield_quantity, i.name as finished_good_name FROM production_orders po JOIN boms b ON po.bom_id = b.id JOIN items i ON b.finished_good_item_id = i.id WHERE po.id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$order) die("Order not found.");
        $bom_items_stmt = $conn->prepare("SELECT bi.*, i.name, i.uom FROM bom_items bi JOIN items i ON bi.raw_material_item_id = i.id WHERE bi.bom_id = ?");
        $bom_items_stmt->bind_param("i", $order['bom_id']);
        $bom_items_stmt->execute();
        $bom_items = $bom_items_stmt->get_result();
        $planned_yield = $order['yield_quantity'] * $order['quantity_to_produce'];
        ?>
        <div class="card shadow-sm"><div class="card-body">
            <h5 class="card-title">Executing Production Order #<?= $order['id'] ?></h5>
            <p><strong>Producing:</strong> <?= htmlspecialchars($order['finished_good_name']) ?><br><strong>BOM/Recipe:</strong> <?= htmlspecialchars($order['bom_name']) ?></p>
            <form method="POST">
                <input type="hidden" name="action" value="execute"><input type="hidden" name="production_order_id" value="<?= $order['id'] ?>">
                <h6>1. Production Yield</h6>
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="form-label">Total Planned Yield</label><input type="text" class="form-control" value="<?= $planned_yield ?>" readonly></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Actual Yield (Update Here)</label><input type="number" name="actual_yield" class="form-control" value="<?= htmlspecialchars($order['actual_yield'] ?? $planned_yield) ?>" required></div>
                </div><hr>
                <h6>2. Raw Material Usage (Update Variance Here)</h6>
                <table class="table">
                    <thead><tr><th>Raw Material</th><th>Total Planned Qty</th><th>Actual Qty Used</th></tr></thead>
                    <tbody>
                    <?php while($item = $bom_items->fetch_assoc()): 
                        $planned_qty = $item['quantity_required'] * $order['quantity_to_produce'];
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= $planned_qty ?> <?= htmlspecialchars($item['uom']) ?></td>
                            <td>
                                <input type="hidden" name="usage[<?= $item['raw_material_item_id'] ?>][item_id]" value="<?= $item['raw_material_item_id'] ?>">
                                <input type="hidden" name="usage[<?= $item['raw_material_item_id'] ?>][planned_qty]" value="<?= $planned_qty ?>">
                                <input type="number" step="0.0001" name="usage[<?= $item['raw_material_item_id'] ?>][actual_used]" class="form-control" value="<?= $planned_qty ?>" required>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table><hr>
                <h6>3. Update Status</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">New Status</label>
                        <select name="new_status" class="form-select" <?= $order['status'] === 'Completed' ? 'disabled' : '' ?>>
                            <option value="Pending" <?= $order['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="In Progress" <?= $order['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="Completed" <?= $order['status'] === 'Completed' ? 'selected' : '' ?>>Completed (Update Inventory)</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" <?= $order['status'] === 'Completed' ? 'disabled' : '' ?>>Save Changes</button>
                 <?php if($order['status'] === 'Completed'): ?> <span class="ms-2 text-success fw-bold">This order is complete.</span><?php endif; ?>
            </form>
        </div></div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function(){
        function calculateTotalYield() {
            const selectedBom = $('#bom_id_select option:selected');
            const baseYield = selectedBom.data('yield') || 0;
            const batches = $('#number_of_batches').val() || 0;
            const totalYield = baseYield * batches;
            $('#total_planned_yield').text(totalYield);
        }
        $('#bom_id_select, #number_of_batches').on('change keyup', calculateTotalYield);
        calculateTotalYield();
    });
</script>
</body>
</html>