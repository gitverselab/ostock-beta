<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';
include 'navbar.php';

// Get items and locations for dropdowns, preserving your original custom order
$itemsArray = $conn->query("SELECT id, name FROM items ORDER BY FIELD(name, 'LECHE FLAN - MI - CHILLED', 'LECHE FLAN - RETAIL - CHILLED', 'UBE HALAYA - MI - CHILLED', 'BANANA CHILLED', 'LANGKA CHILLED', 'BEANS CHILLED', 'MONGGO CHILLED', 'UBE HALAYA - MI - FROZEN', 'MACAPUNO FROZEN', 'MONGGO FROZEN', 'BEANS FROZEN')")->fetch_all(MYSQLI_ASSOC);
$locationsArray = $conn->query("SELECT id, location FROM client_locations ORDER BY location")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Schedule Delivery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 70px; }
        .remove-row { cursor: pointer; color: red; }
    </style>
</head>
<body>
<div class="container my-4">
    <h1 class="mb-4">Schedule Delivery</h1>
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Add / Edit Schedule</h5></div>
        <div class="card-body">
            <form id="delivery-form">
                <input type="hidden" id="delivery_id" name="delivery_id" value="">
                <div class="mb-3">
                    <label for="delivery_date" class="form-label">Delivery Date</label>
                    <input type="date" class="form-control" id="delivery_date" name="delivery_date" required>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="is_additional" name="is_additional" value="1">
                    <label class="form-check-label" for="is_additional">Additional Order</label>
                </div>
                <h6>Items</h6>
                <table class="table table-bordered" id="items_table">
                    <thead><tr><th>Item</th><th>Quantity</th><th>Status</th><th>Location</th><th>Action</th></tr></thead>
                    <tbody></tbody>
                </table>
                <button type="button" id="add_row" class="btn btn-secondary">Add Item</button>
                <hr>
                <button type="submit" class="btn btn-primary">Save Schedule</button>
                <button type="button" id="clear_form" class="btn btn-light">Clear & New</button>
            </form>
        </div>
    </div>
    
    <hr class="my-4">
    
    <div class="row mb-3">
        <div class="col-md-3">
            <label for="filter_start_date" class="form-label">Start Date</label>
            <input type="date" id="filter_start_date" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="filter_end_date" class="form-label">End Date</label>
            <input type="date" id="filter_end_date" class="form-control">
        </div>
        <div class="col-md-3 align-self-end">
            <button id="filter_button" class="btn btn-primary">Filter</button>
        </div>
    </div>
    
    <h2>Scheduled Deliveries</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="scheduled-deliveries">
            <thead class="table-dark"><tr><th>ID</th><th>Date</th><th>Items</th><th>Actions</th></tr></thead>
            <tbody id="scheduled-deliveries-body"></tbody>
        </table>
    </div>
    
    <div id="pagination_controls" class="d-flex justify-content-center align-items-center mt-3">
        <button id="prev_page" class="btn btn-secondary">&laquo; Previous</button>
        <span class="mx-3">Page <strong id="current_page">1</strong></span>
        <button id="next_page" class="btn btn-secondary">Next &raquo;</button>
    </div>
