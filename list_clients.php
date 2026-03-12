<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
include 'navbar.php';

// Fetch all clients
$clients = $conn->query("SELECT * FROM clients");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clients List</title>
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
    <h2>Clients List</h2>
    <a href="add_client.php" class="btn btn-primary mt-3">Add New Client</a>
    <table class="table mt-3 table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Business Name</th>
                <th>Contacts</th>
                <th>Locations</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($client = $clients->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($client['id']); ?></td>
                    <td><?= htmlspecialchars($client['business_name']); ?></td>
                    <td>
                        <?php
                        $contacts = $conn->query("SELECT contact_person, contact_number FROM client_contacts WHERE client_id = " . $client['id']);
                        while ($contact = $contacts->fetch_assoc()) {
                            echo "<p>" . htmlspecialchars($contact['contact_person']) . " - " . htmlspecialchars($contact['contact_number']) . "</p>";
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        $locations = $conn->query("SELECT location FROM client_locations WHERE client_id = " . $client['id']);
                        while ($location = $locations->fetch_assoc()) {
                            echo "<p>" . htmlspecialchars($location['location']) . "</p>";
                        }
                        ?>
                    </td>
                    <td>
                        <a href="edit_client.php?id=<?= $client['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="delete_client.php?id=<?= $client['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this client?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
