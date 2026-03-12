<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';
include 'functions.php';
include 'navbar.php';

// Check for permission. The 'can' function will always allow Admin (role_id 1).
if (!can('categories-manage')) {
    die("<div class='container mt-5 pt-5'><div class='alert alert-danger'>You do not have permission to access this page.</div></div>");
}

$error_message = '';
$success_message = '';

// --- Handle Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $category_name = trim($_POST['category_name']);
        if (!empty($category_name)) {
            $stmt = $conn->prepare("INSERT INTO item_categories (category_name) VALUES (?)");
            $stmt->bind_param("s", $category_name);
            if ($stmt->execute()) {
                $success_message = "Category '" . htmlspecialchars($category_name) . "' added successfully!";
            } else {
                $error_message = "Category already exists or another error occurred.";
            }
            $stmt->close();
        }
    } elseif ($action === 'edit') {
        $category_id = intval($_POST['category_id']);
        $category_name = trim($_POST['category_name']);
        if (!empty($category_name) && $category_id > 0) {
            $stmt = $conn->prepare("UPDATE item_categories SET category_name = ? WHERE id = ?");
            $stmt->bind_param("si", $category_name, $category_id);
            if ($stmt->execute()) {
                $success_message = "Category updated successfully!";
            } else {
                $error_message = "Failed to update category. The name may already be in use.";
            }
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $category_id = intval($_POST['category_id']);
        if ($category_id > 0) {
            // Safety Check: See if any items are using this category
            $stmt_check = $conn->prepare("SELECT COUNT(*) as item_count FROM items WHERE category_id = ?");
            $stmt_check->bind_param("i", $category_id);
            $stmt_check->execute();
            $item_count = $stmt_check->get_result()->fetch_assoc()['item_count'];
            $stmt_check->close();

            if ($item_count > 0) {
                $error_message = "Cannot delete category: {$item_count} item(s) are currently assigned to it.";
            } else {
                $stmt_del = $conn->prepare("DELETE FROM item_categories WHERE id = ?");
                $stmt_del->bind_param("i", $category_id);
                if ($stmt_del->execute()) {
                    $success_message = "Category deleted successfully!";
                } else {
                    $error_message = "Failed to delete category.";
                }
                $stmt_del->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Item Categories</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <h2>Manage Item Categories</h2>
    <p>Add, edit, or remove categories used to classify your inventory items.</p>

    <?php if ($success_message): ?><div class="alert alert-success"><?= $success_message ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?= $error_message ?></div><?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Existing Categories</div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead><tr><th>Category Name</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            <?php
                            $result = $conn->query("SELECT * FROM item_categories ORDER BY category_name");
                            while ($row = $result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['category_name']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-warning btn-sm edit-category-btn" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['category_name']) ?>">Rename</button>
                                        <form action="manage_categories.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this category?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Add New Category</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="category_name" class="form-label">Category Name</label>
                            <input type="text" id="category_name" name="category_name" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Add Category</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Rename Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="category_id" id="edit_category_id">
            <div class="mb-3">
                <label for="edit_category_name" class="form-label">New Category Name</label>
                <input type="text" class="form-control" name="category_name" id="edit_category_name" required>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    $('.edit-category-btn').on('click', function() {
        const categoryId = $(this).data('id');
        const categoryName = $(this).data('name');
        $('#edit_category_id').val(categoryId);
        $('#edit_category_name').val(categoryName);
        editModal.show();
    });
});
</script>
</body>
</html>