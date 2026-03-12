<?php 
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php'; 
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outbound History</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .table-responsive { overflow-x: auto; }
        @media print {
            body * { visibility: hidden; }
            #print-section, #print-section * { visibility: visible; }
            #print-section { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
    <style>
  body {
    padding-top: 70px; /* adjust if your navbar height differs */
  }
</style>
</head>
<body>

<div class="container mt-4">
    <h2>Outbound Inventory History</h2>
    <a href="outbound" class="btn btn-secondary mt-3">Return to Outbound</a>

    <!-- FILTER FORM -->
    <form id="filter-form" class="row g-3 mt-3">
        <div class="col-md-3">
            <label for="item_id" class="form-label">Item Name:</label>
            <select name="item_id" id="item_id" class="form-control">
                <option value="">All Items</option>
                <?php
                $items = $conn->query("SELECT id, name FROM items ORDER BY name");
                while ($item = $items->fetch_assoc()) {
                    echo "<option value='{$item['id']}'>{$item['name']}</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="warehouse_id" class="form-label">Warehouse:</label>
            <select name="warehouse_id" id="warehouse_id" class="form-control">
                <option value="">All Warehouses</option>
                <?php
                $warehouses = $conn->query("SELECT id, name FROM warehouses ORDER BY name");
                while ($warehouse = $warehouses->fetch_assoc()) {
                    echo "<option value='{$warehouse['id']}'>{$warehouse['name']}</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="start_date" class="form-label">Start Date:</label>
            <input type="date" name="start_date" id="start_date" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="end_date" class="form-label">End Date:</label>
            <input type="date" name="end_date" id="end_date" class="form-control">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="button" class="btn btn-primary" id="apply-filters">Filter</button>
            <button type="button" class="btn btn-success ms-2" onclick="printReport()">Print</button>
        </div>
        <div class="col-md-3">
            <label for="limit" class="form-label">Show Entries:</label>
            <select name="limit" id="limit" class="form-control">
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="500">500</option>
                <option value="ALL">ALL</option>
            </select>
        </div>
    </form>

    <!-- OUTBOUND HISTORY TABLE -->
    <div class="table-responsive mt-3" id="print-section">
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Item Name</th>
                    <th>Warehouse</th>
                    <th>Pallet ID</th>
                    <th>Crates Removed</th>
                    <th>Pieces Removed</th>
                    <th>Production Date</th>
                    <th>Expiry Date</th>
                    <th>Outbound Type</th>
                    <th>Date Removed</th>
                    <th>Processed By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="outbound-data">
                <tr><td colspan="11" class="text-center">Select filters and click "Filter" to view results.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- PRINT FUNCTION & AJAX -->
<script>
function printReport() {
    window.print();
}

$(document).ready(function() {
    function loadOutboundHistory() {
        let formData = $("#filter-form").serialize();
        $.ajax({
            url: "fetch_outbound_history.php",
            type: "GET",
            data: formData,
            beforeSend: function() {
                $("#outbound-data").html("<tr><td colspan='11' class='text-center'>Loading...</td></tr>");
            },
            success: function(response) {
                $("#outbound-data").html(response);
            },
            error: function() {
                $("#outbound-data").html("<tr><td colspan='11' class='text-center'>Error loading data.</td></tr>");
            }
        });
    }

    $("#apply-filters").click(function() {
        loadOutboundHistory();
    });

    $("#limit").change(function() {
        loadOutboundHistory();
    });

    loadOutboundHistory(); // Load data initially
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
