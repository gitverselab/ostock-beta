<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';
include 'functions.php'; // We need this for the can() function
include 'navbar.php';

if (!can('roles-manage')) {
    die("You do not have permission to access this page.");
}

$error_message = '';
$success_message = '';

// --- Handle Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $role_name = trim($_POST['role_name']);
        if (!empty($role_name)) {
            $stmt = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
            $stmt->bind_param("s", $role_name);
            if ($stmt->execute()) {
                $success_message = "Role added successfully!";
            } else {
                $error_message = "Role already exists or another error occurred.";
            }
            $stmt->close();
        }
    } elseif ($action === 'edit') {
        $role_id = intval($_POST['role_id']);
        $role_name = trim($_POST['role_name']);
        if (!empty($role_name) && $role_id > 1) { // Prevent editing Admin role
            $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE id = ?");
            $stmt->bind_param("si", $role_name, $role_id);
            if ($stmt->execute()) {
                $success_message = "Role updated successfully!";
            } else {
                $error_message = "Failed to update role.";
            }
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $role_id = intval($_POST['role_id']);
        if ($role_id > 1) { // Prevent deleting Admin role
            // Check if any users are assigned to this role
            $stmt_check = $conn->prepare("SELECT COUNT(*) as user_count FROM users WHERE role_id = ?");
            $stmt_check->bind_param("i", $role_id);
            $stmt_check->execute();
            $user_count = $stmt_check->get_result()->fetch_assoc()['user_count'];
            $stmt_check->close();

            if ($user_count > 0) {
                $error_message = "Cannot delete role: {$user_count} user(s) are currently assigned to it.";
            } else {
                $stmt_del = $conn->prepare("DELETE FROM roles WHERE id = ?");
                $stmt_del->bind_param("i", $role_id);
                if ($stmt_del->execute()) {
                    $success_message = "Role deleted successfully!";
                } else {
                    $error_message = "Failed to delete role.";
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
    <title>Manage Roles</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { padding-top: 70px; } </style>
</head>
<body>
<div class="container mt-4">
    <h2>Manage Roles</h2>
    <p>Add, edit, or remove user roles. The "Admin" role cannot be changed or deleted.</p>

    <?php if ($success_message): ?><div class="alert alert-success"><?= $success_message ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?= $error_message ?></div><?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Existing Roles</div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead><tr><th>Role Name</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            <?php
                            $result = $conn->query("SELECT * FROM roles ORDER BY id");
                            while ($row = $result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['role_name']) ?></td>
                                    <td class="text-end">
                                        <?php if ($row['id'] == 1): // Admin Role ?>
                                            <span class="text-muted fst-italic">All Permissions</span>
                                        <?php else: ?>
                                            <a href="manage_permissions.php?role_id=<?= $row['id'] ?>" class="btn btn-info btn-sm">Edit Permissions</a>
                                            <button class="btn btn-warning btn-sm edit-role-btn" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['role_name']) ?>">Rename</button>
                                            <form action="roles.php" method="POST" style="display:inline-block;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="role_id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        <?php endif; ?>
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
                <div class="card-header">Add New Role</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="role_name" class="form-label">Role Name</label>
                            <input type="text" id="role_name" name="role_name" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Add Role</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editRoleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Rename Role</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="role_id" id="edit_role_id">
            <div class="mb-3">
                <label for="edit_role_name" class="form-label">New Role Name</label>
                <input type="text" class="form-control" name="role_name" id="edit_role_name" required>
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
    const editModal = new bootstrap.Modal(document.getElementById('editRoleModal'));
    $('.edit-role-btn').on('click', function() {
        const roleId = $(this).data('id');
        const roleName = $(this).data('name');
        $('#edit_role_id').val(roleId);
        $('#edit_role_name').val(roleName);
        editModal.show();
    });
});
</script>
</body>
</html>