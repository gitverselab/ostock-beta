<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
    <title>Delivery History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 70px; }
        .table-responsive { overflow-x: auto; }
        .collapse-row { display: none; }
        .collapse-row.show { display: table-row; }
        .pagination .page-link { cursor: pointer; }
    </style>
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-4">Delivery History</h2>
  
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date:</label>
                    <input type="date" id="end_date" name="end_date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">GR Number:</label>
                    <input type="text" id="gr_number" name="gr_number" class="form-control" placeholder="Search GR #">
                </div>
                <div class="col-md-3">
                    <label class="form-label">DR Number:</label>
                    <input type="text" id="dr_number" name="dr_number" class="form-control" placeholder="Search DR #">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Show Entries:</label>
                    <select id="limit" name="limit" class="form-select">
                        <option value="20" selected>20 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-primary w-100" id="applyFilter">Filter</button>
                </div>
                 <div class="col-md-3">
                    <button type="button" class="btn btn-secondary w-100" onclick="window.print()">Print</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="deliveryTable">
            <thead class="table-dark text-center">
                <tr>
                    <th>ID</th><th>Client</th><th>Location</th><th>Warehouse</th>
                    <th>GR#</th><th>DR#</th><th>Date</th><th>By</th><th>Action</th>
                </tr>
            </thead>
            <tbody id="deliveryResultsBody"></tbody>
        </table>
    </div>

    <nav>
        <ul class="pagination justify-content-center" id="paginationControls"></ul>
    </nav>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    function loadDeliveries(page = 1) {
        let formData = $("#filterForm").serializeArray();
        formData.push({name: "page", value: page});
        
        $('#deliveryResultsBody').html('<tr><td colspan="9" class="text-center">Loading...</td></tr>');

        $.ajax({
            url: "get_filtered_deliveries.php",
            type: "POST",
            data: $.param(formData),
            dataType: 'json',
            success: function(response) {
                if(response.error){
                    $('#deliveryResultsBody').html(`<tr><td colspan="9" class="text-center text-danger">Error: ${response.message}</td></tr>`);
                    $('#paginationControls').html(''); // Clear pagination on error
                    return;
                }
                
                $("#deliveryResultsBody").html(response.table_rows);
                
                let paginationHtml = '';
                if (response.total_pages > 1) {
                    paginationHtml += `<li class="page-item ${response.page <= 1 ? 'disabled' : ''}"><a class="page-link" data-page="${response.page - 1}">Previous</a></li>`;
                    for (let i = 1; i <= response.total_pages; i++) {
                        paginationHtml += `<li class="page-item ${i === response.page ? 'active' : ''}"><a class="page-link" data-page="${i}">${i}</a></li>`;
                    }
                    paginationHtml += `<li class="page-item ${response.page >= response.total_pages ? 'disabled' : ''}"><a class="page-link" data-page="${response.page + 1}">Next</a></li>`;
                }
                $('#paginationControls').html(paginationHtml);
            },
            error: function() {
                $('#deliveryResultsBody').html('<tr><td colspan="9" class="text-center text-danger">An error occurred while fetching data.</td></tr>');
            }
        });
    }

    // Initial load
    loadDeliveries();

    // Event handlers
    $("#applyFilter").click(function(){
        loadDeliveries(1);
    });
    
    // Also filter when the "limit" dropdown changes
    $("#limit").change(function(){
        loadDeliveries(1);
    });

    $(document).on("click", ".pagination .page-link", function(e){
        e.preventDefault();
        if($(this).parent().hasClass('disabled') || $(this).parent().hasClass('active')) return;
        loadDeliveries($(this).data('page'));
    });

    $(document).on("click", ".toggleDetails", function(){
        // This logic remains unchanged
        let deliveryId = $(this).data("deliveryid");
        let detailsRow = $("#details-" + deliveryId);
        detailsRow.toggleClass('show');
        if(detailsRow.hasClass('show') && detailsRow.data('loaded') !== true) {
            $.ajax({
                url: "get_delivery_items.php",
                type: "POST",
                data: { delivery_id: deliveryId },
                success: function(response){
                    detailsRow.find('td > div').html(response);
                    detailsRow.data('loaded', true);
                },
                error: function(){
                    detailsRow.find('td > div').html('<div class="alert alert-danger">Error loading items.</div>');
                }
            });
        }
    });
});
</script>
</body>
</html>