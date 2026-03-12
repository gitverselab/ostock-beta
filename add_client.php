<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
include 'navbar.php';

$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $business_name = $_POST['business_name'];

    $stmt = $conn->prepare("INSERT INTO clients (business_name) VALUES (?)");
    $stmt->bind_param("s", $business_name);
    $stmt->execute();
    $client_id = $stmt->insert_id;
    $stmt->close();

    if (isset($_POST['contacts'])) {
        foreach ($_POST['contacts'] as $contact) {
            $stmt = $conn->prepare("INSERT INTO client_contacts (client_id, contact_person, contact_number) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $client_id, $contact['person'], $contact['number']);
            $stmt->execute();
            $stmt->close();
        }
    }

    if (isset($_POST['locations'])) {
        foreach ($_POST['locations'] as $location) {
            $stmt = $conn->prepare("INSERT INTO client_locations (client_id, location) VALUES (?, ?)");
            $stmt->bind_param("is", $client_id, $location);
            $stmt->execute();
            $stmt->close();
        }
    }

    $success = true;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Client</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
  body {
    padding-top: 70px; /* adjust if your navbar height differs */
  }
</style>
</head>
<body>
<div class="container mt-4">
    <h2>Add Client</h2>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Client added successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label>Business Name</label>
            <input type="text" name="business_name" class="form-control" required>
        </div>

        <h4>Contacts</h4>
        <div id="contacts-container">
            <div class="contact-entry mb-2">
                <input type="text" name="contacts[0][person]" class="form-control mb-1" placeholder="Contact Person" required>
                <input type="text" name="contacts[0][number]" class="form-control mb-1" placeholder="Contact Number" required>
                <button type="button" class="btn btn-danger remove-contact">Remove</button>
            </div>
        </div>
        <button type="button" class="btn btn-secondary mt-2" id="add-contact">Add Contact</button>

        <h4 class="mt-4">Locations</h4>
        <div id="locations-container">
            <div class="location-entry mb-2">
                <input type="text" name="locations[0]" class="form-control" placeholder="Business Location" required>
                <button type="button" class="btn btn-danger remove-location">Remove</button>
            </div>
        </div>
        <button type="button" class="btn btn-secondary mt-2" id="add-location">Add Location</button>

        <button type="submit" class="btn btn-primary mt-3">Save Client</button>
    </form>
</div>

<script>
let contactIndex = 1, locationIndex = 1;

$("#add-contact").click(function () {
    $("#contacts-container").append(`
        <div class="contact-entry mb-2">
            <input type="text" name="contacts[${contactIndex}][person]" class="form-control mb-1" placeholder="Contact Person" required>
            <input type="text" name="contacts[${contactIndex}][number]" class="form-control mb-1" placeholder="Contact Number" required>
            <button type="button" class="btn btn-danger remove-contact">Remove</button>
        </div>
    `);
    contactIndex++;
});

$("#add-location").click(function () {
    $("#locations-container").append(`
        <div class="location-entry mb-2">
            <input type="text" name="locations[${locationIndex}]" class="form-control" placeholder="Business Location" required>
            <button type="button" class="btn btn-danger remove-location">Remove</button>
        </div>
    `);
    locationIndex++;
});

$(document).on("click", ".remove-contact", function () {
    $(this).parent().remove();
});

$(document).on("click", ".remove-location", function () {
    $(this).parent().remove();
});
</script>
</body>
</html>
