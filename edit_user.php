<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'db.php';
// We don't include the navbar yet

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid user ID.");
}

// --- Handle Form Submission FIRST ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = htmlspecialchars($_POST['username']);
    $role_id = intval($_POST['role_id']);
    $password = trim($_POST['password']);

    if (!empty($password)) {
        // If a new password is provided, hash it and update all fields.
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username = ?, role_id = ?, password = ? WHERE id = ?");
        $stmt->bind_param("sisi", $username, $role_id, $hashedPassword, $id);
    } else {
        // No password change: update only username and role.
        $stmt = $conn->prepare("UPDATE users SET username = ?, role_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $username, $role_id, $id);
    }
    
    if ($stmt->execute()) {
        // This redirect will now work correctly
        header("Location: users.php?success=edit");
        exit;
    }
    // If we get here, the execute failed.
    $error_message = "Failed to update user.";
    $stmt->close();
}

// --- Now include visual elements and prepare data for display ---
include 'navbar.php';

// Fetch user data for the form
$stmt = $conn->prepare("SELECT username, role_id FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}

// Fetch roles for the dropdown
$roles = $conn->query("SELECT * FROM roles");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
      body { padding-top: 70px; }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2>Edit User</h2>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    <form method="POST" action="edit_user.php?id=<?= $id ?>">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="role_id" class="form-label">Role</label>
            <select class="form-control" id="role_id" name="role_id" required>
                <?php while ($role = $roles->fetch_assoc()): ?>
                    <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $user['role_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($role['role_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="(Leave blank to keep current password)">
            <small class="text-muted">Leave blank if you do not wish to change the password.</small>
        </div>
        <button type="submit" class="btn btn-primary">Update User</button>
        <a href="users.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>