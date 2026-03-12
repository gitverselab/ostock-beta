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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 70px; background-color: #f8f9fa; }
        .calendar-wrapper { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; }
        .calendar-container {
            flex: 1;
            min-width: 300px;
            overflow: auto; /* Enables both horizontal and vertical scrolling */
            max-height: 80vh; /* Sets a maximum height for the container */
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: .375rem;
        }
        .inventory-panel-wrapper { flex: 0 0 320px; width: 100%; }
        @media (min-width: 992px) { .inventory-panel-wrapper { width: 320px; } }
        .inventory-panel {
            /* Height will be set by JavaScript */
            overflow-y: auto;
        }

        /* --- Sticky CSS --- */
        .calendar-table { border-collapse: separate; border-spacing: 0; }
        .calendar-table th, .calendar-table td {
            border: 1px solid #dee2e6; text-align: center; vertical-align: middle;
            padding: 0.5rem; white-space: nowrap;
        }
        .calendar-table thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
        .calendar-table th:first-child, .calendar-table td:first-child {
            position: sticky; left: 0; background: #ffffff; border-right: 2px solid #adb5bd; font-weight: bold;
        }
        .calendar-table thead th:first-child { background: #f8f9fa; z-index: 3; }
        
        /* Status Colors */
        .status-box { padding: 0.25rem 0.5rem; margin-bottom: 0.25rem; border-radius: .25rem; }
        .pending-future { background-color: #fff3cd; }
        .pending-past { background-color: #ffc107; }
        .delivered { background-color: #28a745; color: white; }
        .canceled { background-color: #dc3545; color: white; }
        .rtv { background-color: #0d6efd; color: white; }
    </style>
</head>
<body>
<div class="container-fluid my-4">
    <h1 class="mb-4 text-center">Delivery Calendar</h1>
    <div class="calendar-wrapper">
        <div class="calendar-container">
            <div id="calendar-placeholder" class="text-center p-5">Loading calendar...</div>
            <table class="table table-bordered calendar-table d-none">
                <thead><tr id="calendar-header-row"></tr></thead>
                <tbody id="calendar-body"></tbody>
            </table>
        </div>
        <div class="inventory-panel-wrapper">
            <div class="card">
                <div class="card-header fw-bold">Inventory Stock</div>
                <div class="card-body inventory-panel p-0">
                    <table class="table table-striped table-sm mb-0">
                        <thead class="table-dark"><tr><th>Item</th><th>Location</th><th>Stock</th></tr></thead>
                        <tbody id="inventory-panel-body">
                            <tr><td colspan="3" class="text-center">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
  
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
    function refreshCalendar() {
        $.ajax({
            url: 'delivery_ajax.php', method: 'POST', data: { action: 'get_calendar_schedule' }, dataType: 'json'
        }).done(function(response) {
            if (!response.success) {
                $('#calendar-placeholder').html(`<div class="alert alert-danger">${response.message || 'Could not load calendar data.'}</div>`).removeClass('d-none');
                $('.calendar-table').addClass('d-none');
                return;
            }
            const { dates, calendar } = response;
            
            let headerHtml = '<th>Item</th>';
            dates.forEach(function(date){
                const d = new Date(date + 'T12:00:00');
                headerHtml += `<th data-date="${date}">${d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</th>`;
            });
            $('#calendar-header-row').html(headerHtml);

            let bodyHtml = '';
            const todayStr = new Date().toISOString().slice(0, 10);
            
            calendar.forEach(function(item){
                bodyHtml += `<tr><td>${item.name}</td>`;
                dates.forEach(function(date){
                    let cellHtml = '';
                    if(item.schedules && item.schedules[date]){
                        item.schedules[date].forEach(function(sched) {
                            const status = (sched.status || 'Pending').toLowerCase().trim();
                            let statusClass = (date < todayStr && status === 'pending') ? 'pending-past' : (status === 'pending' ? 'pending-future' : status);
                            cellHtml += `<div class="status-box ${statusClass}">
                                <strong>${sched.quantity}${sched.is_additional == 1 ? ' (A)' : ''}</strong><br>
                                <small>${sched.delivery_location || 'N-A'}</small>
                            </div>`;
                        });
                    }
                    bodyHtml += `<td>${cellHtml}</td>`;
                });
                bodyHtml += '</tr>';
            });
            $('#calendar-body').html(bodyHtml);

            $('#calendar-placeholder').addClass('d-none');
            $('.calendar-table').removeClass('d-none');
            
            // --- NEW: Sync panel heights ---
            // We set the height of the inventory panel to match the main calendar container
            const calendarHeight = $('.calendar-container').height();
            $('.inventory-panel').css('max-height', calendarHeight + 'px');

            // --- UPDATED: Auto-scroll to today's date ---
            let targetCell = $(`th[data-date="${todayStr}"]`);
            if (targetCell.length) {
                let container = $('.calendar-container');
                let stickyWidth = $('th:first-child').first().outerWidth() || 0;
                let scrollPos = targetCell.position().left + container.scrollLeft() - stickyWidth;
                container.animate({ scrollLeft: scrollPos }, 300);
            }

        }).fail(function() {
            $('#calendar-placeholder').html('<div class="alert alert-danger">Could not load calendar data.</div>').removeClass('d-none');
            $('.calendar-table').addClass('d-none');
        });
    }
      
    function refreshInventory(){
        $.ajax({
            url: 'delivery_ajax.php', method: 'POST', data: { action: 'get_inventory' }, dataType: 'json'
        }).done(function(response) {
            let invHtml = '';
            if (response.success && response.inventory && response.inventory.length > 0) {
                response.inventory.forEach(function(inv){
                    invHtml += `<tr><td>${inv.item}</td><td>${inv.location}</td><td>${inv.stock}</td></tr>`;
                });
            } else {
                invHtml = '<tr><td colspan="3" class="text-center">No inventory data.</td></tr>';
            }
            $('#inventory-panel-body').html(invHtml);
        }).fail(function() {
            $('#inventory-panel-body').html('<tr><td colspan="3" class="text-center text-danger">Error loading inventory.</td></tr>');
        });
    }
    
    refreshCalendar();
    refreshInventory();
    
    setInterval(function(){
        refreshCalendar();
        refreshInventory();
    }, 60000);
});
</script>
</body>
</html>