</div>
  
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const itemsOptions = <?php echo json_encode($itemsArray); ?>;
    const locationsOptions = <?php echo json_encode($locationsArray); ?>;
    
    let currentPage = 1;
    const limit = 20;

    function buildItemOptions(selectedId) {
        let html = '<option value="">-- Select --</option>';
        itemsOptions.forEach(function(item){
            let sel = (selectedId == item.id) ? ' selected' : '';
            html += `<option value="${item.id}"${sel}>${item.name}</option>`;
        });
        return html;
    }
    
    function buildLocationOptions(selectedId) {
        let html = '<option value="">-- Select --</option>';
        locationsOptions.forEach(function(loc){
            let sel = (selectedId == loc.id) ? ' selected' : '';
            html += `<option value="${loc.id}"${sel}>${loc.location}</option>`;
        });
        return html;
    }
    
    function addRow(itemData = {}) {
        let statusOptions = ['Pending', 'Delivered', 'Canceled', 'RTV'].map(s => `<option value="${s}" ${itemData.status === s ? 'selected' : ''}>${s}</option>`).join('');
        let rowHtml = `<tr>
            <td><select class="form-select" name="item_id[]" required>${buildItemOptions(itemData.item_id)}</select></td>
            <td><input type="number" class="form-control" name="quantity[]" required value="${itemData.quantity || ''}"></td>
            <td><select class="form-select" name="item_status[]" required>${statusOptions}</select></td>
            <td><select class="form-select" name="item_location[]" required>${buildLocationOptions(itemData.location)}</select></td>
            <td class="text-center align-middle"><span class="remove-row">❌</span></td>
        </tr>`;
        $('#items_table tbody').append(rowHtml);
    }
    
    function loadScheduled(page = 1) {
        currentPage = page;
        $('#scheduled-deliveries-body').html('<tr><td colspan="4" class="text-center">Loading...</td></tr>');
        $.ajax({
            url: 'delivery_ajax.php',
            method: 'POST',
            data: { action: 'get_scheduled', page: currentPage, limit: limit, start_date: $('#filter_start_date').val(), end_date: $('#filter_end_date').val() },
            dataType: 'json'
        }).done(function(response) {
            let html = '';
            if (response.success && response.scheduled && response.scheduled.length > 0) {
                response.scheduled.forEach(function(delivery) {
                    html += `<tr>
                        <td>${delivery.id}</td>
                        <td>${delivery.delivery_date}</td>
                        <td>${delivery.items}</td>
                        <td>
                            <button class="btn btn-sm btn-warning edit-delivery" data-id="${delivery.id}">Edit</button>
                            <button class="btn btn-sm btn-danger delete-delivery" data-id="${delivery.id}">Delete</button>
                        </td>
                    </tr>`;
                });
            } else {
                html = '<tr><td colspan="4" class="text-center">No scheduled deliveries found.</td></tr>';
            }
            $('#scheduled-deliveries-body').html(html);
            $('#current_page').text(currentPage);
            const totalPages = Math.ceil(response.total / limit);
            $('#prev_page').prop('disabled', currentPage <= 1);
            $('#next_page').prop('disabled', currentPage >= totalPages || totalPages === 0);
        }).fail(function() {
            $('#scheduled-deliveries-body').html('<tr><td colspan="4" class="text-center text-danger">Error loading data.</td></tr>');
        });
    }

    function clearForm() {
        $('#delivery-form')[0].reset();
        $('#delivery_id').val('');
        $('#items_table tbody').html('');
        addRow();
    }
    
    $(document).ready(function(){
        clearForm();
        loadScheduled(currentPage);
      
        $('#add_row').click(() => addRow());
        $(document).on('click', '.remove-row', function(){ $(this).closest('tr').remove(); });
        $('#clear_form').click(() => clearForm());

        $('#delivery-form').on('submit', function(e){
            e.preventDefault();
            $.ajax({
                url: 'delivery_ajax.php', method: 'POST', data: $(this).serialize() + '&action=schedule_delivery', dataType: 'json'
            }).done(function(response) {
                if(response.success){
                    alert('Delivery scheduled successfully');
                    clearForm();
                    loadScheduled(1);
                } else {
                    alert('Error: ' + response.message);
                }
            }).fail(function() { alert('An unexpected error occurred.'); });
        });
      
        $(document).on('click', '.delete-delivery', function(){
            if(confirm('Are you sure?')){
                const id = $(this).data('id');
                $.ajax({ url: 'delivery_ajax.php', method: 'POST', data: { action: 'delete_delivery', id: id }, dataType: 'json'
                }).done(function(response) {
                    if(response.success){
                        alert('Delivery deleted');
                        loadScheduled(1);
                    } else {
                        alert('Error: ' + response.message);
                    }
                });
            }
        });
      
        $(document).on('click', '.edit-delivery', function(){
            const id = $(this).data('id');
            $.ajax({ url: 'delivery_ajax.php', method: 'POST', data: { action: 'get_delivery', id: id }, dataType: 'json'
            }).done(function(response) {
                if(response.success){
                    const d = response.delivery;
                    $('#delivery_date').val(d.delivery_date);
                    $('#delivery_id').val(d.id);
                    $('#is_additional').prop('checked', d.is_additional == 1);
                    $('#items_table tbody').html('');
                    d.items.forEach(item => addRow(item));
                    $('html, body').animate({ scrollTop: 0 }, 300);
                } else {
                    alert('Error fetching delivery details: ' + response.message);
                }
            });
        });
      
        $('#filter_button').click(() => loadScheduled(1));
        $('#prev_page').click(() => { if (currentPage > 1) loadScheduled(currentPage - 1); });
        $('#next_page').click(() => {
            loadScheduled(currentPage + 1);
        });
    });
</script>
</body>
</html>