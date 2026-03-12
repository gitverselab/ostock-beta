<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';
include 'navbar.php';

$finished_goods_with_bom = $conn->query(
    "SELECT i.id, i.name, b.yield_quantity 
     FROM items i 
     JOIN boms b ON i.id = b.finished_good_item_id 
     ORDER BY i.name ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Production Forecast Calculator</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-4">Production Forecast Calculator</h2>

    <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">1. Enter Forecasted Demand (in Pieces)</h5></div>
        <div class="card-body">
            <form id="forecast-form">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-light">
                            <tr>
                                <th>Finished Good</th>
                                <th class="text-center">BOM Yield (pcs/batch)</th>
                                <th>Forecast Qty Needed</th>
                                <th>Buffer Qty (For extra orders)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($item = $finished_goods_with_bom->fetch_assoc()): ?>
                            <tr>
                                <td class="align-middle"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="text-center align-middle"><?= htmlspecialchars($item['yield_quantity']) ?></td>
                                <td>
                                    <input type="number" class="form-control forecast-qty" data-item-id="<?= $item['id'] ?>" placeholder="0" min="0">
                                </td>
                                <td>
                                    <input type="number" class="form-control forecast-buffer" data-item-id="<?= $item['id'] ?>" placeholder="0" min="0">
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary">Calculate Forecast</button>
            </form>
        </div>
    </div>

    <div id="results-container" class="d-none">
        <hr>
        <div id="error-message" class="alert alert-danger d-none"></div>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white"><h5 class="mb-0">2. Production Run Summary</h5></div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead><tr><th>Finished Good</th><th>Forecast</th><th>Buffer</th><th>Total Demand</th><th>BOM Yield</th><th>Batches to Run</th><th>Total Planned Yield</th></tr></thead>
                    <tbody id="production-summary-body"></tbody>
                </table>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white"><h5 class="mb-0">3. Aggregated Raw Material Requirements</h5></div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead><tr><th>Raw Material</th><th>Total Required Quantity</th><th>Unit of Measure</th></tr></thead>
                    <tbody id="raw-materials-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('#forecast-form').on('submit', function(e) {
        e.preventDefault();
        
        let forecastData = [];
        $('.forecast-qty').each(function() {
            const itemId = $(this).data('item-id');
            const qty = $(this).val();
            // Find the corresponding buffer input for this item
            const buffer_qty = $(`.forecast-buffer[data-item-id="${itemId}"]`).val();

            if ((qty && parseInt(qty) > 0) || (buffer_qty && parseInt(buffer_qty) > 0)) {
                forecastData.push({
                    item_id: itemId,
                    quantity: parseInt(qty) || 0,
                    buffer: parseInt(buffer_qty) || 0 // NEW: Add buffer to data
                });
            }
        });

        if (forecastData.length === 0) {
            alert("Please enter a forecast or buffer quantity for at least one item.");
            return;
        }

        $.ajax({
            url: 'forecasting_ajax.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(forecastData),
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                $('#error-message').addClass('d-none');
                
                // UPDATED: Populate Production Summary with new columns
                let prodHtml = '';
                response.production_summary.forEach(item => {
                    prodHtml += `<tr>
                        <td>${item.finished_good_name}</td>
                        <td>${item.forecasted_qty}</td>
                        <td>${item.buffer_qty}</td>
                        <td><strong>${item.total_demand}</strong></td>
                        <td>${item.bom_yield}</td>
                        <td><strong>${item.batches_to_run}</strong></td>
                        <td>${item.total_yield}</td>
                    </tr>`;
                });
                $('#production-summary-body').html(prodHtml);

                // Populate Raw Material Summary (no changes needed here)
                let rawHtml = '';
                response.raw_materials.forEach(item => {
                    rawHtml += `<tr>
                        <td>${item.name}</td>
                        <td>${item.total_required.toFixed(4)}</td>
                        <td>${item.uom}</td>
                    </tr>`;
                });
                $('#raw-materials-body').html(rawHtml);

                $('#results-container').removeClass('d-none');
            } else {
                $('#error-message').text(response.message).removeClass('d-none');
                $('#results-container').addClass('d-none');
            }
        }).fail(function() {
            $('#error-message').text('An unknown error occurred. Please check the server logs.').removeClass('d-none');
            $('#results-container').addClass('d-none');
        });
    });
});
</script>
</body>
</html>