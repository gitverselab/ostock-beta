<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';

// Only Admins (role_id 1) can access this page
if ($_SESSION['role_id'] != 1) {
    echo "<script>alert('You do not have permission to access this page.'); window.location='dashboard.php';</script>";
    exit;
}

// Determine the action from GET parameter, default to "list"
$action = $_GET['action'] ?? 'list';
$error_message = '';
$success_message = '';

// Process all form submissions at the top of the file
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Use a switch statement to handle different actions from POST requests
    $post_action = $_POST['action'] ?? '';

    switch ($post_action) {
        case "add":
            $name = trim($_POST['name']);
            $address = trim($_POST['address']);
            
            if (!empty($name) && !empty($address)) {
                $stmt = $conn->prepare("INSERT INTO warehouses (name, address) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $address);
                if ($stmt->execute()) {
                    header("Location: manage_warehouses.php?success=add");
                    exit;
                } else {
                    $error_message = "Error adding warehouse.";
                }
                $stmt->close();
            } else {
                $error_message = "Name and address cannot be empty.";
            }
            break;

        case "edit":
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $address = trim($_POST['address']);

            if ($id > 0 && !empty($name) && !empty($address)) {
                $stmt = $conn->prepare("UPDATE warehouses SET name = ?, address = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $address, $id);
                if ($stmt->execute()) {
                    header("Location: manage_warehouses.php?success=edit");
                    exit;
                } else {
                    $error_message = "Error updating warehouse.";
                }
                $stmt->close();
            } else {
                $error_message = "Invalid data provided for update.";
            }
            break;
            
        case "delete":
            $id = intval($_POST['id']);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM warehouses WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    header("Location: manage_warehouses.php?success=delete");
                    exit;
                } else {
                    $error_message = "Error deleting warehouse.";
                }
                $stmt->close();
            }
            break;
    }
}

// Display success messages based on GET parameters
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'add': $success_message = "Warehouse added successfully!"; break;
        case 'edit': $success_message = "Warehouse updated successfully!"; break;
        case 'delete': $success_message = "Warehouse deleted successfully!"; break;
    }
}

// --- Now, include visual elements for display ---
include 'navbar.php'; // CORRECT: Navbar is included after processing is done
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Warehouses</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
      body {
        padding-top: 70px; /* adjust if your navbar height differs */
      }
    </style>
</head>
<body>
<div class="container mt-4">
    <h2>Warehouse Management</h2>
    <a href="dashboard.php" class="btn btn-secondary mb-3">Return to Dashboard</a>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if ($action == "list"): ?>
        <a href="manage_warehouses.php?action=add" class="btn btn-primary mb-3">Add New Warehouse</a>
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // This query is safe as it does not use user input
                $result = $conn->query("SELECT * FROM warehouses ORDER BY id ASC");
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                            <td>" . htmlspecialchars($row['id']) . "</td>
                            <td>" . htmlspecialchars($row['name']) . "</td>
                            <td>" . htmlspecialchars($row['address']) . "</td>
                            <td>
                                <a href='manage_warehouses.php?action=edit&id=" . htmlspecialchars($row['id']) . "' class='btn btn-warning btn-sm'>Edit</a>
                                <form method='POST' action='manage_warehouses.php' style='display:inline-block;'>
                                    <input type='hidden' name='action' value='delete'>
                                    <input type='hidden' name='id' value='" . htmlspecialchars($row['id']) . "'>
                                    <button type='submit' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this warehouse?\")'>Delete</button>
                                </form>
                            </td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' class='text-center'>No warehouses found</td></tr>";
                }
                ?>
            </tbody>
        </table>
    <?php elseif ($action == "add"): ?>
        <h4>Add New Warehouse</h4>
        <form method="POST" action="manage_warehouses.php">
            <input type="hidden" name="action" value="add">
            <div class="mb-3">
                <label for="name" class="form-label">Warehouse Name</label>
                <input type="text" class="form-control" name="name" id="name" required>
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" name="address" id="address" rows="3" required></textarea>
            </div>
            <button type="submit" class="btn btn-success">Add Warehouse</button>
            <a href="manage_warehouses.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php elseif ($action == "edit" && isset($_GET['id'])): ?>
        <?php
        $id = intval($_GET['id']);
        $warehouse = null;
        if ($id > 0) {
            // Securely fetch the warehouse to edit
            $stmt = $conn->prepare("SELECT * FROM warehouses WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $warehouse = $result->fetch_assoc();
            $stmt->close();
        }

        if ($warehouse):
        ?>
            <h4>Edit Warehouse</h4>
            <form method="POST" action="manage_warehouses.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= htmlspecialchars($warehouse['id']); ?>">
                <div class="mb-3">
                    <label for="name" class="form-label">Warehouse Name</label>
                    <input type="text" class="form-control" name="name" id="name" value="<?= htmlspecialchars($warehouse['name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" name="address" id="address" rows="3" required><?= htmlspecialchars($warehouse['address']); ?></textarea>
                </div>
                <button type="submit" class="btn btn-success">Save Changes</button>
                <a href="manage_warehouses.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php else: ?>
            <div class="alert alert-danger">Warehouse not found.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>