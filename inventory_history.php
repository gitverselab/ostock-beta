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
  <title>Inventory History</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container-fluid mt-4">
  <h2>Inventory History</h2>
  
  <form id="filter-form" class="row g-3 mb-3">
    <div class="col-md-3">
      <label for="item_id" class="form-label">Item Name:</label>
      <select name="item_id" id="item_id" class="form-select">
        <option value="">All Items</option>
        <?php
        $items = $conn->query("SELECT id, name FROM items ORDER BY name");
        while ($item = $items->fetch_assoc()) {
          echo "<option value='{$item['id']}'>" . htmlspecialchars($item['name']) . "</option>";
        }
        ?>
      </select>
    </div>
    <div class="col-md-2">
      <label for="warehouse_id" class="form-label">Warehouse:</label>
      <select name="warehouse_id" id="warehouse_id" class="form-select">
        <option value="">All Warehouses</option>
        <?php
        $warehouses = $conn->query("SELECT id, name FROM warehouses ORDER BY name");
        while ($warehouse = $warehouses->fetch_assoc()) {
          echo "<option value='{$warehouse['id']}'>" . htmlspecialchars($warehouse['name']) . "</option>";
        }
        ?>
      </select>
    </div>
    <div class="col-md-2">
      <label for="start_date" class="form-label">Start Date:</label>
      <input type="date" name="start_date" id="start_date" class="form-control">
    </div>
    <div class="col-md-2">
      <label for="end_date" class="form-label">End Date:</label>
      <input type="date" name="end_date" id="end_date" class="form-control">
    </div>
    <div class="col-md-1">
      <label for="limit" class="form-label">Show:</label>
      <select name="limit" id="limit" class="form-select">
        <option value="20">20</option>
        <option value="50">50</option>
        <option value="100">100</option>
        <option value="500">500</option>
        <option value="ALL">ALL</option>
      </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button type="button" class="btn btn-primary w-100" id="apply-filters">Filter</button>
    </div>
  </form>
  
  <div class="table-responsive">
    <table class="table table-bordered table-striped">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>Type</th>
          <th>Ref ID</th>
          <th>Item</th>
          <th>Pallet ID</th>
          <th>Warehouse</th>
          <th>Crates</th>
          <th>Pieces</th>
          <th>Date</th>
          <th>By</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody id="inventory-data"></tbody>
    </table>
  </div>
  <div id="pagination-controls" class="mt-3"></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function loadInventoryHistory(page = 1) {
    let formData = $("#filter-form").serializeArray();
    formData.push({name: "page", value: page});

    $('#inventory-data').html("<tr><td colspan='11' class='text-center'>Loading...</td></tr>");
    
    $.ajax({
        url: "fetch_inventory_history.php",
        type: "GET",
        data: $.param(formData),
        dataType: "json",
    }).done(function(response) {
        if (response.error) {
            $('#inventory-data').html(`<tr><td colspan='11' class='text-center text-danger'>Error: ${response.message}</td></tr>`);
        } else {
            $("#inventory-data").html(response.table_rows);
            $("#pagination-controls").html(response.pagination);
        }
    }).fail(function() {
        $("#inventory-data").html("<tr><td colspan='11' class='text-center text-danger'>An error occurred while fetching data.</td></tr>");
    });
}

$(document).ready(function() {
    loadInventoryHistory(); // Initial load
    
    $("#apply-filters").click(function() {
        loadInventoryHistory();
    });

    $(document).on("click", ".page-link", function(e) {
        e.preventDefault();
        let page = $(this).data("page");
        if(page) {
            loadInventoryHistory(page);
        }
    });
});
</script>
</body>
</html>