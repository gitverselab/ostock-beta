<?php
// Ensure session starts only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';
include 'navbar.php';

if ($_SESSION['role_id'] != 1) {
    echo "<div class='container mt-5 pt-5'><div class='alert alert-danger'>You do not have permission to access this page.</div></div>";
    exit;
}

// Updated query to JOIN with item_categories to get the category name
$sql = "SELECT 
            i.*, 
            ic.category_name 
        FROM items i
        LEFT JOIN item_categories ic ON i.category_id = ic.id
        ORDER BY i.name ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>List Items</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 70px; }
        .status-indicator {
            margin-left: 10px;
            font-style: italic;
            color: green;
            display: none;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Items List</h2>
        <a href="add_item.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Add New Item
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Item successfully saved!</div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Item Name</th>
                            <th>Item Code</th>
                            <th>Category</th>
                            <th class="text-end">Cost</th>
                            <th>Primary Unit</th>
                            <th>Secondary Unit</th>
                            <th class="text-center">On Calendar?</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']); ?></td>
                                <td><?= htmlspecialchars($row['item_code']); ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['category_name'] ?? 'N/A'); ?></span></td>
                                <td class="text-end">₱<?= number_format($row['cost'], 2); ?></td>
                                <td><?= htmlspecialchars($row['primary_uom_label']); ?></td>
                                <td><?= htmlspecialchars($row['secondary_uom_label']); ?></td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input calendar-toggle" type="checkbox" 
                                               data-item-id="<?= $row['id']; ?>" 
                                               <?= !empty($row['is_calendar_item']) ? 'checked' : ''; ?>>
                                    </div>
                                    <span class="status-indicator">Saved!</span>
                                </td>
                                <td>
                                    <a href="edit_item.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="delete_item.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure? This action cannot be undone.');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('.calendar-toggle').on('change', function() {
        let checkbox = $(this);
        let itemId = checkbox.data('item-id');
        let isChecked = checkbox.is(':checked');
        let statusIndicator = checkbox.closest('td').find('.status-indicator');

        $.ajax({
            url: 'update_calendar_status.php',
            type: 'POST',
            data: {
                item_id: itemId,
                status: isChecked ? 1 : 0
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    statusIndicator.fadeIn().delay(1000).fadeOut();
                } else {
                    alert('Error: ' + response.message);
                    checkbox.prop('checked', !isChecked);
                }
            },
            error: function() {
                alert('An unexpected error occurred. Please try again.');
                checkbox.prop('checked', !isChecked);
            }
        });
    });
});
</script>
</body>
</html>