<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';
include 'navbar.php';

// --- Handle Editable Low Stock Threshold ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['low_stock_threshold'])) {
    $_SESSION['low_stock_threshold'] = intval($_POST['low_stock_threshold']);
    header("Location: dashboard.php");
    exit;
}
$low_stock_threshold = $_SESSION['low_stock_threshold'] ?? 3000;

// --- WIDGET DATA QUERIES (FILTERED FOR FINISHED GOODS) ---

// Define the Category ID for 'Finished Good' for reuse.
$fg_category_id_result = $conn->query("SELECT id FROM item_categories WHERE category_name = 'Finished Good' LIMIT 1");
$finished_good_category_id = $fg_category_id_result ? $fg_category_id_result->fetch_assoc()['id'] : 0;


// 1. KPI Cards Data
$kpi_stmt = $conn->prepare("SELECT 
                            SUM(inv.items_per_pc) AS total_pieces, 
                            COUNT(DISTINCT inv.pallet_id) AS total_pallets, 
                            COUNT(DISTINCT inv.item_id) AS distinct_skus 
                          FROM inventory inv
                          JOIN items i ON inv.item_id = i.id
                          WHERE i.category_id = ?");
$kpi_stmt->bind_param("i", $finished_good_category_id);
$kpi_stmt->execute();
$kpi_data = $kpi_stmt->get_result()->fetch_assoc();

$warehouses_count = $conn->query("SELECT COUNT(*) as count FROM warehouses")->fetch_assoc()['count'];


// 2. Low Stock Items Widget
$low_stock_stmt = $conn->prepare("SELECT i.name, SUM(inv.items_per_pc) as total_pieces 
                                 FROM inventory inv 
                                 JOIN items i ON inv.item_id = i.id 
                                 WHERE i.category_id = ?
                                 GROUP BY inv.item_id 
                                 HAVING total_pieces <= ?
                                 ORDER BY total_pieces ASC
                                 LIMIT 5");
$low_stock_stmt->bind_param("ii", $finished_good_category_id, $low_stock_threshold);
$low_stock_stmt->execute();
$low_stock_items = $low_stock_stmt->get_result();


// 3. Items Expiring Soon Widget
$expiring_stmt = $conn->prepare("SELECT i.name, inv.pallet_id, inv.expiry_date 
                                FROM inventory inv 
                                JOIN items i ON inv.item_id = i.id 
                                WHERE inv.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
                                AND i.category_id = ?
                                ORDER BY inv.expiry_date ASC 
                                LIMIT 5");
$expiring_stmt->bind_param("i", $finished_good_category_id);
$expiring_stmt->execute();
$expiring_soon_items = $expiring_stmt->get_result();


// 4. Recent Activity Widget
$recent_inbound_stmt = $conn->prepare("SELECT h.*, i.name FROM inventory_history h JOIN items i ON h.item_id = i.id WHERE h.transaction_type LIKE '%inbound%' AND i.category_id = ? ORDER BY h.transaction_date DESC LIMIT 5");
$recent_inbound_stmt->bind_param("i", $finished_good_category_id);
$recent_inbound_stmt->execute();
$recent_inbound = $recent_inbound_stmt->get_result();

$recent_outbound_stmt = $conn->prepare("SELECT h.*, i.name FROM inventory_history h JOIN items i ON h.item_id = i.id WHERE (h.transaction_type LIKE '%outbound%' OR h.transaction_type = 'delivery') AND i.category_id = ? ORDER BY h.transaction_date DESC LIMIT 5");
$recent_outbound_stmt->bind_param("i", $finished_good_category_id);
$recent_outbound_stmt->execute();
$recent_outbound = $recent_outbound_stmt->get_result();


// 5. Chart Data: Inventory per Warehouse
$chart_stmt = $conn->prepare("SELECT w.name, SUM(inv.items_per_pc) as total_stock 
                              FROM inventory inv 
                              JOIN warehouses w ON inv.warehouse_id = w.id 
                              JOIN items i ON inv.item_id = i.id
                              WHERE i.category_id = ?
                              GROUP BY inv.warehouse_id 
                              ORDER BY total_stock DESC");
$chart_stmt->bind_param("i", $finished_good_category_id);
$chart_stmt->execute();
$chart_result = $chart_stmt->get_result();
$chart_labels = []; $chart_data = [];
while ($row = $chart_result->fetch_assoc()) {
    $chart_labels[] = $row['name'];
    $chart_data[] = $row['total_stock'];
}


// 6. Inventory Storage Tracker Table
$tracker_stmt = $conn->prepare("SELECT 
                                    items.id AS item_id, items.name, 
                                    warehouses.id AS warehouse_id, warehouses.name AS warehouse_name, 
                                    SUM(inventory.items_per_pc) AS total_items_per_pc,
                                    SUM(inventory.quantity) AS total_quantity
                                FROM inventory
                                JOIN items ON inventory.item_id = items.id
                                JOIN warehouses ON inventory.warehouse_id = warehouses.id
                                WHERE items.category_id = ?
                                GROUP BY items.id, warehouses.id
                                ORDER BY items.name, warehouses.name");
$tracker_stmt->bind_param("i", $finished_good_category_id);
$tracker_stmt->execute();
$tracker_result = $tracker_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Finished Goods</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 70px; background-color: #f8f9fa; }
        .kpi-card { border-left-width: 5px; }
        .kpi-card .card-body { font-size: 1.2rem; }
        .kpi-card .kpi-value { font-size: 2rem; font-weight: bold; }
        .widget-card .list-group-item { border: none; padding: 0.5rem 0; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <h2 class="mb-3">Finished Goods Dashboard</h2>
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card border-primary kpi-card shadow-sm h-100"><div class="card-body"><div class="row align-items-center"><div class="col"><div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Pieces in Stock</div><div class="kpi-value"><?= number_format($kpi_data['total_pieces'] ?? 0) ?></div></div><div class="col-auto"><i class="fas fa-cubes fa-2x text-gray-300"></i></div></div></div></div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card border-success kpi-card shadow-sm h-100"><div class="card-body"><div class="row align-items-center"><div class="col"><div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Pallets</div><div class="kpi-value"><?= number_format($kpi_data['total_pallets'] ?? 0) ?></div></div><div class="col-auto"><i class="fas fa-pallet fa-2x text-gray-300"></i></div></div></div></div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card border-info kpi-card shadow-sm h-100"><div class="card-body"><div class="row align-items-center"><div class="col"><div class="text-xs font-weight-bold text-info text-uppercase mb-1">Distinct SKUs</div><div class="kpi-value"><?= number_format($kpi_data['distinct_skus'] ?? 0) ?></div></div><div class="col-auto"><i class="fas fa-barcode fa-2x text-gray-300"></i></div></div></div></div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card border-warning kpi-card shadow-sm h-100"><div class="card-body"><div class="row align-items-center"><div class="col"><div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Warehouses</div><div class="kpi-value"><?= number_format($warehouses_count) ?></div></div><div class="col-auto"><i class="fas fa-warehouse fa-2x text-gray-300"></i></div></div></div></div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-6 mb-4">
            <form method="POST" action="dashboard.php" class="card shadow-sm mb-3"><div class="card-body"><div class="input-group"><span class="input-group-text">Set Low Stock Alert Threshold:</span><input type="number" name="low_stock_threshold" class="form-control" value="<?= htmlspecialchars($low_stock_threshold) ?>"><button type="submit" class="btn btn-primary">Set</button></div></div></form>
            <div class="card shadow-sm widget-card"><div class="card-header bg-danger text-white"><h6 class="m-0 font-weight-bold"><i class="fas fa-triangle-exclamation"></i> Low Stock Items (<= <?= $low_stock_threshold ?> Pieces)</h6></div><div class="card-body"><?php if ($low_stock_items->num_rows > 0): ?><ul class="list-group list-group-flush"><?php while ($item = $low_stock_items->fetch_assoc()): ?><li class="list-group-item d-flex justify-content-between align-items-center"><?= htmlspecialchars($item['name']) ?><span class="badge bg-danger rounded-pill"><?= number_format($item['total_pieces']) ?> pcs</span></li><?php endwhile; ?></ul><?php else: ?><p class="text-center text-muted">No items are low on stock. Great job!</p><?php endif; ?></div></div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm widget-card"><div class="card-header bg-warning text-dark"><h6 class="m-0 font-weight-bold"><i class="fas fa-clock"></i> Items Expiring in Next 14 Days</h6></div><div class="card-body"><?php if ($expiring_soon_items->num_rows > 0): ?><ul class="list-group list-group-flush"><?php while ($item = $expiring_soon_items->fetch_assoc()): ?><li class="list-group-item"><strong><?= htmlspecialchars($item['name']) ?></strong> (Pallet: <?= htmlspecialchars($item['pallet_id']) ?>)<span class="float-end text-muted">Expires: <?= date('M d, Y', strtotime($item['expiry_date'])) ?></span></li><?php endwhile; ?></ul><?php else: ?><p class="text-center text-muted">No items are expiring soon.</p><?php endif; ?></div></div>
        </div>
    </div>
     <div class="row">
        <div class="col-lg-7 mb-4"><div class="card shadow-sm h-100"><div class="card-header"><h6 class="m-0 font-weight-bold">Finished Goods Pieces by Warehouse</h6></div><div class="card-body"><canvas id="inventoryByWarehouseChart"></canvas></div></div></div>
        <div class="col-lg-5 mb-4"><div class="card shadow-sm h-100"><div class="card-header"><h6 class="m-0 font-weight-bold"><i class="fas fa-history"></i> Recent Activity (Finished Goods)</h6></div><div class="card-body" style="max-height: 400px; overflow-y: auto;"><h6><i class="fas fa-arrow-down text-success"></i> Recent Inbound</h6><ul class="list-group list-group-flush small"><?php while($row = $recent_inbound->fetch_assoc()): ?><li class="list-group-item"><?= htmlspecialchars($row['quantity']) ?> Crt / <?= htmlspecialchars($row['items_per_pc']) ?> Pcs of <strong><?= htmlspecialchars($row['name']) ?></strong></li><?php endwhile; ?></ul><hr><h6><i class="fas fa-arrow-up text-danger"></i> Recent Outbound/Deliveries</h6><ul class="list-group list-group-flush small"><?php while($row = $recent_outbound->fetch_assoc()): ?><li class="list-group-item"><?= htmlspecialchars($row['quantity']) ?> Crt / <?= htmlspecialchars($row['items_per_pc']) ?> Pcs of <strong><?= htmlspecialchars($row['name']) ?></strong></li><?php endwhile; ?></ul></div></div></div>
    </div>
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header"><h6 class="m-0 font-weight-bold">Finished Goods Storage Tracker</h6></div>
                <div class="card-body">
                    <div class="mb-3"><input type="text" id="trackerSearch" class="form-control" placeholder="Type to search items or warehouses..."></div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark text-center">
                                <tr><th>Item Name</th><th>Warehouse</th><th>Total Crates</th><th>Total Pieces</th><th>Action</th></tr>
                            </thead>
                            <tbody id="trackerTableBody">
                                <?php if ($tracker_result->num_rows > 0): ?>
                                    <?php while ($row = $tracker_result->fetch_assoc()): ?>
                                        <tr class='text-center'>
                                            <td><?= htmlspecialchars($row['name']) ?></td>
                                            <td><?= htmlspecialchars($row['warehouse_name']) ?></td>
                                            <td><?= htmlspecialchars($row['total_quantity']) ?></td>
                                            <td><?= htmlspecialchars($row['total_items_per_pc']) ?></td>
                                            <td>
                                                <button class='btn btn-success btn-sm' data-bs-toggle='modal' data-bs-target='#itemModal' 
                                                        data-itemid='<?= $row['item_id'] ?>' data-warehouseid='<?= $row['warehouse_id'] ?>' 
                                                        data-itemname='<?= htmlspecialchars($row['name']) ?>'>
                                                    View Pallets
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center">No Finished Goods in inventory</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="itemModalLabel">Pallet Details: <span id="modalItemName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead><tr><th>Pallet ID</th><th>Quantity (Crates)</th><th>Items Per PC</th><th>Production Date</th></tr></thead>
                        <tbody id="modalItemDetails"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Chart.js initialization
    const ctx = document.getElementById('inventoryByWarehouseChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{ label: '# of Pieces', data: <?= json_encode($chart_data) ?>, backgroundColor: 'rgba(54, 162, 235, 0.5)', borderColor: 'rgba(54, 162, 235, 1)', borderWidth: 1 }]
        },
        options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
    });

    // Modal AJAX for pallet details
    $('#itemModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var itemId = button.data('itemid');
        var warehouseId = button.data('warehouseid');
        var itemName = button.data('itemname');
        
        $('#modalItemName').text(itemName);
        $('#modalItemDetails').html('<tr><td colspan="4" class="text-center">Loading...</td></tr>');

        $.ajax({
            url: "get_item_pallets.php",
            type: "POST",
            data: { item_id: itemId, warehouse_id: warehouseId },
            success: function (data) {
                $('#modalItemDetails').html(data);
            }
        });
    });

    // Live search for the tracker table
    $("#trackerSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#trackerTableBody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
</script>
</body>
</html>