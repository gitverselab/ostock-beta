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
  <title>Inventory Report</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap 5 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    .table-responsive { overflow-x: auto; }
    /* Adjust the Details column width */
    .details-col { width: 40%; }
    /* When printing, hide elements marked with .no-print */
    @media print {
      .no-print { display: none !important; }
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
  <h2>Inventory Report</h2>
  <a href="index.php" class="btn btn-secondary mt-3 no-print">Return to Dashboard</a>

  <!-- Filter Form (always visible) -->
  <form id="filter-form" class="row g-3 mt-3 no-print">
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
      <div class="col-md-3 d-flex align-items-end">
          <button type="button" class="btn btn-primary me-2" id="apply-filters">Filter</button>
          <button type="button" class="btn btn-success" id="printBtn">Print</button>
      </div>
  </form>

  <!-- Tabs for Summary and Detailed Table -->
  <ul class="nav nav-tabs mt-4 no-print" id="inventoryTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab" aria-controls="summary" aria-selected="true">Summary</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="detailed-tab" data-bs-toggle="tab" data-bs-target="#detailed" type="button" role="tab" aria-controls="detailed" aria-selected="false">Detailed Table</button>
    </li>
  </ul>
  <div class="tab-content" id="inventoryTabsContent">
    <!-- Summary Tab -->
    <div class="tab-pane fade show active" id="summary" role="tabpanel" aria-labelledby="summary-tab">
      <div id="summaryContent" class="mt-3">
          <!-- Summary data will be loaded via AJAX -->
          <p class="text-center">Loading summary...</p>
      </div>
    </div>
    <!-- Detailed Table Tab -->
    <div class="tab-pane fade" id="detailed" role="tabpanel" aria-labelledby="detailed-tab">
      <div class="table-responsive mt-3" id="print-section-detailed">
        <table class="table table-bordered">
          <thead class="table-dark">
            <tr>
              <th>ITEM DESCRIPTION</th>
              <th>QUANTITY</th>
              <th>UOM</th>
              <th>ITEMS PER PC</th>
              <th>PRODUCTION DATE</th>
              <th>EXPIRY DATE</th>
              <th>PALLET ID</th>
              <th>WAREHOUSE</th>
              <th>DATE RECEIVED</th>
            </tr>
          </thead>
          <tbody id="inventory-data-detailed">
            <tr><td colspan="9" class="text-center">Select filters and click "Filter" to view results.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap Bundle JS (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Custom print function to print only the active tab's content.
function printReport() {
    // Get the HTML content of the active tab
    var activeContent = document.querySelector('.tab-pane.active').innerHTML;
    // Open a new window for printing
    var printWindow = window.open('', '', 'width=800,height=600');
    // Write the HTML content along with a link to the Bootstrap CSS and any inline styles
    printWindow.document.write(`
        <html>
          <head>
            <title>Print</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
            <style>
              body { margin: 20px; }
            </style>
          </head>
          <body>
            ${activeContent}
          </body>
        </html>
    `);
    printWindow.document.close();
    // Wait until the new window finishes loading all content (including CSS)
    printWindow.onload = function() {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    };
}


$(document).ready(function() {
    // Function to load summary view data via AJAX
    function loadSummary() {
        $.ajax({
            url: "fetch_inventory_summary.php",
            type: "GET",
            beforeSend: function() {
                $("#summaryContent").html("<p class='text-center'>Loading summary...</p>");
            },
            success: function(response) {
                $("#summaryContent").html(response);
            },
            error: function() {
                $("#summaryContent").html("<p class='text-center text-danger'>Error loading summary data.</p>");
            }
        });
    }
    
    // Function to load detailed table view data via AJAX
    function loadDetailed() {
        let formData = $("#filter-form").serialize();
        $.ajax({
            url: "fetch_inventory_report.php",
            type: "GET",
            data: formData,
            beforeSend: function() {
                $("#inventory-data-detailed").html("<tr><td colspan='9' class='text-center'>Loading...</td></tr>");
            },
            success: function(response) {
                $("#inventory-data-detailed").html(response);
            },
            error: function() {
                $("#inventory-data-detailed").html("<tr><td colspan='9' class='text-center text-danger'>Error loading data.</td></tr>");
            }
        });
    }
    
    // Load data for active tab when filters are applied
    $("#apply-filters").click(function() {
        if ($("#summary-tab").hasClass("active")) {
            loadSummary();
        } else {
            loadDetailed();
        }
    });
    
    // When switching tabs, load the corresponding data if not already loaded
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        let target = $(e.target).attr("data-bs-target");
        if (target === "#summary") {
            loadSummary();
        } else if (target === "#detailed") {
            loadDetailed();
        }
    });
    
    // Initial load for the default active tab (Summary)
    loadSummary();
    
    // Bind print button
    $("#printBtn").click(function(){
        printReport();
    });
});
</script>
</body>
</html>
<?php
$conn->close();
?>
