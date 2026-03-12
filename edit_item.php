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

// --- Handle Form Submission FIRST ---
$error_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    if ($_SESSION['role_id'] != 1) { 
        die("Permission denied.");
    }
    
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $item_code = trim($_POST['item_code']);
    $uom = trim($_POST['uom']);
    $category_id = intval($_POST['category_id']);
    $cost = floatval($_POST['cost']);
    $is_calendar_item = isset($_POST['is_calendar_item']) ? 1 : 0;
    $primary_label = trim($_POST['primary_uom_label']);
    $secondary_label = trim($_POST['secondary_uom_label']);
    
    $stmt = $conn->prepare("UPDATE items SET name = ?, item_code = ?, uom = ?, category_id = ?, cost = ?, is_calendar_item = ?, primary_uom_label = ?, secondary_uom_label = ? WHERE id = ?");
    $stmt->bind_param("sssidissi", $name, $item_code, $uom, $category_id, $cost, $is_calendar_item, $primary_label, $secondary_label, $id);
    
    if($stmt->execute()){
        header("Location: list_items.php?success=edit");
        exit;
    } else {
        $error_message = "Failed to update item.";
    }
    $stmt->close();
}

// --- Now, include visual elements and prepare data for display ---
include 'navbar.php';

if ($_SESSION['role_id'] != 1) { 
    echo "<div class='container mt-5 pt-5'><div class='alert alert-danger'>You do not have permission to access this page.</div></div>";
    exit;
}

$item = null;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
}

if (!$item) {
    die("<div class='container mt-5 pt-5'><div class='alert alert-danger'>Item not found.</div></div>");
}

// Fetch item categories for the dropdown
$categories_result = $conn->query("SELECT * FROM item_categories ORDER BY category_name ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Item</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <h2>Edit Item: <?= htmlspecialchars($item['name']) ?></h2>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form method="POST" action="edit_item.php?id=<?= htmlspecialchars($item['id']); ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']); ?>">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Item Name:</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($item['name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Item Code:</label>
                    <input type="text" name="item_code" class="form-control" value="<?= htmlspecialchars($item['item_code']); ?>" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="category_id" class="form-label">Item Category:</label>
                        <select name="category_id" id="category_id" class="form-select" required>
                            <option value="">-- Select a Category --</option>
                            <?php while ($category = $categories_result->fetch_assoc()): ?>
                                <option value="<?= $category['id'] ?>" <?= ($category['id'] == $item['category_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="cost" class="form-label">Cost / Value (per secondary unit):</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" name="cost" id="cost" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($item['cost']); ?>" required>
                        </div>
                    </div>
                </div>
                <hr>
                <h5>Unit of Measure Details</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Primary Unit Label:</label>
                        <input type="text" name="primary_uom_label" class="form-control" placeholder="e.g., Crates, Sacks, Boxes" value="<?= htmlspecialchars($item['primary_uom_label']); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Secondary Unit Label:</label>
                        <input type="text" name="secondary_uom_label" class="form-control" placeholder="e.g., Pieces, KG, Liters" value="<?= htmlspecialchars($item['secondary_uom_label']); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Base UOM (for records):</label>
                        <input type="text" name="uom" class="form-control" placeholder="e.g., PCS, KG, L" value="<?= htmlspecialchars($item['uom']); ?>" required>
                    </div>
                </div>
                 <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_calendar_item" value="1" id="is_calendar_item" <?= !empty($item['is_calendar_item']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_calendar_item">
                        Show on Delivery Calendar
                    </label>
                </div>
            </div>
            <div class="card-footer text-end">
                 <a href="list_items.php" class="btn btn-light">Cancel</a>
                 <button type="submit" class="btn btn-primary">Update Item</button>
            </div>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>