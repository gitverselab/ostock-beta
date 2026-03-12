<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';
include 'navbar.php';

// --- Securely Build the Query ---
$sql = "SELECT
            i.name AS item_name,
            ic.category_name,
            SUM(inv.items_per_pc) AS total_pieces,
            i.cost AS cost_per_piece,
            (SUM(inv.items_per_pc) * i.cost) AS total_value
        FROM inventory inv
        JOIN items i ON inv.item_id = i.id
        LEFT JOIN item_categories ic ON i.category_id = ic.id";

$conditions = [];
$params = [];
$types = "";

if (!empty($_GET['category_id'])) {
    $conditions[] = "i.category_id = ?";
    $params[] = $_GET['category_id'];
    $types .= "i";
}
if (!empty($_GET['warehouse_id'])) {
    $conditions[] = "inv.warehouse_id = ?";
    $params[] = $_GET['warehouse_id'];
    $types .= "i";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY i.id ORDER BY total_value DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$report_data = $stmt->get_result();
$grand_total = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Valuation Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <h2>Inventory Valuation Report</h2>

    <div class="card my-3">
        <div class="card-body">
            <form class="row g-3">
                <div class="col-md-4">
                    <label for="category_id" class="form-label">Filter by Category:</label>
                    <select name="category_id" id="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php
                        $categories = $conn->query("SELECT * FROM item_categories ORDER BY category_name");
                        while($cat = $categories->fetch_assoc()) {
                            $selected = ($_GET['category_id'] ?? '') == $cat['id'] ? 'selected' : '';
                            echo "<option value='{$cat['id']}' $selected>" . htmlspecialchars($cat['category_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                     <label for="warehouse_id" class="form-label">Filter by Warehouse:</label>
                    <select name="warehouse_id" id="warehouse_id" class="form-select">
                        <option value="">All Warehouses</option>
                        <?php
                        $warehouses = $conn->query("SELECT * FROM warehouses ORDER BY name");
                        while($wh = $warehouses->fetch_assoc()) {
                            $selected = ($_GET['warehouse_id'] ?? '') == $wh['id'] ? 'selected' : '';
                            echo "<option value='{$wh['id']}' $selected>" . htmlspecialchars($wh['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th class="text-center">Total Pieces in Stock</th>
                        <th class="text-end">Cost per Piece</th>
                        <th class="text-end">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($report_data->num_rows > 0): ?>
                        <?php while($row = $report_data->fetch_assoc()): ?>
                            <?php $grand_total += $row['total_value']; ?>
                            <tr>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td><?= htmlspecialchars($row['category_name'] ?? 'N/A') ?></td>
                                <td class="text-center"><?= number_format($row['total_pieces']) ?></td>
                                <td class="text-end">₱<?= number_format($row['cost_per_piece'], 2) ?></td>
                                <td class="text-end">₱<?= number_format($row['total_value'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No inventory found matching the criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="4" class="text-end fs-5">Grand Total Value:</th>
                        <th class="text-end fs-5">₱<?= number_format($grand_total, 2) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>