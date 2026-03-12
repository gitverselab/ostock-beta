<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Only admins (role_id 1) can view this page.
if ($_SESSION['role_id'] != 1) {
    echo "<script>alert('You do not have permission to access this page.'); window.location='index.php';</script>";
    exit;
}

include 'db.php';
include 'navbar.php';

// Fetch distinct transaction types for the filter dropdown
$transaction_types = $conn->query("SELECT DISTINCT transaction_type FROM deleted_transactions ORDER BY transaction_type");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Transaction History</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
      body { padding-top: 70px; }
      pre { background-color: #f8f9fa; padding: 1rem; border-radius: .25rem; white-space: pre-wrap; word-break: break-all; }
    </style>
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-4">Deleted Transaction History</h2>
    
    <div class="card my-3">
        <div class="card-body">
            <form id="filter-form" class="row g-3">
                <div class="col-md-3">
                    <label for="transaction_type" class="form-label">Transaction Type</label>
                    <select id="transaction_type" name="transaction_type" class="form-select">
                        <option value="">All Types</option>
                        <?php while($type = $transaction_types->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($type['transaction_type']) ?>"><?= htmlspecialchars($type['transaction_type']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="deleted_by" class="form-label">Deleted By (Username)</label>
                    <input type="text" id="deleted_by" name="deleted_by" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" id="apply-filters" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
          <thead class="table-dark text-center">
              <tr>
                  <th>ID</th>
                  <th>Type</th>
                  <th>Original ID</th>
                  <th>Deleted By</th>
                  <th>Deleted Date</th>
                  <th>Details</th>
              </tr>
          </thead>
          <tbody id="deleted-data-body">
              </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    function loadDeletedHistory() {
        const formData = $("#filter-form").serialize();
        $('#deleted-data-body').html("<tr><td colspan='6' class='text-center'>Loading...</td></tr>");
        
        $.ajax({
            url: "fetch_deleted_history.php",
            type: "GET",
            data: formData,
            dataType: "json"
        }).done(function(response) {
            if (response.error) {
                $('#deleted-data-body').html(`<tr><td colspan='6' class='text-center text-danger'>Error: ${response.message}</td></tr>`);
            } else {
                $("#deleted-data-body").html(response.table_rows);
            }
        }).fail(function() {
            $("#deleted-data-body").html("<tr><td colspan='6' class='text-center text-danger'>An error occurred while fetching data.</td></tr>");
        });
    }

    // Initial load
    loadDeletedHistory();

    // Trigger load on filter button click
    $("#apply-filters").click(function() {
        loadDeletedHistory();
    });
});
</script>
</body>
</html>